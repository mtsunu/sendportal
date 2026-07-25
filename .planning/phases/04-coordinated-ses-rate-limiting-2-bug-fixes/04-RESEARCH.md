---
phase: 4
slug: coordinated-ses-rate-limiting-2-bug-fixes
title: Coordinated SES rate limiting + 2 bug fixes
date: 2026-07-25
source: consolidated from milestone v1.1 research (.planning/research/{STACK,FEATURES,ARCHITECTURE,PITFALLS,SUMMARY}.md)
confidence: HIGH (verified against installed vendor/framework source + AWS docs)
---

# Phase 4 Research: Coordinated SES rate limiting + 2 bug fixes

This consolidates the milestone-level research (four parallel dimensions + synthesis)
into the phase brief the planner consumes. Every load-bearing claim was verified
against the installed `vendor/` source (SendPortal Core, `laravel/framework` 11.55.0)
and AWS SES documentation.

## Goal restated

Pace SES campaign sends proactively to the account's per-second `MaxSendRate`,
coordinated (Redis-backed, genuinely cross-process) across all Horizon
`sendportal-message-dispatch` workers (≤20), and fix two latent throttle-path bugs —
throttle-code misclassification and a `null`-return `TypeError` on retry exhaustion —
entirely via a host-level adapter override with **no `vendor/` edits** and **no new
Composer dependency**.

## Verified send path (do not re-derive)

`MessageDispatchHandler` (queued **listener**, `implements ShouldQueue`, queue
`sendportal-message-dispatch`, Horizon `supervisor-2` `maxProcesses=20`, `tries=3`)
→ `Services/Messages/DispatchMessage::handle` (does `send()` then `markSent()`, sole
idempotency guard = `sent_at`)
→ `RelayMessage::handle`
→ `MailAdapterFactory::adapter(EmailService)` — `MailAdapterFactory::$adapterMap` is a
**public static** array; `resolve()` does `new $adapterClass($emailService->settings)`
(direct instantiation, **no container DI** — adapter must use facades/`app()`)
→ `SesMailAdapter::send()` which wraps `SesClient::sendEmail()` in the
`Traits/ThrottlesSending::throttleSending()` trait (this is where both bugs live).

## Locked technical decisions (design contract — do not re-litigate)

1. **Host-level override, no vendor edits.** New class `app/Mail/ThrottledSesAdapter`
   `extends Sendportal\Base\Adapters\SesMailAdapter`, overriding `send()` **wholesale**.
   It MUST NOT call `parent::send()` — the parent re-enters the buggy `ThrottlesSending`
   trait. Reuse only the safe inherited protected helpers: `resolveClient()`,
   `resolveMessageId()`, `getSendQuota()`, and `$this->config`.
2. **Rebind** in `AppServiceProvider::boot()`:
   `MailAdapterFactory::$adapterMap[EmailServiceType::SES] = ThrottledSesAdapter::class;`
   Boot-safe because the static map is read lazily at dispatch time (no provider-boot-order
   dependency).
3. **Limiter (SES-01):** `Illuminate\Support\Facades\Redis::throttle($key)`
   `->allow($rate)->every(1)->block($cap)->then($ok, $onFail)`, resolving to Laravel's
   `Illuminate\Redis\Limiters\DurationLimiter` — a single atomic Redis-Lua `EVAL`, so it
   genuinely coordinates across the ≤20 separate worker processes on a shared key.
   `every(1)` = per second. Use Horizon's `default` Redis connection (shared keyspace).
   Set `->sleep(100)` (default 750ms is too coarse). Client is **phpredis** (confirmed
   `REDIS_CLIENT` default; predis is suggest/dev-only, not installed).
   - `$key = md5(region|access-key-id)` from adapter `settings` (the `email_service` id is
     NOT passed to the adapter, and `MaxSendRate` is an account-per-region quota — this
     keying is both feasible and more correct than per-service).
   - `$cap = config('sendportal-throttle.max_block_seconds', 15)`. On block timeout the
     builder throws `LimiterTimeoutException` — treat as overflow (see SES-04 path), never
     return `null`.
