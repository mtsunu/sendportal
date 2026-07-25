<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Per-attempt Redis block cap (seconds)
    |--------------------------------------------------------------------------
    |
    | The maximum time a single send attempt will block waiting for a rate slot
    | on the shared Redis DurationLimiter. Must stay well below the worker
    | timeout so a blocked slot never triggers a duplicate reservation.
    |
    */
    'max_block_seconds' => (int) env('SENDPORTAL_SES_MAX_BLOCK_SECONDS', 15),

    /*
    |--------------------------------------------------------------------------
    | Aggregate in-send wait budget (seconds)
    |--------------------------------------------------------------------------
    |
    | The single shared wall-clock deadline that caps ALL in-send waiting (every
    | Redis block plus every reactive backoff) combined across every attempt of a
    | single send(). MUST stay below the 60s worker timeout (config/horizon.php);
    | values >= 60 are clamped down at runtime.
    |
    */
    'max_total_wait_seconds' => (int) env('SENDPORTAL_SES_MAX_TOTAL_WAIT_SECONDS', 45),

    /*
    |--------------------------------------------------------------------------
    | MaxSendRate cache TTL (seconds)
    |--------------------------------------------------------------------------
    |
    | How long the account MaxSendRate read from getSendQuota() is cached before a
    | single-flight refresh. ~5 minutes keeps GetSendQuota traffic negligible.
    |
    */
    'rate_cache_ttl' => (int) env('SENDPORTAL_SES_RATE_CACHE_TTL', 300),

    /*
    |--------------------------------------------------------------------------
    | Conservative fallback rate (per second)
    |--------------------------------------------------------------------------
    |
    | Used only when MaxSendRate is 0 or missing. There is deliberately NO config
    | override of the rate VALUE itself — the live rate is authoritative from SES.
    |
    */
    'default_rate' => (int) env('SENDPORTAL_SES_DEFAULT_RATE', 1),

    /*
    |--------------------------------------------------------------------------
    | Redis block sleep (milliseconds)
    |--------------------------------------------------------------------------
    |
    | The DurationLimiter's default 750ms poll is too coarse; 100ms paces closer
    | to the per-second window without busy-waiting.
    |
    */
    'block_sleep_ms' => (int) env('SENDPORTAL_SES_BLOCK_SLEEP_MS', 100),

    /*
    |--------------------------------------------------------------------------
    | Bounded in-send reactive retries
    |--------------------------------------------------------------------------
    |
    | The maximum number of send attempts within a single send() when SES returns a
    | retryable Throttling (rate) error. Kept small so Horizon tries=3 remains the
    | single outer retry owner (no 10x-loop x 3-tries amplification).
    |
    */
    'max_send_attempts' => (int) env('SENDPORTAL_SES_MAX_SEND_ATTEMPTS', 3),

    /*
    |--------------------------------------------------------------------------
    | Reactive backoff between failed rate attempts (milliseconds)
    |--------------------------------------------------------------------------
    |
    | Applied only between FAILED rate-classified sends, never after a success, and
    | always capped by the remaining aggregate wait budget.
    |
    */
    'reactive_backoff_ms' => (int) env('SENDPORTAL_SES_REACTIVE_BACKOFF_MS', 200),

];
