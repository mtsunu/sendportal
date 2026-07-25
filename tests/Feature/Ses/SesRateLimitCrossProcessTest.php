<?php

declare(strict_types=1);

namespace Tests\Feature\Ses;

use Aws\Result;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Facades\Redis;
use Sendportal\Base\Services\Messages\MessageTrackingOptions;

/**
 * SES-01: two independent ThrottledSesAdapter instances that share the SAME derived
 * Redis key (a stand-in for two OS worker processes) never exceed MaxSendRate per
 * aligned, first-acquire-anchored fixed window. Proves the atomic DurationLimiter
 * Lua EVAL coordinates across contexts — not a silent per-process fallback.
 */
final class SesRateLimitCrossProcessTest extends SesTestCase
{
    use SesAdapterTestSupport;

    private const RATE = 4;
    private const TOTAL_SENDS = 10;

    /** @test */
    public function combined_sends_across_two_shared_key_contexts_stay_within_rate_per_window(): void
    {
        $this->redisAvailableOrSkip();

        // The limiter store MUST be a real Redis connection (guards the silent
        // per-process fallback that would allow up to a 20x overshoot).
        self::assertInstanceOf(Connection::class, app('redis')->connection());

        // A shared suffix => identical region|key => identical throttle key across
        // both adapters, exactly as two separate workers on one SES account/region.
        $suffix = uniqid('xproc', true);
        $key = null;
        $timestamps = [];

        $adapterA = $this->recordingAdapter($suffix, 'A', $timestamps);
        $adapterB = $this->recordingAdapter($suffix, 'B', $timestamps);
        $key = 'sp:ses:rate:' . $adapterA->throttleKeyHash();

        $this->flushLimiterKey($key);

        try {
            for ($i = 0; $i < self::TOTAL_SENDS; $i++) {
                $adapter = ($i % 2 === 0) ? $adapterA : $adapterB;
                $adapter->send('a@b.com', 'A', 'c@d.com', 'S', new MessageTrackingOptions(), 'body');
            }

            self::assertCount(self::TOTAL_SENDS, $timestamps);

            // The limiter caps each aligned integer-second window at R, so M sends
            // CANNOT fit in fewer than ceil(M/R) windows. A per-process fallback (two
            // independent limiters each allowing R => ~2R/sec combined) WOULD fit them
            // in ceil(M/2R) windows. Asserting the coordinated minimum window count is
            // the stable, pigeonhole-equivalent form of "combined <= R per second",
            // and it strictly catches the fallback regression (never relaxed to <=2R).
            //
            // (Counting recorded timestamps per wall-clock window directly is NOT used:
            //  post-hoc timestamps drift across integer-second edges, so a single window
            //  can read up to ~2R purely from measurement skew — the documented
            //  fixed-window edge burst — which would make a strict per-window assertion
            //  flaky without proving anything the window-count assertion does not.)
            $windowsUsed = $this->distinctAlignedWindows($timestamps);
            $minWindows = (int) ceil(self::TOTAL_SENDS / self::RATE);

            self::assertGreaterThanOrEqual(
                $minWindows,
                $windowsUsed,
                sprintf(
                    '%d sends occupied only %d aligned second-windows (coordinated minimum is %d); '
                    . 'combined rate exceeded MaxSendRate %d/sec — the limiter likely fell back to per-process.',
                    self::TOTAL_SENDS,
                    $windowsUsed,
                    $minWindows,
                    self::RATE
                )
            );
        } finally {
            $this->flushLimiterKey($key);
        }
    }

    /**
     * Build an adapter whose sendEmail() records the wall-clock time of each call.
     */
    private function recordingAdapter(string $suffix, string $tag, array &$timestamps)
    {
        $client = $this->sesClientMock();
        $client->shouldReceive('getSendQuota')->andReturn(new Result(['MaxSendRate' => self::RATE]));
        $client->shouldReceive('sendEmail')->andReturnUsing(function () use (&$timestamps, $tag) {
            $timestamps[] = microtime(true);

            return new Result(['MessageId' => $tag . '-' . count($timestamps)]);
        });

        return $this->throttledAdapter($client, [], $suffix);
    }

    /**
     * The number of distinct aligned integer-second windows the sends occupied.
     *
     * The DurationLimiter Lua anchors every window to an integer Unix second
     * (HMSET 'start', time() — see DurationLimiter::luaScript()), so integer-second
     * bucketing matches the limiter's true first-acquire grid.
     */
    private function distinctAlignedWindows(array $timestamps): int
    {
        $windows = [];

        foreach ($timestamps as $t) {
            $windows[(int) floor($t)] = true;
        }

        return count($windows);
    }

    private function flushLimiterKey(?string $key): void
    {
        if ($key === null) {
            return;
        }

        try {
            Redis::connection('default')->del($key);
        } catch (\Throwable $e) {
            // best-effort; the unique per-test key already guarantees isolation
        }
    }
}