4. **Rate source (SES-02):**
   `Cache::remember("sp:ses:maxrate:{$key}", 300, fn() => getSendQuota()['MaxSendRate'])`,
   inside `send()`. Handle edge values: `-1` (unlimited → skip limiter entirely / very high
   allow), `0`/missing (fall back to a safe conservative default, e.g. 1, and log once),
   fractional (`floor` to int ≥ 1), sandbox `1.0`. Single-flight the refresh with
   `Cache::lock` + stale-while-revalidate + last-known-good fallback to avoid a
   GetSendQuota stampede when 20 workers hit expiry together.
5. **SES-03 throttle detection (BUG 1 fix, with the critical subtlety):** detect via
   `$e->getAwsErrorCode() === 'Throttling'` (on `Aws\Ses\Exception\SesException`) instead
   of the brittle exact-string `== 'Maximum sending rate exceeded.'`. **BUT** AWS returns
   the *same* `Throttling` code for BOTH "Maximum sending rate exceeded." AND "Daily
   message quota exceeded." — so the fix MUST sub-branch on the message: **retry** the
   rate case; **fail-fast** (throw, do not retry) the daily-quota case. A naive
   code-only check is a latent regression (would retry a daily-quota exhaustion forever).
   Any non-`Throttling` `SesException` is rethrown immediately (unchanged behavior).
6. **SES-04 exhaustion (BUG 2 fix):** when local retries/block are exhausted, throw a
   **named** `app/Mail/SesSendThrottledException extends RuntimeException` — never fall
   off the end returning `null` (which currently reaches `resolveMessageId(Result $result)`
   → `TypeError` against `send(): string`). Make **Horizon `tries=3` the single retry
   owner**: keep the in-`send()` retry/block loop minimal so we don't get
   `10×loop × 3 tries = 30` attempts.
7. **SES-05 no-double-send invariant:** ALL waiting (the `block()`) happens **before** the
   `sendEmail()` call, NEVER between `send()` and `markSent()`. Bounded block
   `max_block_seconds=15` is provably `< worker timeout 60s < redis retry_after 90s`
   (verified `config/queue.php` / `config/horizon.php`), so a blocked slot never triggers
   a duplicate reservation. Idempotency guard remains `sent_at`; a new Redis idempotency
   marker is deferred (SES-07) unless the fault-injection test proves the invariant
   insufficient.

## New / changed host files (build order — internal to this phase)

1. `config/sendportal-throttle.php` (new) — `max_block_seconds=15`, `rate_cache_ttl=300`,
   conservative default rate, block-sleep-ms.
2. `app/Mail/SesSendThrottledException.php` (new) — `extends RuntimeException`.
3. `app/Mail/ThrottledSesAdapter.php` (new) — wholesale `send()` override: rate read
   (SES-02), `Redis::throttle(...)->block(15)->then(...)` pacing (SES-01/05),
   `Throttling`-code-gated + message-sub-branched detection (SES-03), named exhaustion
   exception (SES-04).
4. `app/Providers/AppServiceProvider.php` (modify) — one-line static-map rebind in `boot()`.
5. Tests under `tests/` (see Validation Architecture).

Do NOT touch: `vendor/mettle/sendportal-core/**`, `config/horizon.php` topology,
`QuotaService` daily pre-check, Composer manifest/lock.

## Key pitfalls (with prevention → owning build step)

- **Bug-1 latent regression** (daily-quota shares the `Throttling` code): sub-branch on
  message; fail-fast the quota case. → step 3, asserted by a dedicated test.
- **Double-send** (send→mark gap widened by pacing latency; kill/OOM/DB-throw + `tries=3`
  re-sends): wait strictly before send; bounded block ≪ timeout; keep `timeout <
  retry_after`. → step 3, fault-injection test.
