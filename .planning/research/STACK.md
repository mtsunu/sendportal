# Stack Research

**Domain:** Coordinated cross-process rate limiting for SES email sends (Laravel 11 host, PHP 8.4, Horizon/Redis)
**Researched:** 2026-07-25
**Confidence:** HIGH (verified against the installed vendor source, not memory — `laravel/framework` 11.55.0 in the committed lock, running on PHP 8.4.23)

## Scope note

This is a v1.1 focused-fix milestone. The existing stack (Laravel 11, `mettle/sendportal-core` 3.0.2, Horizon on Redis, PHP 8.4) is **not** re-researched. This file answers one question: **which existing primitive should implement the Redis-backed, cross-process, per-second SES send limiter — with ZERO new dependencies and no edits to `vendor/mettle/sendportal-core`.**

The throttle seam is fixed by the codebase: `Sendportal\Base\Adapters\SesMailAdapter::send()` calls `throttleSending()` (from `Sendportal\Base\Traits\ThrottlesSending`) around each `sendEmail()`. Both are vendor-owned. The only host-level override seam is **re-binding the SES mail adapter in the container** (in `app/`) with a subclass that wraps the SES call in a limiter. That means the limiter runs **inline inside an adapter method**, NOT at a Job boundary — a decisive constraint for option selection (see below).

## Recommended Stack

### Core Technologies

| Technology | Version | Purpose | Why Recommended |
|------------|---------|---------|-----------------|
| `Illuminate\Support\Facades\Redis::throttle()` → `Illuminate\Redis\Limiters\DurationLimiter` | Laravel 11.55 (bundled) | Atomic, Redis-Lua, cross-process duration limiter that paces the SES call to N acquisitions per 1-second window | Already installed; genuinely atomic across processes (single `EVAL`); `every(1)` maps exactly to "per second"; can **block** to pace (not just fail); zero new deps; identical engine that Laravel's own `RateLimitedWithRedis` job middleware uses, so it is battle-tested |
| `Illuminate\Support\Facades\Redis` facade | Laravel 11.55 (bundled) | Resolves the shared Redis connection all workers use | Facade → container binding `redis` → `Illuminate\Redis\RedisManager` → `Illuminate\Redis\Connections\PhpRedisConnection`. Same connection Horizon uses (`default`, DB 0), so every worker process coordinates on the same keyspace |
| `Illuminate\Contracts\Redis\LimiterTimeoutException` | Laravel 11.55 (bundled) | Signals "no token acquired within block timeout" | Lets the override decide: bounded wait then either proceed to the existing retry/backoff path or fail cleanly (feeds SES-04) |

### Supporting Libraries

| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| `Illuminate\Support\Facades\Cache` (`Cache::remember`) | bundled | Cache SES `getSendQuota()['MaxSendRate']` ~5 min (SES-02) to feed `allow($rate)` | Already the idiomatic cache seam; use the `redis`/default store |
| Custom Redis Lua token bucket via `Redis::connection()->eval()` | n/a (host code in `app/`) | **Escalation only** — smooth (sub-second, continuous-refill) pacing if the fixed-window boundary burst of `DurationLimiter` proves to trip SES in production | Only adopt if the primary option's window-rollover burst (see caveat) is observed to matter; still zero new deps |

### Development Tools

