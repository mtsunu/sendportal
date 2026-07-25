# Project Research Summary

**Project:** SendPortal — Milestone v1.1 "SES Sending Reliability"
**Domain:** Coordinated cross-process per-second SES rate limiting + SES throttle-path bug fixes (Laravel 11 host, `mettle/sendportal-core` 3.0.2, Horizon/Redis, PHP 8.4)
**Researched:** 2026-07-25
**Confidence:** HIGH

## Executive Summary

This is a focused v1.1 reliability milestone on an existing, working system — not a greenfield build. The goal is narrow: make Amazon SES sends pace proactively to the account's per-second `MaxSendRate` (coordinated across up to 20 Horizon workers) and fix two latent bugs in the vendor throttle path. All four research streams (stack, features, architecture, pitfalls) were verified end-to-end against the *installed* vendor and framework source, not from memory, and they converge on a single, unusually well-specified design. Confidence is HIGH across the board; the only genuine open design call is app-level idempotency (see Gaps).

The recommended approach uses **zero new dependencies**. The pacing primitive is Laravel's bundled `Illuminate\Support\Facades\Redis::throttle(key)->allow($rate)->every(1)->block($cap)->sleep(100)->then(...)` (the `DurationLimiter` — an atomic Redis-Lua script that is the same engine behind Laravel's `RateLimitedWithRedis` job middleware, coordinating across all worker processes on the shared `default` Redis connection via phpredis). The entire feature is delivered as a **host-level override**: a new `app/Mail/ThrottledSesAdapter` extends the vendor `SesMailAdapter` and overrides `send()` **wholesale** (it must NOT call `parent::send()`, because both bugs live in the parent's `ThrottlesSending` trait), rebound via `MailAdapterFactory::$adapterMap[EmailServiceType::SES]` in `AppServiceProvider::boot()`. That static map is the only boot-safe, DI-free seam — it is read lazily at dispatch time, so a one-line reassignment in `boot()` is durable regardless of provider order. Nothing under `vendor/` is touched.

The dominant risk is **double-send**. Adding blocking/pacing latency into `send()` widens every existing window between the SES call succeeding and `markSent()` persisting `sent_at` (the only current idempotency guard). The governing invariant — validated across the research — is: **do ALL waiting BEFORE the SES call, never between `send()` and `markSent()`**, with a bounded block (`max_block_seconds = 15`) that is provably `< worker timeout 60s < redis retry_after 90s`. The second-order risks are subtle SES semantics: the error code `Throttling` is returned for BOTH "Maximum sending rate exceeded" (retriable) AND "Daily message quota exceeded" (NOT short-term retriable), so detection must gate on the code AND sub-branch on the message; and retry exhaustion must throw a **named exception**, never fall through to `null` (which reproduces a `TypeError` against `send(): string`). Horizon (`tries=3`) should be the single retry owner to avoid a 10×loop × 3-tries = 30-attempt explosion.

## Key Findings

### Recommended Stack

The stack is fixed and re-uses what is already installed and running on PHP 8.4.23 (verified `laravel/framework` 11.55.0 in the committed lock, live `:8.4` CI green). No `composer require`. The client is **phpredis** (`predis/predis` is only a dev/suggest dependency), and the limiter runs over the same `default` connection (DB 0) that Horizon coordinates on, so every worker contends on one shared keyspace.

**Core technologies:**
- `Illuminate\Support\Facades\Redis::throttle()` → `Illuminate\Redis\Limiters\DurationLimiter` — atomic, cross-process, per-second admission control via a single Redis-Lua `EVAL`; `every(1)` = per-second, `block()` paces instead of merely signaling. RECOMMENDED primitive.
- `Illuminate\Support\Facades\Cache` (`Cache::remember`) — caches `getSendQuota()['MaxSendRate']` ~5 min (SES-02) to feed `allow($rate)`; avoids a `GetSendQuota` round-trip per message.
- `Illuminate\Contracts\Redis\LimiterTimeoutException` — the block-timeout signal; the override catches it and throws the named exception rather than returning `null`.

**Ruled out (deliberately):** queue/job middleware (`RateLimitedWithRedis`) — unreachable, because the throttle point is a vendor adapter method with no `$job`, and middleware attaches only to the vendor listener. Any new rate-limit Composer package, `predis`, in-memory/per-process limiters, and `Cache::lock`/`WithoutOverlapping` (mutual exclusion, not rate control). Custom Redis-Lua **token bucket** is kept as a documented escalation only if the fixed-window burst actually trips SES.

### Expected Features

Four numbered features, all P1, sourced from AWS docs + vendor code. The existing **daily** `Max24HourSend` pre-check (`QuotaService`) is correct and explicitly out of scope — do not re-scope or regress it.

**Must have (table stakes):**
- **SES-02** — source live `MaxSendRate` from `getSendQuota()`, cached ~5 min; conservative fallback on fetch failure. (Build first — SES-01 needs a value to pace to.)
- **SES-01** — Redis-coordinated proactive pacing to `MaxSendRate` across all workers. The core outcome; a per-process limiter would multiply the rate by worker count.
- **SES-03** — detect throttle via `getAwsErrorCode() === 'Throttling'` (not brittle message-string match), AND sub-branch on message to separate rate-exceeded from daily-quota-exceeded.
- **SES-04** — throw a clear, named exception on retry exhaustion; let Horizon retry. No `null` → `TypeError`.
- Host-level override only (no `vendor/` edits).

**Should have (competitive):**
- At most one info log line at rate-refresh / throttle-retry — only if trivial.

**Defer (v2+):**
- Config/env override for the rate (drifts from the auto-ramped real limit — anti-feature), SESv2 migration (changes the error-code contract — anti-feature), operator UI showing effective rate, per-configuration-set / dedicated-IP granularity, adaptive backoff rewrite.

### Architecture Approach

The send path is `MessageDispatchEvent` → queued vendor listener `MessageDispatchHandler` (`sendportal-message-dispatch`, min 2 / max 20, `tries=3`) → `DispatchMessage` → `RelayMessage` → `MailAdapterFactory::adapter()` → `SesMailAdapter::send()` → `ThrottlesSending` → `SesClient::sendEmail()`. The single clean seam is the factory's `public static $adapterMap`, read lazily at dispatch — so pacing must live **inside `send()`**, and the natural primitive there is a **bounded block** (release-to-queue is impossible: the adapter never receives `$job`). Release-via-throw is retained only for the two genuinely-exceptional exits (block timeout, retry exhaustion), where riding `tries=3` → `failed_jobs` is the correct loud failure (SES-04).

**Major components (new/changed host files, in build order):**
1. `config/sendportal-throttle.php` (new) — `max_block_seconds` (15), `rate_cache_ttl` (300), local-retry knobs, key prefix. Pure data.
2. `app/Mail/SesSendThrottledException.php` (new) — thin `extends \RuntimeException`; the named failure for SES-04.
3. `app/Mail/ThrottledSesAdapter.php` (new) — extends `SesMailAdapter`, overrides `send()` wholesale (NOT `parent::send()`), reuses only safe inherited helpers (`resolveClient()`, `resolveMessageId()`, `getSendQuota()`, `$this->config`). No container DI — deps via facades/`app()`. Core of the milestone (SES-01..04).
4. `app/Providers/AppServiceProvider.php` (modify) — one line: `MailAdapterFactory::$adapterMap[EmailServiceType::SES] = ThrottledSesAdapter::class;` in `boot()`.
5. Tests in `tests/`.

**Keying:** limiter `sp:ses:rate:{key}` and cache `sp:ses:maxrate:{key}` where `key = md5(region|access-key-id)` — SES `MaxSendRate` is per-account/region, so two `email_service` rows on one account correctly share a bucket. Rate = `max(1, floor(getSendQuota()['MaxSendRate']))` for the integer `allow($n)`.

### Critical Pitfalls

1. **Double-send (highest severity)** — worker death, DB failure on mark, or timeout in the gap between SES `send()` and `markSent()` re-sends the email under `tries=3`. Avoid: do ALL waiting BEFORE the SES call, never between send and mark; `max_block_seconds = 15 << timeout 60 < retry_after 90`; consider a Redis in-flight/sent marker (SES v1 has no idempotency token) and log-not-rethrow on post-send `markSent` failure.
2. **`Throttling` false-positive (Bug-1 subtlety)** — `Throttling` covers rate-exceeded AND daily-quota-exceeded. Avoid: gate on `getAwsErrorCode() === 'Throttling'` as the outer family, then sub-branch on message — retry only "Maximum sending rate", fail-fast a distinct exception for "Daily message quota". Do NOT delete the message inspection.
3. **Not actually cross-process** — a limiter on `array`/`file` cache or a PHP static gives 20× overshoot. Avoid: pin explicitly to shared Redis; add a real cross-process (≥2 worker) verification test inspecting the shared key; fail fast if the store isn't Redis.
4. **Fixed-window edge burst** — `DurationLimiter` resets per window, permitting ~2N across a sub-second boundary; SES enforces a rolling rate. Mitigation: usually absorbed by SES + retry; if observed to trip SES, escalate to a Lua token bucket (min-interval `1/rate`). This is the single most important design trade-off to flag.
5. **Wrong Bug-2 exception (SES-04)** — throw a specific named exception (not bare `\Exception`, not `TypeError`, not a control-flow exception); make Horizon `tries=3` the single retry owner to avoid 10×loop × 3 = 30 attempts.

Secondary: `GetSendQuota` cache stampede (single-flight via `Cache::lock` + stale-while-revalidate + last-known-good fallback, never "unlimited"); thundering-herd synchronized retries (add jitter); blocking starves workers at low rates (bounded wait + release-before-SES); `MaxSendRate` edge values `-1`/unlimited/`0`/fractional/sandbox `1.0` (bypass limiting for `<=0`/unlimited, never floor refill to zero).

## Implications for Roadmap

This is a small, tightly-coupled milestone. The four bugs live in one trait and are delivered by one adapter override; over-phasing would fragment code that must be co-designed. Suggested structure is a short sequence with SES-02 first (SES-01 depends on it) and Bug-1/Bug-2 co-designed.

### Phase 1: Quota sourcing + config scaffold (SES-02)
**Rationale:** SES-01 cannot pace to a rate it has not sourced; this is the least-coupled piece and unblocks everything.
**Delivers:** `config/sendportal-throttle.php`, cached `MaxSendRate` read (`Cache::remember`, `md5(region|key)` key, ~5 min), edge-value guards (`-1`/unlimited/`0`/fractional/sandbox), single-flight + last-known-good fallback.
**Addresses:** SES-02.
**Avoids:** Pitfall 5 (stampede), Pitfall 9 (edge rates).

### Phase 2: ThrottledSesAdapter + coordinated limiter + rebind (SES-01, SES-03, SES-04)
**Rationale:** The adapter override is the core; the limiter, throttle detection, and exhaustion exception all live inside the same `send()`/`doSend()` and must be co-designed. Bug-1 (SES-03) and Bug-2 (SES-04) share the throttle-handling block.
**Delivers:** `SesSendThrottledException`, `ThrottledSesAdapter` (wholesale `send()` override, bounded `Redis::throttle(...)->block(15)->then()`, `doSend` with code+message sub-branch and named exhaustion exception), one-line `AppServiceProvider` rebind.
**Uses:** `Redis::throttle()` DurationLimiter, `Cache`, `LimiterTimeoutException` (STACK.md).
**Implements:** injection point A (static-map rebind) + block-primary/throw-overflow hybrid (ARCHITECTURE.md).
**Avoids:** Pitfalls 1, 2 (block-before-send invariant), 3, 6, 7, 8.

### Phase 3: Verification hardening (acceptance tests + double-send fault injection)
**Rationale:** Several failure modes ("looks done but isn't") are only caught by cross-process and fault-injection tests, not unit tests. Worth an explicit step so it is not skipped.
**Delivers:** the four explicit acceptance tests — (a) cross-process limiter is genuinely shared (not per-process), (b) double-send fault injection (kill between send and mark → no duplicate), (c) SES-03 rate-vs-daily-quota branch, (d) SES-04 exhaustion throws the named exception (no `TypeError`) — plus store-is-Redis assertion and timeout-ordering check.
**Avoids:** Pitfalls 2, 3, 1, 8 verification gaps.

### Phase Ordering Rationale
- SES-02 → SES-01 is a hard dependency (need a rate value before pacing); SES-03 → SES-04 is a hard dependency (must classify a throttle before deciding to exhaust). This fixes the 3-step order.
- Bug-1 and Bug-2 are grouped because both live in the throttle-handling path and the "give up" path (SES-04) must produce the clear exception that SES-03's daily-quota branch also fails-fast with.
- Verification is separated because the highest-severity risk (double-send) and the coordination guarantee are provable only by fault-injection and multi-process tests — the kind that get dropped when folded into feature steps.
- Phases 1 and 2 could reasonably be merged into a single phase given the small surface; the roadmapper may collapse them if it prefers one implementation phase + one verification phase.

### Research Flags

Phases likely needing deeper research during planning:
- **Phase 2 (limiter algorithm):** the fixed-window vs token-bucket trade-off (Pitfall 4) is a live design decision. Ship `DurationLimiter` first; the Lua token-bucket escalation only needs research if SES throttling is observed. Flag `--research-phase` only if the planner wants the token-bucket contingency designed up front.
- **Phase 3 (double-send idempotency):** whether to add an app-level Redis in-flight marker beyond `sent_at` is an open design call (see Gaps). Worth a focused decision during planning.

Phases with standard patterns (skip research-phase):
- **Phase 1:** `Cache::remember` + `Cache::lock` single-flight is a well-documented, established pattern.

## Confidence Assessment

| Area | Confidence | Notes |
|------|------------|-------|
| Stack | HIGH | Every primitive verified against installed `laravel/framework` 11.55.0 source; live `:8.4` CI green; zero new deps. |
| Features | HIGH | AWS-doc-backed; one SESv2 error-code detail is MEDIUM but v2 is out of scope. |
| Architecture | HIGH | Every load-bearing claim verified against vendor + framework source (factory map, adapter, trait, limiter `block()`, `getAwsErrorCode`, config). |
| Pitfalls | HIGH | Code read end-to-end; AWS dual-`Throttling` behavior confirmed against AWS docs; timeout/`retry_after` ordering verified from config. |

**Overall confidence:** HIGH

### Gaps to Address
- **App-level idempotency beyond `sent_at`:** SES v1 `sendEmail` has no idempotency token; the only current guard is `sent_at` persisted after send. A Redis in-flight marker or unique constraint would close the crash-in-gap window, but is an explicit open design call. Handle during Phase 2/3 planning; the block-before-send invariant + 15s bound is the minimum mitigation.
- **Fixed-window burst in practice:** whether `DurationLimiter`'s window-edge ~2N burst actually trips SES is unverified in production. Handle by shipping the simple limiter with the token-bucket escalation documented; monitor SES `Throttling` after launch.
- **Live `Throttling` wire message string:** the exact wording/punctuation of the daily-quota vs rate messages was not byte-verified on the wire. Handle by matching a substring (`Maximum sending rate`, `Daily message quota`) rather than an exact string, and by gating on the error code as the outer family.

## Sources

### Primary (HIGH confidence)
- Installed framework source: `Illuminate/Redis/Limiters/DurationLimiter*.php` (`allow/every/block/sleep/then`, whole-second window, atomic Lua `EVAL`, 750ms default sleep, `LimiterTimeoutException`), `Illuminate/Cache/RateLimiter.php`, `Illuminate/Queue/Middleware/*` — verified 11.55.0.
- Vendor source: `mettle/sendportal-core` `Factories/MailAdapterFactory.php` (public static lazy map), `Adapters/{SesMailAdapter,BaseMailAdapter}.php`, `Traits/ThrottlesSending.php` (string match + `null`-return bug), `Services/Messages/{DispatchMessage,RelayMessage}.php`, `MarkAsSent.php` (`sent_at` guard), `Listeners/MessageDispatchHandler.php`; `aws/aws-sdk-php` `Exception/AwsException.php` (`getAwsErrorCode`).
- Repo config: `config/horizon.php` (supervisor-2 min2/max20, `tries=3`, no `timeout` → 60s default), `config/queue.php` (`retry_after=90`), `config/database.php` (`REDIS_CLIENT=phpredis`), `composer.lock`.
- AWS SES docs: [GetSendQuota v1](https://docs.aws.amazon.com/ses/latest/APIReference/API_GetSendQuota.html), [Sending-quota errors](https://docs.aws.amazon.com/ses/latest/dg/manage-sending-quotas-errors.html) (both conditions share `Throttling`), [Handling the Throttling error](https://aws.amazon.com/blogs/messaging-and-targeting/how-to-handle-a-throttling-maximum-sending-rate-exceeded-error/), [SES sending errors](https://docs.aws.amazon.com/ses/latest/dg/troubleshoot-error-messages.html).

### Secondary (MEDIUM confidence)
- SESv2 `TooManyRequestsException` (HTTP 429) error-code detail — out of scope; v1 stays authoritative.

### Tertiary (LOW confidence)
- Exact on-the-wire punctuation of the `Throttling` message strings — mitigated by substring matching + error-code gating.

---
*Research completed: 2026-07-25*
*Ready for roadmap: yes*
