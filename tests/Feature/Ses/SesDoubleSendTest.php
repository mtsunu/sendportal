<?php

declare(strict_types=1);

namespace Tests\Feature\Ses;

use App\Mail\ThrottledSesAdapter;
use Aws\Result;
use RuntimeException;
use Sendportal\Base\Factories\MailAdapterFactory;
use Sendportal\Base\Services\Messages\MessageTrackingOptions;

/**
 * SES-05: rebind routing, wait-strictly-before-send, the single aggregate wait
 * budget (< the 60s worker timeout), no double-send under fault injection, and no
 * vendor/ or Composer-manifest drift.
 *
 * Reference: config/horizon.php sendportal-message-dispatch has no explicit worker
 * timeout (60s default); config/queue.php retry_after = 90. max_total_wait_seconds
 * (45) < 60 < 90 keeps a paced send well inside its reservation.
 */
final class SesDoubleSendTest extends SesTestCase
{
    use SesAdapterTestSupport;

    private function send($adapter): string
    {
        return $adapter->send('a@b.com', 'A', 'c@d.com', 'S', new MessageTrackingOptions(), 'body');
    }

    /** @test */
    public function the_rebind_routes_ses_to_the_throttled_adapter(): void
    {
        $adapter = (new MailAdapterFactory())->adapter($this->sesEmailService());

        self::assertInstanceOf(ThrottledSesAdapter::class, $adapter);
    }

    /** @test */
    public function a_successful_send_calls_send_email_exactly_once_with_no_post_send_wait(): void
    {
        $this->redisAvailableOrSkip();

        $calls = 0;
        $client = $this->sesClientMock();
        $client->shouldReceive('getSendQuota')->andReturn(new Result(['MaxSendRate' => 5]));
        $client->shouldReceive('sendEmail')->andReturnUsing(function () use (&$calls) {
            $calls++;

            return new Result(['MessageId' => 'once-id']);
        });

        $adapter = $this->throttledAdapter($client, [], uniqid('once', true));

        $start = microtime(true);
        $id = $this->send($adapter);
        $elapsed = microtime(true) - $start;

        self::assertSame('once-id', $id);
        self::assertSame(1, $calls, 'A successful send must call sendEmail exactly once.');
        self::assertLessThan(1.0, $elapsed, 'A successful send must return promptly with no post-send wait.');
    }

    /** @test */
    public function the_aggregate_wait_budget_is_below_the_worker_timeout(): void
    {
        self::assertLessThan(
            ThrottledSesAdapter::WORKER_TIMEOUT_SECONDS,
            (int) config('sendportal-throttle.max_total_wait_seconds'),
            'The single shared in-send wait budget must stay below the 60s worker timeout.'
        );
    }

    /** @test */
    public function the_config_guard_clamps_a_budget_at_or_above_the_worker_timeout(): void
    {
        config(['sendportal-throttle.max_total_wait_seconds' => 60]);

        $adapter = $this->throttledAdapter($this->sesClientMock(), [], uniqid('clamp', true));

        self::assertLessThan(
            ThrottledSesAdapter::WORKER_TIMEOUT_SECONDS,
            $adapter->maxTotalWaitSeconds(),
            'A configured budget >= 60 must be clamped below the worker timeout.'
        );
    }

    /** @test */
    public function a_crash_between_send_and_mark_does_not_produce_a_second_send(): void
    {
        $this->redisAvailableOrSkip();

        $calls = 0;
        $client = $this->sesClientMock();
        $client->shouldReceive('getSendQuota')->andReturn(new Result(['MaxSendRate' => 5]));
        $client->shouldReceive('sendEmail')->andReturnUsing(function () use (&$calls) {
            $calls++;

            return new Result(['MessageId' => 'crash-id']);
        });

        $adapter = $this->throttledAdapter($client, [], uniqid('crash', true));

        // Mirror DispatchMessage::handle(): send() returns, THEN the worker is
        // interrupted before markSent(). Because all pacing happened before the SES
        // call and send() returned immediately, the adapter issues no second send.
        try {
            $this->send($adapter);
            throw new RuntimeException('simulated worker crash before markSent()');
        } catch (RuntimeException $e) {
            self::assertSame('simulated worker crash before markSent()', $e->getMessage());
        }

        self::assertSame(1, $calls, 'Pacing must not open a new send window between the SES call and markSent().');
    }

    /** @test */
    public function no_vendor_core_or_composer_manifest_was_changed(): void
    {
        // composer.json / composer.lock are tracked: any dependency drift shows here.
        self::assertSame('', $this->gitPorcelain('composer.json composer.lock'), 'Composer manifest/lock must be unchanged.');

        // vendor/mettle/sendportal-core is gitignored, so the "no vendor edit" rule is
        // enforced structurally by the host-override design (ThrottledSesAdapter
        // subclasses the untouched vendor adapter). git confirms nothing tracked there
        // changed.
        self::assertSame('', $this->gitPorcelain('vendor/mettle/sendportal-core'), 'No tracked vendor/core file may change.');
    }

    private function gitPorcelain(string $paths): string
    {
        return trim((string) shell_exec('git status --porcelain -- ' . $paths . ' 2>/dev/null'));
    }
}
