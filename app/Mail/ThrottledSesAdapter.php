<?php

declare(strict_types=1);

namespace App\Mail;

use Aws\Ses\Exception\SesException;
use Aws\Ses\SesClient;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Sendportal\Base\Adapters\SesMailAdapter;
use Sendportal\Base\Services\Messages\MessageTrackingOptions;

/**
 * Host-level SES adapter that paces sends to the account MaxSendRate across all
 * Horizon workers via a shared Redis DurationLimiter, and fixes the two vendor
 * throttle-path bugs (code misclassification and null-return on exhaustion).
 *
 * send() is overridden WHOLESALE and never calls parent::send() — the parent
 * re-enters the buggy ThrottlesSending trait. Only the safe inherited protected
 * helpers (resolveClient, resolveMessageId, getSendQuota, $this->config) are reused.
 */
final class ThrottledSesAdapter extends SesMailAdapter
{
    /**
     * Sentinel returned by resolveSendRate() when the account has no per-second
     * cap (SES MaxSendRate = -1). send() bypasses the limiter entirely in this case.
     */
    public const RATE_UNLIMITED = -1;

    /** A Throttling error caused by exceeding the per-second rate — retry it. */
    public const CLASSIFY_RATE = 'rate_retryable';

    /** A Throttling error caused by the daily quota — fail fast, do not retry. */
    public const CLASSIFY_DAILY_QUOTA = 'daily_quota_failfast';

    /** Any non-Throttling SesException — propagate unchanged. */
    public const CLASSIFY_PROPAGATE = 'propagate';

    /**
     * The Horizon worker timeout (config/horizon.php: sendportal-message-dispatch
     * has no explicit worker timeout, so the 60s default applies). The aggregate
     * in-send wait budget MUST stay below this so a slow send never triggers a
     * duplicate reservation (SES-05).
     */
    public const WORKER_TIMEOUT_SECONDS = 60;

    /** Guards the config-clamp warning so it is logged at most once per process. */
    private static bool $clampWarned = false;

    /**
     * Inject a pre-built (mocked) SES client. The factory instantiates adapters
     * with `new` (no container DI), so this is the seam tests use.
     */
    public function setClient(SesClient $client): void
    {
        $this->client = $client;
    }

    /**
     * Wholesale override — preserves the parent's exact signature and return type.
     *
     * @throws SesSendThrottledException on Redis limiter block timeout.
     */
    public function send(string $fromEmail, string $fromName, string $toEmail, string $subject, MessageTrackingOptions $trackingOptions, string $content): string
    {
        $payload = $this->buildPayload($fromEmail, $fromName, $toEmail, $subject, $content);
        $rate = $this->resolveSendRate();

        // ONE shared wall-clock deadline: every Redis block AND every reactive
        // backoff draws from this single budget, so the aggregate in-send wait can
        // never exceed max_total_wait_seconds regardless of how max_send_attempts or
        // max_block_seconds are tuned (SES-05).
        $deadline = microtime(true) + $this->maxTotalWaitSeconds();

        $attempt = $rate === self::RATE_UNLIMITED
            ? fn (): string => $this->resolveMessageId($this->resolveClient()->sendEmail($payload))
            : fn (): string => $this->pacedSesCall($rate, $payload, $deadline);

        return $this->runWithRetries($attempt, $deadline);
    }

    /**
     * The effective aggregate in-send wait budget in seconds.
     *
     * Config guard (SES-05): a value >= the 60s worker timeout is clamped down (and
     * warned once) so the configured values can never sum past the worker timeout.
     */
    public function maxTotalWaitSeconds(): int
    {
        $configured = (int) config('sendportal-throttle.max_total_wait_seconds', 45);

        if ($configured >= self::WORKER_TIMEOUT_SECONDS) {
            if (! self::$clampWarned) {
                self::$clampWarned = true;
                Log::warning(sprintf(
                    'sendportal-throttle.max_total_wait_seconds (%d) must be < %ds worker timeout; clamping to %d.',
                    $configured,
                    self::WORKER_TIMEOUT_SECONDS,
                    self::WORKER_TIMEOUT_SECONDS - 1
                ));
            }

            return self::WORKER_TIMEOUT_SECONDS - 1;
        }

        return max(1, $configured);
    }