- **Per-process limiter overshoot** (Laravel `RateLimiter`/`Cache` silently falls back to
  per-process → up to 20× overshoot): use the atomic Redis `DurationLimiter` on a shared
  connection; assert the store is Redis; multi-process (≥2 worker) test. → step 3, test.
- **Fixed-window edge burst** (`DurationLimiter` is fixed-window → transient ~2× at second
  boundaries): acceptable with reactive backoff as safety net; escalate to a custom Lua
  token bucket (SES-06, deferred) ONLY if SES actually trips. → documented, not built now.
- **GetSendQuota cache stampede** at ~5-min expiry under 20 workers (and SES throttling
  GetSendQuota itself): single-flight `Cache::lock` + stale-while-revalidate + last-known
  fallback. → step 1.
- **Thundering herd** when blocked workers wake together: `DurationLimiter.block()` sleep
  pacing + Horizon `balance=auto` scale-down; add jitter if needed. → step 2/3.
- **Double retry layers** (vendor 10×loop × Horizon `tries=3`): minimal in-`send()` loop;
  Horizon is sole retry owner. → step 3.

## Validation Architecture

The success criteria are behavioral and must be proven by tests, not just unit-level asserts:

- **Cross-process pacing (SES-01):** an integration test that drives the limiter from ≥2
  concurrent contexts against a shared Redis and asserts combined `sendEmail` invocations
  never exceed `MaxSendRate`/sec — proving the shared-key coordination, not per-process.
  Also assert the resolved limiter store/connection is Redis (guards the silent
  per-process fallback).
- **Rate source + edge values (SES-02):** unit tests over `getSendQuota()` mocked returns
  covering `-1`, `0`/missing, fractional, sandbox `1.0`; assert the ~5-min cache is used
  (GetSendQuota not called on every send) and single-flight under concurrent expiry.
- **Throttle disambiguation (SES-03):** with a mocked `SesClient`, assert a `Throttling`
  + "Maximum sending rate exceeded" is retried and eventually sends; a `Throttling` +
  "Daily message quota exceeded" fails fast (throws, no retry); a non-`Throttling`
  `SesException` propagates unchanged.
- **Exhaustion exception (SES-04):** assert retry/block exhaustion throws
  `SesSendThrottledException` (not `TypeError`, not `null`), the message is NOT marked
  sent, and the in-`send()` attempt count is bounded (Horizon owns outer retries).
- **No double-send + rebind routing (SES-05):** fault-injection test that interrupts
  between the SES call and `markSent()` and asserts no second `sendEmail`; plus a test
  that `MailAdapterFactory::adapter()` for an SES `EmailService` returns
  `ThrottledSesAdapter` after boot (rebind wired) and that no `vendor/` file is modified.

Test env note: the cross-process/limiter test needs Redis provisioned (Horizon/queue
already assume it); GetSendQuota should be mockable/seedable so CI needn't hit live SES.

## Deferred (not this phase)

- **SES-06:** custom Redis-Lua token-bucket limiter (only if fixed-window bursts trip SES).
- **SES-07:** app-level idempotency marker beyond `sent_at` for the send→mark crash gap.

## Sources

- AWS SES: manage-sending-quotas-errors; troubleshoot-error-messages; "How to handle a
  Throttling – Maximum sending rate exceeded error"; re:Post ses-sending-rate-quota-exceeded.
- Installed source: `laravel/framework` 11.55.0 `Illuminate\Redis\Limiters\DurationLimiter`
  / `DurationLimiterBuilder`; SendPortal Core `SesMailAdapter`, `ThrottlesSending`,
  `MailAdapterFactory`, `DispatchMessage`, `MarkAsSent`, `MessageDispatchHandler`;
  `config/horizon.php`, `config/queue.php`, `config/database.php`.
