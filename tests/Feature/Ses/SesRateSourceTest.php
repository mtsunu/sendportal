<?php

declare(strict_types=1);

namespace Tests\Feature\Ses;

use App\Mail\ThrottledSesAdapter;
use Aws\Result;
use Illuminate\Support\Facades\Cache;
use Sendportal\Base\Services\Messages\MessageTrackingOptions;

/**
 * SES-02: MaxSendRate is read from getSendQuota()['MaxSendRate'], normalized for
 * edge values, cached ~5 min, and refreshed single-flight (last-known-good on a
 * held lock) so 20 workers never stampede GetSendQuota.
 */
final class SesRateSourceTest extends SesTestCase
{
    use SesAdapterTestSupport;

    private function adapterWithQuota(float|int|null $maxSendRate, string $suffix): ThrottledSesAdapter
    {
        $client = $this->sesClientMock();
        $client->shouldReceive('getSendQuota')
            ->andReturn(new Result($maxSendRate === null ? [] : ['MaxSendRate' => $maxSendRate]));

        return $this->throttledAdapter($client, [], $suffix);
    }

    /** @test */
    public function unlimited_rate_resolves_to_the_unlimited_sentinel(): void
    {
        $adapter = $this->adapterWithQuota(-1, uniqid('unl', true));

        self::assertSame(ThrottledSesAdapter::RATE_UNLIMITED, $adapter->resolveSendRate());
    }

    /** @test */
    public function unlimited_rate_bypasses_the_limiter_and_still_sends(): void
    {
        // With rate -1 the limiter is skipped entirely. If it were NOT skipped,
        // allow(-1) would never grant a slot and send() would throw — so a
        // successful send here proves the bypass (and needs no Redis).
        $client = $this->sesClientMock();
        $client->shouldReceive('getSendQuota')->andReturn(new Result(['MaxSendRate' => -1]));
        $client->shouldReceive('sendEmail')->once()->andReturn(new Result(['MessageId' => 'unlimited-id']));

        $adapter = $this->throttledAdapter($client, [], uniqid('unlsend', true));

        $id = $adapter->send('a@b.com', 'A', 'c@d.com', 'S', new MessageTrackingOptions(), 'body');

        self::assertSame('unlimited-id', $id);
    }

    /** @test */
    public function zero_rate_falls_back_to_the_conservative_default(): void
    {
        $adapter = $this->adapterWithQuota(0, uniqid('zero', true));

        self::assertSame((int) config('sendportal-throttle.default_rate'), $adapter->resolveSendRate());
    }

    /** @test */
    public function missing_rate_falls_back_to_the_conservative_default(): void
    {
        $adapter = $this->adapterWithQuota(null, uniqid('missing', true));

        self::assertSame((int) config('sendportal-throttle.default_rate'), $adapter->resolveSendRate());
    }

    /** @test */
    public function fractional_rate_is_floored_to_an_integer(): void
    {
        $adapter = $this->adapterWithQuota(13.9, uniqid('frac', true));

        self::assertSame(13, $adapter->resolveSendRate());
    }

    /** @test */
    public function sandbox_rate_of_one_resolves_to_one(): void
    {
        $adapter = $this->adapterWithQuota(1.0, uniqid('sandbox', true));

        self::assertSame(1, $adapter->resolveSendRate());
    }

    /** @test */
    public function get_send_quota_is_called_once_across_two_cached_sends(): void
    {
        $this->redisAvailableOrSkip();

        $client = $this->sesClientMock();
        $client->shouldReceive('getSendQuota')->once()->andReturn(new Result(['MaxSendRate' => 10]));
        $client->shouldReceive('sendEmail')->twice()->andReturn(new Result(['MessageId' => 'cached-id']));

        $adapter = $this->throttledAdapter($client, [], uniqid('cache', true));

        $adapter->send('a@b.com', 'A', 'c@d.com', 'S', new MessageTrackingOptions(), 'body');
        $adapter->send('a@b.com', 'A', 'c@d.com', 'S', new MessageTrackingOptions(), 'body');

        // Mockery ->once() on getSendQuota is asserted on tearDown.
        self::assertTrue(true);
    }

    /** @test */
    public function a_held_single_flight_lock_returns_last_known_good_without_a_second_quota_call(): void
    {
        $client = $this->sesClientMock();
        $client->shouldReceive('getSendQuota')->once()->andReturn(new Result(['MaxSendRate' => 7]));

        $adapter = $this->throttledAdapter($client, [], uniqid('flight', true));

        // Prime the cache + last-known-good (one getSendQuota call).
        self::assertSame(7, $adapter->resolveSendRate());

        $hash = $adapter->throttleKeyHash();

        // Force the refresh path, then hold the single-flight lock externally.
        Cache::forget('sp:ses:maxrate:' . $hash);
        self::assertTrue(Cache::lock('sp:ses:maxrate:' . $hash . ':lock', 10)->get());

        // Lock is held: resolveSendRate() must return the stale value, not call getSendQuota again.
        self::assertSame(7, $adapter->resolveSendRate());
    }
}
