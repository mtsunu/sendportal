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

        if ($rate === self::RATE_UNLIMITED) {
            return $this->resolveMessageId($this->resolveClient()->sendEmail($payload));
        }

        return $this->paceAndSend($rate, $payload);
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
     * Pace one send through the shared Redis DurationLimiter, then issue the SES
     * call inside the acquired 1-second window and resolve the message id.
     */
    protected function paceAndSend(int $rate, array $payload): string
    {
        $key = 'sp:ses:rate:' . $this->throttleKeyHash();

        return Redis::throttle($key)
            ->allow($rate)
            ->every(1)
            ->block((int) config('sendportal-throttle.max_block_seconds', 15))
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
