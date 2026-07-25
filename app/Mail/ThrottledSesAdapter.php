<?php

declare(strict_types=1);

namespace App\Mail;

use Aws\Ses\SesClient;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
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

        return $this->paceAndSend($rate, $payload);
    }

    /**
     * Resolve the per-second send rate from the (cached) account MaxSendRate.
     */
    public function resolveSendRate(): int
    {
        $ttl = (int) config('sendportal-throttle.rate_cache_ttl', 300);

        return (int) Cache::remember(
            'sp:ses:maxrate:' . $this->throttleKeyHash(),
            $ttl,
            fn (): int => $this->normalizeRate($this->readMaxSendRate())
        );
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
     * Read the raw MaxSendRate value from getSendQuota() (null if absent).
     */
    protected function readMaxSendRate(): mixed
    {
        return $this->getSendQuota()['MaxSendRate'] ?? null;
    }

    /**
     * Normalize a raw MaxSendRate to a usable integer per-second rate.
     */
    protected function normalizeRate(mixed $raw): int
    {
        return max(1, (int) floor((float) $raw));
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