    /**
     * Resolve the per-second send rate from the account MaxSendRate.
     *
     * Cached for rate_cache_ttl; the refresh is single-flight (Cache::lock) with a
     * sibling last-known-good key, so a held lock returns the stale value instead of
     * issuing a second GetSendQuota (stampede protection across ~20 workers).
     *
     * Returns self::RATE_UNLIMITED when the account is uncapped.
     */
    public function resolveSendRate(): int
    {
        $cacheKey = 'sp:ses:maxrate:' . $this->throttleKeyHash();
        $ttl = (int) config('sendportal-throttle.rate_cache_ttl', 300);

        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return (int) $cached;
        }

        $lock = Cache::lock($cacheKey . ':lock', 10);

        if (! $lock->get()) {
            // A concurrent worker is refreshing: prefer last-known-good over a
            // second GetSendQuota call.
            $last = Cache::get($cacheKey . ':last');

            return $last !== null ? (int) $last : $this->normalizeRate(null);
        }

        try {
            // Double-check under the lock in case a peer just populated it.
            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                return (int) $cached;
            }

            $rate = $this->normalizeRate($this->readMaxSendRate());

            Cache::put($cacheKey, $rate, $ttl);
            Cache::forever($cacheKey . ':last', $rate);

            return $rate;
        } finally {
            $lock->release();
        }
    }

    /**
     * The md5(region|access-key-id) hash that keys both the rate cache and the
     * shared Redis limiter. MaxSendRate is an account-per-region quota, so this
     * keying coordinates every worker for the same SES account/region.
     */
    public function throttleKeyHash(): string
    {
        return md5(Arr::get($this->config, 'region') . '|' . Arr::get($this->config, 'key'));
    }

    /**
     * Run a single send attempt inside a bounded reactive retry loop (SES-04).
     *
     * On a RATE-classified SesException the send is retried up to max_send_attempts
     * times with a backoff drawn from the shared deadline. DAILY_QUOTA fails fast and
     * any other SesException propagates unchanged. On exhaustion (attempts or the
     * shared deadline) a named SesSendThrottledException is thrown — never null, so
     * the string return type can never TypeError. A Redis block-timeout surfaces as
     * the same SesSendThrottledException from pacedSesCall() and is not retried.
     */
    protected function runWithRetries(callable $attempt, float $deadline): string
    {
        $maxAttempts = max(1, (int) config('sendportal-throttle.max_send_attempts', 3));
        $backoffMs = (int) config('sendportal-throttle.reactive_backoff_ms', 200);
        $attempts = 0;

        while (true) {
            $attempts++;

            try {
                return $attempt();
            } catch (SesException $e) {
                if ($this->classifyThrottleException($e) !== self::CLASSIFY_RATE) {
                    throw $e; // DAILY_QUOTA fail-fast; PROPAGATE unchanged
                }

                if ($attempts >= $maxAttempts || microtime(true) >= $deadline) {
                    throw new SesSendThrottledException(
                        sprintf(
                            'SES send throttled after %d attempt(s) on key %s.',
                            $attempts,
                            $this->throttleKeyHash()
                        ),
                        0,
                        $e
                    );
                }

                $this->reactiveBackoff($backoffMs, $deadline);
            }
        }
    }

    /**
     * Pace one send through the shared Redis DurationLimiter, then issue the SES
     * call inside the acquired 1-second window and resolve the message id. The block
     * cap is drawn from the shared deadline so it never exceeds the remaining budget.
     */
    protected function pacedSesCall(int $rate, array $payload, float $deadline): string
    {
        $key = 'sp:ses:rate:' . $this->throttleKeyHash();

        return Redis::throttle($key)
            ->allow($rate)
            ->every(1)
            ->block($this->currentBlockCap($deadline))
            ->sleep((int) config('sendportal-throttle.block_sleep_ms', 100))
            ->then(
                fn (): string => $this->resolveMessageId($this->resolveClient()->sendEmail($payload)),
                function (): string {
                    throw new SesSendThrottledException(
                        'SES rate limiter block timed out for key ' . $this->throttleKeyHash() . '.'
                    );
                }
            );
    }

    /**
     * The per-attempt Redis block cap: min(max_block_seconds, remaining budget),
     * never negative. When the budget is spent this is 0, so the limiter times out
     * immediately rather than pushing the aggregate wait past the deadline.
     */
    protected function currentBlockCap(float $deadline): int
    {
        $configured = (int) config('sendportal-throttle.max_block_seconds', 15);
        $remaining = (int) floor($deadline - microtime(true));

        return max(0, min($configured, $remaining));
    }

    /**
     * Sleep between FAILED rate-classified attempts, capped by the remaining shared
     * budget so cumulative backoffs can never exceed the deadline.
     */
    protected function reactiveBackoff(int $backoffMs, float $deadline): void
    {
        $remainingMs = (int) max(0, floor(($deadline - microtime(true)) * 1000));
        $sleepMs = min($backoffMs, $remainingMs);

        if ($sleepMs > 0) {
            usleep($sleepMs * 1000);
        }
    }

    /**
     * Classify a SesException (SES-03, BUG 1 fix).
     *
     * Detection is CODE-gated first: any non-'Throttling' error propagates
     * unchanged. AWS returns the SAME 'Throttling' code for both "Maximum sending
     * rate exceeded." (retryable) and "Daily message quota exceeded." (fail fast),
     * so the Throttling case is sub-branched on getAwsErrorMessage() — the clean AWS
     * text — NOT the verbose getMessage() wrapper the vendor trait matched on.
     */
    public function classifyThrottleException(SesException $e): string
    {
        if ($e->getAwsErrorCode() !== 'Throttling') {
            return self::CLASSIFY_PROPAGATE;
        }

        $message = strtolower((string) $e->getAwsErrorMessage());

        if (str_contains($message, 'daily message quota exceeded')) {
            return self::CLASSIFY_DAILY_QUOTA;
        }

        return self::CLASSIFY_RATE;
    }

    /**
     * Read the raw MaxSendRate value from getSendQuota() (null if absent).
     */
    protected function readMaxSendRate(): mixed
    {
        return $this->getSendQuota()['MaxSendRate'] ?? null;
    }

    /**
     * Normalize a raw MaxSendRate to a usable integer per-second rate.
     *
     * -1 (uncapped) -> self::RATE_UNLIMITED; 0 or missing -> conservative default
     * (one info log); fractional -> floored, >= 1; sandbox 1.0 -> 1.
     */
    protected function normalizeRate(mixed $raw): int
    {
        if ($raw === null || $raw === '') {
            return $this->fallbackRate();
        }

        $value = (float) $raw;

        if ($value < 0) {
            return self::RATE_UNLIMITED;
        }

        if ($value < 1) {
            return $this->fallbackRate();
        }

        return (int) floor($value);
    }

    /**
     * The conservative fallback rate used when MaxSendRate is 0 or missing.
     * Emits a single info log line (LOCKED scope: at most one info log line).
     */
    protected function fallbackRate(): int
    {
        $default = max(1, (int) config('sendportal-throttle.default_rate', 1));

        Log::info('SES MaxSendRate unavailable; using conservative default send rate.', [
            'key' => $this->throttleKeyHash(),
            'default_rate' => $default,
        ]);

        return $default;
    }

    /**
     * Build the SES sendEmail() payload — matches the vendor SesMailAdapter shape.
     * Per-message tracking is not settable for SES (vendor TODO), so the tracking
     * options are accepted for signature compatibility but not applied.
     */
    protected function buildPayload(string $fromEmail, string $fromName, string $toEmail, string $subject, string $content): array
    {
        return [
            'Source' => $fromName . ' <' . $fromEmail . '>',

            'Destination' => [
                'ToAddresses' => [$toEmail],
            ],

            'Message' => [
                'Subject' => [
                    'Data' => $subject,
                ],
                'Body' => [
                    'Html' => [
                        'Data' => $content,
                    ],
                ],
            ],

            'ConfigurationSetName' => Arr::get($this->config, 'configuration_set_name'),
        ];
    }
}
