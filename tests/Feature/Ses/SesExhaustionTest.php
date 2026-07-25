<?php

declare(strict_types=1);

namespace Tests\Feature\Ses;

use App\Mail\SesSendThrottledException;
use Aws\Result;
use Sendportal\Base\Services\Messages\MessageTrackingOptions;

/**
 * SES-04 (BUG 2) + SES-05 aggregate bound: a rate error is retried to success
 * within a bounded loop; exhaustion throws a named SesSendThrottledException (never
 * null, never a TypeError); the message is never marked sent; and the aggregate
 * in-send wait is capped by ONE shared wall-clock deadline < the 60s worker timeout,
 * independent of max_send_attempts / max_block_seconds.
 *
 * All cases use rate = -1 (limiter bypassed, Redis-free) so the retry loop itself is
 * under test, deterministically and without Redis.
 */
final class SesExhaustionTest extends SesTestCase
{
    use SesAdapterTestSupport;

    private function send($adapter): string
    {
        return $adapter->send('a@b.com', 'A', 'c@d.com', 'S', new MessageTrackingOptions(), 'body');
    }

    /** @test */
    public function a_rate_error_is_retried_to_success_within_the_bounded_loop(): void
    {
        $calls = 0;
        $ex = $this->rateExceededException();

        $client = $this->sesClientMock();
        $client->shouldReceive('getSendQuota')->andReturn(new Result(['MaxSendRate' => -1]));
        $client->shouldReceive('sendEmail')->andReturnUsing(function () use (&$calls, $ex) {
            $calls++;
            if ($calls < 2) {
                throw $ex;
            }

            return new Result(['MessageId' => 'recovered-id']);
        });

        $adapter = $this->throttledAdapter($client, [], uniqid('retry', true));

        self::assertSame('recovered-id', $this->send($adapter));
        self::assertSame(2, $calls, 'sendEmail should be called exactly N times (N-1 failures + 1 success).');
    }

    /** @test */
    public function exhaustion_throws_named_exception_after_exactly_max_send_attempts(): void
    {
        $maxAttempts = (int) config('sendportal-throttle.max_send_attempts');
        $calls = 0;
        $ex = $this->rateExceededException();

        $client = $this->sesClientMock();
        $client->shouldReceive('getSendQuota')->andReturn(new Result(['MaxSendRate' => -1]));
        $client->shouldReceive('sendEmail')->andReturnUsing(function () use (&$calls, $ex) {
            $calls++;
            throw $ex;
        });

        $adapter = $this->throttledAdapter($client, [], uniqid('exhaust', true));

        try {
            $this->send($adapter);
            self::fail('Expected SesSendThrottledException on exhaustion.');
        } catch (SesSendThrottledException $e) {
            self::assertNotSame('', $e->getMessage(), 'Exhaustion exception must carry a clear message.');
        }

        self::assertSame($maxAttempts, $calls, 'sendEmail should be called exactly max_send_attempts times.');
    }

    /** @test */
    public function the_message_is_never_marked_sent_when_send_throws(): void
    {
        $ex = $this->rateExceededException();

        $client = $this->sesClientMock();
        $client->shouldReceive('getSendQuota')->andReturn(new Result(['MaxSendRate' => -1]));
        $client->shouldReceive('sendEmail')->andThrow($ex);

        $adapter = $this->throttledAdapter($client, [], uniqid('nomark', true));

        // Mirror DispatchMessage::handle() ordering: send() then markSent().
        $marked = false;
        try {
            $id = $this->send($adapter);
            $marked = true; // stands in for markSent($message, $id) — must be unreachable
            unset($id);
        } catch (SesSendThrottledException $e) {
            // expected
        }

        self::assertFalse($marked, 'markSent() must be unreachable because send() threw before returning.');
    }

    /** @test */
    public function aggregate_in_send_wait_is_bounded_by_one_shared_deadline_independent_of_attempts(): void
    {
        // Raise attempts high and backoff coarse, but budget stays small: the single
        // shared deadline — not max_send_attempts / max_block_seconds — caps the wait.
        config([
            'sendportal-throttle.max_total_wait_seconds' => 1,
            'sendportal-throttle.max_send_attempts' => 100,
            'sendportal-throttle.reactive_backoff_ms' => 200,
        ]);

        $ex = $this->rateExceededException();
        $client = $this->sesClientMock();
        $client->shouldReceive('getSendQuota')->andReturn(new Result(['MaxSendRate' => -1]));
        $client->shouldReceive('sendEmail')->andThrow($ex);

        $adapter = $this->throttledAdapter($client, [], uniqid('budget', true));

        $start = microtime(true);
        try {
            $this->send($adapter);
            self::fail('Expected SesSendThrottledException.');
        } catch (SesSendThrottledException $e) {
            // expected
        }
        $elapsed = microtime(true) - $start;

        self::assertLessThan(2.0, $elapsed, 'Aggregate in-send wait must stay near the 1s budget, not scale with 100 attempts.');
    }
}
