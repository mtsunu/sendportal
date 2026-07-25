<?php

declare(strict_types=1);

namespace Tests\Feature\Ses;

use App\Mail\ThrottledSesAdapter;
use Aws\Result;
use Aws\Ses\Exception\SesException;
use Sendportal\Base\Services\Messages\MessageTrackingOptions;

/**
 * SES-03 (BUG 1): throttle classification is code-gated first
 * (getAwsErrorCode() === 'Throttling'), then message-sub-branched on
 * getAwsErrorMessage() — never the brittle exact-string equality the vendor trait
 * uses on getMessage(). Classification only; retry-to-success lives in Task 4.
 */
final class SesThrottleDetectionTest extends SesTestCase
{
    use SesAdapterTestSupport;

    private function classifier(): ThrottledSesAdapter
    {
        // No sends here — a bare client is enough to reach the pure classifier.
        return $this->throttledAdapter($this->sesClientMock(), [], uniqid('cls', true));
    }

    /** @test */
    public function throttling_rate_exceeded_is_classified_rate_retryable(): void
    {
        self::assertSame(
            ThrottledSesAdapter::CLASSIFY_RATE,
            $this->classifier()->classifyThrottleException($this->rateExceededException())
        );
    }

    /** @test */
    public function throttling_daily_quota_is_classified_fail_fast(): void
    {
        self::assertSame(
            ThrottledSesAdapter::CLASSIFY_DAILY_QUOTA,
            $this->classifier()->classifyThrottleException($this->dailyQuotaException())
        );
    }

    /** @test */
    public function non_throttling_exception_is_classified_propagate(): void
    {
        self::assertSame(
            ThrottledSesAdapter::CLASSIFY_PROPAGATE,
            $this->classifier()->classifyThrottleException($this->sesException('Email address is not verified.', 'MessageRejected'))
        );
    }

    /** @test */
    public function daily_quota_fails_fast_through_send_with_a_single_send_email_call(): void
    {
        // rate = -1 bypasses the limiter (Redis-free); daily-quota must not retry.
        $client = $this->sesClientMock();
        $client->shouldReceive('getSendQuota')->andReturn(new Result(['MaxSendRate' => -1]));
        $client->shouldReceive('sendEmail')->once()->andThrow($this->dailyQuotaException());

        $adapter = $this->throttledAdapter($client, [], uniqid('daily', true));

        $this->expectException(SesException::class);

        $adapter->send('a@b.com', 'A', 'c@d.com', 'S', new MessageTrackingOptions(), 'body');
    }

    /** @test */
    public function non_throttling_exception_propagates_unchanged_through_send(): void
    {
        $original = $this->sesException('Email address is not verified.', 'MessageRejected');

        $client = $this->sesClientMock();
        $client->shouldReceive('getSendQuota')->andReturn(new Result(['MaxSendRate' => -1]));
        $client->shouldReceive('sendEmail')->once()->andThrow($original);

        $adapter = $this->throttledAdapter($client, [], uniqid('reject', true));

        try {
            $adapter->send('a@b.com', 'A', 'c@d.com', 'S', new MessageTrackingOptions(), 'body');
            self::fail('Expected the non-Throttling SesException to propagate.');
        } catch (SesException $e) {
            self::assertSame($original, $e, 'The original SesException must propagate unchanged (no wrapping).');
        }
    }
}