| Tool | Purpose | Notes |
|------|---------|-------|
| PHPUnit 10 (existing) | Test the limiter override against a real/fake Redis | Assert cross-process semantics by driving the `DurationLimiter` name directly; time-freeze with `Illuminate\Support\Sleep::fake()` and `Carbon::setTestNow()` (the limiter's `block()` uses `Illuminate\Support\Sleep`, which is test-fakeable) |

## Installation

```bash
# NOTHING to install. All primitives ship inside laravel/framework 11.55.0,
# already present in the committed composer.lock and running on PHP 8.4.23.
# Do NOT run composer require for a rate-limiter package.
```

## The four options, compared against the hard requirements

Requirements: (a) coordinate across **separate worker processes** via shared Redis (not in-memory), (b) **per-second** granularity, (c) ability to **block/pace** vs merely signal, (d) whether it must live **inside a Job class**.

| Option | Exact class/facade | Cross-process (shared Redis)? | Per-second? | Blocks or signals? | Requires being inside a Job? | Verdict |
|--------|--------------------|-------------------------------|-------------|--------------------|------------------------------|---------|
| **Redis funnel** ✅ | `Illuminate\Support\Facades\Redis::throttle($name)->allow($n)->every(1)->block($t)->sleep($ms)->then($ok,$fail)` → `Illuminate\Redis\Limiters\DurationLimiter` | **Yes** — one atomic `EVAL` Lua script (`HMSET`/`HINCRBY` on a shared key); all processes on the same connection contend on the same key | **Yes** — `every()` runs through `secondsUntil()`, whole-second window; `every(1)` = per second | **Both** — `block($timeout)` sleeps and retries until a slot frees, throwing `LimiterTimeoutException` on timeout; `then($ok,$fail)` gives an explicit failure callback | **No** — plain method call, works inline inside the adapter override | **RECOMMENDED** |
| Cache RateLimiter | `Illuminate\Support\Facades\RateLimiter` → `Illuminate\Cache\RateLimiter::attempt($key,$max,$cb,$decaySeconds)` | **Conditionally** — only if `cache.default` resolves to a **shared** store (Redis/Memcached/DB). Uses `Cache::add`+`increment`; not a single atomic op, minor boundary races | Whole-second decay (`$decaySeconds`, min 1) | **Signals only** — returns `false` when limited; you'd hand-roll a sleep/retry loop to pace | No | Viable fallback, but strictly worse: depends on cache-store config, not purpose-built for pacing, no built-in block |
| Queue job middleware | `Illuminate\Queue\Middleware\RateLimitedWithRedis` / `RateLimited` / `WithoutOverlapping` / `ThrottlesExceptionsWithRedis` | Yes (WithRedis variant wraps the same `DurationLimiter`) | Per-second via named limiter | **Neither blocks nor paces inline** — on limit it **releases the job back to the queue** with a delay (re-queue), freeing the worker | **YES — must be returned from a queued class's `middleware()` method** | **RULED OUT by constraints** — the queued unit (a Listener) and the throttle point (adapter method) are both in `vendor/mettle/sendportal-core`; attaching middleware needs editing/subclassing that vendor Job. Host-level-override-only forbids it. Also releases-instead-of-paces, which changes dispatch semantics |
| Custom Lua token bucket | Host code in `app/` calling `Redis::connection('default')->eval($lua, ...)` | Yes — atomic `EVAL` on a shared key | **Yes, and sub-second/continuous-refill** — smoother than fixed window | Both (you write the wait loop) | No | Best precision, but more code to own/test. Keep as documented escalation, not the default |

### Why the recommendation

`Redis::throttle()` (the `DurationLimiter`) is the correct default because it satisfies every hard requirement with **no new dependency and no vendor edit**, and it is the *same* atomic Lua engine Laravel ships behind `RateLimitedWithRedis` — we simply invoke it inline (where the job-middleware form cannot reach) instead of at a Job boundary.

Concrete override shape (host code in `app/`, `declare(strict_types=1)`, native types):

```php
// Inside a host SesMailAdapter subclass bound over the vendor adapter.
$rate = (int) Cache::remember(
    "ses:maxsendrate:{$accountKey}", now()->addMinutes(5),
    fn (): int => (int) floor($this->getSendQuota()['MaxSendRate']),
);

return Redis::throttle("ses:send:{$accountKey}")
    ->allow(max(1, $rate))
    ->every(1)            // whole-second window == per-second pacing
    ->block(10)           // wait up to 10s for a slot
    ->sleep(100)          // retry every 100ms (default 750ms is too coarse — see caveat)
    ->then(
        fn () => $sendEmailClosure(),
        function (): void { throw new /* clear */ ThrottleTimeoutException(); }, // feeds SES-04
    );
```

Key `ses:send:{$accountKey}` must be scoped to the **SES account** (credentials+region), since `MaxSendRate` is per-account — different workspaces on different SES accounts get independent buckets.

## Redis client / config that Horizon implies is available

- `config/database.php` sets `'client' => env('REDIS_CLIENT', 'phpredis')`. In the committed `composer.lock`, `predis/predis` appears **only** as a `suggest`/`require-dev` of framework and Horizon — it is **not an installed runtime dependency**. Therefore the available client is **phpredis (ext-redis)**, which Horizon needs anyway.
- Both the recommended `DurationLimiter` and the escalation token-bucket run over `Illuminate\Support\Facades\Redis` → `RedisManager` → **`PhpRedisConnection`**, so they are client-agnostic and work identically under phpredis.
- Use the **`default` connection (DB 0)** — the one Horizon coordinates on — so limiter keys live in the same shared instance every worker already talks to. (The `cache` connection is DB 1; only use it if you deliberately want isolation.)
- **PHP 8.4:** all classes are core framework 11.55.0, already exercised on PHP 8.4.23 by the live `:8.4` CI (per PROJECT.md). Facade resolution is standard container lookup — no PHP 8.4-specific concerns. `Redis` facade → binding `redis`; `RateLimiter` facade → singleton `Illuminate\Cache\RateLimiter`.

## Alternatives Considered

| Recommended | Alternative | When to Use Alternative |
|-------------|-------------|-------------------------|
| `Redis::throttle()` DurationLimiter | Custom Redis Lua token bucket | Only if the fixed-window boundary burst (see caveat) is observed to exceed SES `MaxSendRate` in production and smooth continuous-refill pacing is required |
| `Redis::throttle()` DurationLimiter | `RateLimiter` facade (cache-based) | Only if you cannot use the Redis connection directly AND `cache.default` is a confirmed shared Redis store — but you lose built-in blocking and atomicity |
| `Redis::throttle()` DurationLimiter | `RateLimitedWithRedis` job middleware | Only in a hypothetical world where the throttle point were a **host-owned queued Job** — it is not here (vendor Listener + vendor adapter), so this is unavailable under host-level-override-only |

## What NOT to Use

| Avoid | Why | Use Instead |
|-------|-----|-------------|
| Any new rate-limit Composer package (`symfony/rate-limiter`, `nikolaposa/rate-limit`, `spatie/laravel-rate-limited-job-middleware`, `graham-campbell/throttle`) | Redundant — `illuminate/redis` already provides an atomic cross-process limiter. New deps risk churning the carefully PHP-8.4-pinned lock (documented Symfony-8.1-floor tech debt) and violate "avoid new heavy deps" | `Redis::throttle()` |
| `predis/predis` | Not installed; phpredis is the configured/available client | phpredis via `Illuminate\Support\Facades\Redis` |
| In-memory / per-process limiters (array cache, APCu, static counters, `usleep` alone) | Do **not** coordinate across the ≤20 separate Horizon worker processes — each process would allow N/sec independently, giving up to 20×N total | Shared-Redis `DurationLimiter` |
| `Cache::lock()` / `WithoutOverlapping` middleware as a "rate limiter" | These are **mutual exclusion** (one-at-a-time), not rate control — they do not enforce N-per-second | `Redis::throttle()->allow(N)->every(1)` |
| Editing `vendor/mettle/sendportal-core` (adapter or `ThrottlesSending`) | Violates host-level-override-only; lost on `composer install` | Re-bind the SES adapter in `app/` and override `send()`/throttle wrapper |
| Composer platform-check bypass / `--ignore-platform-reqs` | Explicit project constraint; conceals compatibility defects | Keep the pinned lock; add only host PHP code |

## Version Compatibility

| Package A | Compatible With | Notes |
|-----------|-----------------|-------|
| `laravel/framework` 11.55.0 | PHP 8.4.23 | Verified by committed lock + live `:8.4` CI; all limiter classes present under `vendor/laravel/framework/src/Illuminate/{Redis/Limiters,Cache}` |
| `DurationLimiter` (Lua `EVAL`) | phpredis (ext-redis) | Runs over `PhpRedisConnection`; single-key script, no Redis-cluster cross-slot concern for one bucket key |
| `laravel/horizon` (installed) | Redis DB 0 `default` | Limiter should share this connection; requires ext-redis/ext-pcntl/ext-posix already implied by Horizon |

## Caveats the planner must carry forward

1. **Fixed-window, not smooth token bucket.** `DurationLimiter` resets the count each `every(N)` window. With `every(1)`, up to `MaxSendRate` sends can fire near the end of window A and another full `MaxSendRate` at the start of window B — a transient ~2N burst across a sub-second boundary. For SES this is usually absorbed by SES's own per-second enforcement plus the existing retry/backoff (which SES-03/SES-04 harden). If observed to trip SES, escalate to the custom Lua token bucket. This is the single most important design trade-off for the roadmap.
2. **`block()` default sleep is 750ms** — too coarse for a 1s window; set `->sleep(100)` (or 50–150ms) so blocked senders re-check promptly instead of idling most of a second. `block()` sleeps via `Illuminate\Support\Sleep`, so it is test-fakeable.
3. **`block()` timeout throws `LimiterTimeoutException`.** Decide deliberately: on timeout, either fall through to the existing (soon-fixed) retry path or throw a clear exception — do **not** return `null` (that reproduces the SES-04 `TypeError`).
4. **Stable, account-scoped key.** All processes must use the identical throttle name for a given SES account or coordination silently splits. Derive it from SES credentials+region, not per-request state.

## Sources

- `vendor/laravel/framework/src/Illuminate/Redis/Limiters/DurationLimiter.php` and `DurationLimiterBuilder.php` (installed 11.55.0) — verified `allow/every/block/sleep/then`, `secondsUntil` (whole-second window), atomic Lua `EVAL`, `LimiterTimeoutException`, 750ms default sleep — HIGH
- `vendor/laravel/framework/src/Illuminate/Cache/RateLimiter.php` (installed) — verified `attempt($key,$max,$cb,$decaySeconds=60)` cache-store based, signals-not-blocks — HIGH
- `vendor/laravel/framework/src/Illuminate/Queue/Middleware/{RateLimited,RateLimitedWithRedis,WithoutOverlapping,ThrottlesExceptionsWithRedis}.php` (installed) — confirm job-boundary-only, release-not-pace — HIGH
- `config/database.php`, `config/horizon.php` (repo) — `REDIS_CLIENT=phpredis` default; supervisor-2 `sendportal-message-dispatch` `maxProcesses=20`, `tries=3`; Horizon on `default` connection — HIGH
- `composer.lock` (repo) — `predis/predis` only in suggest/require-dev → phpredis is the runtime client — HIGH
- `vendor/mettle/sendportal-core/src/Adapters/SesMailAdapter.php`, `src/Traits/ThrottlesSending.php` (repo) — throttle seam is an inline adapter method, not a Job; confirms job-middleware options are unreachable under host-level-override-only — HIGH

---
*Stack research for: coordinated cross-process SES send rate limiting (v1.1)*
*Researched: 2026-07-25*
