# Pitfalls Research

**Domain:** Coordinated per-second SES rate limiting across Horizon workers + SES throttle-path bug fixes (host-level `ThrottledSesAdapter` override, no vendor edits)
**Researched:** 2026-07-25
**Confidence:** HIGH (code read end-to-end; AWS error-code behavior confirmed against AWS docs)

## Context anchors (verified from code)

- **Queued unit:** `MessageDispatchHandler` (a queued **event listener**, `ShouldQueue`, `$queue = 'sendportal-message-dispatch'`). It calls `DispatchMessage::handle()`.
- **Send→mark sequence** (`DispatchMessage::handle`): `dispatch()` → SES `send()` returns `messageId` → **then** `markSent()` (`MarkAsSent` sets `sent_at = now()` + `message_id`, saves). Idempotency guard is `isValidMessage()` → `if ($message->sent_at) return false;`. **The only thing preventing re-send is `sent_at` having been persisted.**
- **Horizon** (`config/horizon.php`): `supervisor-2` → queue `sendportal-message-dispatch`, `balance=auto`, `minProcesses=2`, `maxProcesses=20`, `tries=3`. **No `timeout` key set** → Laravel/Horizon worker default timeout = **60s**. `memory_limit=64` MB.
- **Queue** (`config/queue.php`): `redis` connection `retry_after=90`, `block_for=null`. So today `timeout(60) < retry_after(90)` — correct ordering. **Any change that lets a single job block/run ≥60s breaks this and causes the worker to be killed mid-flight.**
- **Vendor `ThrottlesSending` (being bypassed, not edited):** loops `while ($attempt < 10)`, matches `$e->getMessage() == 'Maximum sending rate exceeded.'`, `usleep()` with deterministic `attempt^2` backoff, **falls out of the loop returning `null`** after 10 attempts → `resolveMessageId(Result $result)` gets `null` → `TypeError`.
- **AWS SDK:** `Aws\Ses\Exception\SesException` extends `AwsException`; `getAwsErrorCode()` returns the parsed `errorCode` string.

---

## Critical Pitfalls

### Pitfall 1: Bug-1 "fix" turns a precise check into a false-positive — daily-quota-exceeded gets retried as if it were a rate limit

**What goes wrong:**
The milestone replaces `getMessage() == 'Maximum sending rate exceeded.'` with `getAwsErrorCode() === 'Throttling'`. **AWS SES returns the *same* error code (`Throttling` / `ThrottlingException`) for two different conditions** — "Maximum sending rate exceeded." **and** "Daily message quota exceeded." They differ *only in the message string*. So switching to the error code alone makes the limiter treat "you are out of your 24-hour quota" as a transient per-second rate limit: it will pace/sleep/retry and eventually fail after retries with no signal that the account is actually out of daily quota. Ironically the old brittle string match was *more* selective about which throttle to retry.

**Why it happens:**
"Detect via error code, not message string" sounds strictly better and matches the requirement wording (SES-03). Developers assume one error code == one condition. It doesn't for SES v1.

**How to avoid:**
Treat `Throttling` as the *family*, then branch on intent:
- Rate exceeded (message contains `Maximum sending rate`) → this is the pace-and-retry path.
- Daily quota exceeded (message contains `Daily message quota` / `Daily sending quota`) → **do not spin retries**; fail fast with a clear, distinct exception (or release with a long delay). Retrying 3× against a 24-hour cap is pointless and delays surfacing the real problem.
- Any other `Throttling` sub-message → fail with the clear exception rather than silently retrying forever.
Keep the error-code check as the outer gate (robust to localization/wording drift of the *rate* message) but retain a message substring check to separate quota-exhaustion from rate-exceeded. Do **not** delete the message inspection entirely.

**Warning signs:**
Campaigns silently stall near the daily cap; logs show repeated "throttle" sleeps with no successful sends; jobs fail after `tries=3` with a generic throttle error instead of a "daily quota exhausted" signal.

**Phase to address:** Throttle-detection fix step (Bug-1). Must be co-designed with Bug-2 so the "give up" path produces the clear exception, not `null`.

---

### Pitfall 2: Double-send — SES accepts the email but `sent_at` is never persisted, so `tries=3` re-sends it

**What goes wrong:**
In `DispatchMessage::handle`, SES `send()` succeeds (email physically delivered, `messageId` returned) but the worker dies **before `markSent()` persists `sent_at`**. Because `isValidMessage()` only guards on `sent_at`, the retry re-runs the full send → **the recipient gets the email twice** (or 3×). Death windows between send and mark:
1. **Worker timeout (60s):** a blocking rate limiter or a long throttle back-off pushes the job past the 60s worker timeout; Horizon SIGALRM-kills the process *after* SES accepted but *before* mark. This is the highest-probability window and **is made worse by this milestone**, because we are deliberately adding blocking/pacing latency into `send()`.
2. **DB failure on mark:** `markSent()` is not wrapped in try/catch; a deadlock/connection blip on `$message->save()` throws → job retried → resend.
3. **Process crash:** OOM (`memory_limit=64`MB) or a deploy/restart in the send→mark gap.

**Why it happens:**
The vendor flow was written for a fast, non-blocking send. Adding coordinated rate limiting *increases per-job wall-clock time*, widening every one of these windows. Nobody re-audits the idempotency guard when adding a limiter.

**How to avoid:**
- **Never block between send and mark.** All pacing/waiting for a token/permit must happen **before** the SES `sendEmail` call. Once SES is called, go straight to `markSent` with no awaitable work in between.
- **Keep the single-job blocking budget well under the worker timeout.** Set an explicit Horizon `timeout` for `supervisor-2` and enforce a max in-`send()` wait that leaves comfortable margin (e.g., wait budget ≤ ~half of `timeout`). If a token is not available within budget, **release the job back to the queue with a delay *before* calling SES** (safe: no send has happened) rather than blocking to the timeout.
- **Preserve `timeout < retry_after`.** If you raise `timeout`, keep it under `retry_after=90`, otherwise Horizon releases the job while it is still running → guaranteed double-processing.
- Consider a **DB-level idempotency guard** independent of `sent_at` (e.g., short-lived Redis "in-flight/sent" marker keyed by message id set immediately before the SES call, or a unique constraint) so a crash in the gap cannot resend. SES v1 `sendEmail` has **no idempotency token**, so app-level dedup is the only lever.
- Wrap `markSent` failure handling so a *post-send* DB error does **not** bubble as a retryable job failure that resends (log + alert instead, since the email already went out).

**Warning signs:**
Recipients report duplicate emails; `messages` rows where `sent_at` was set on a later attempt; Horizon "job released after timeout" events on `sendportal-message-dispatch`; retries concentrated on large campaigns (longer blocking).

**Phase to address:** Adapter-override step + rate-limiter step (block-before-send invariant) + a dedicated idempotency/verification step. This is the single highest-severity risk of the milestone.

---

### Pitfall 3: Limiter is not actually shared across processes (in-memory / wrong cache store or connection)

**What goes wrong:**
The limiter "works" in a single-process test but does nothing in production because state lives per-process. Classic causes: using Laravel's `RateLimiter`/`Cache` with the **default store bound to `array`/`file`/`apc`** (per-process), building a token bucket in a PHP static/instance property (reset every job), or writing to a Redis **connection/database/prefix** that differs from where other workers read. With `maxProcesses=20`, 20 independent counters each allow the full `MaxSendRate` → **up to 20× the intended rate** → SES `Throttling`.

**Why it happens:**
`RateLimiter` and `Cache::remember` silently fall back to whatever the default store is; test envs pin cache to `array`; Horizon's own Redis (`horizon.use=default`, prefix `..._horizon:`) vs the app cache Redis vs queue Redis are easy to conflate.

**How to avoid:**
- Bind the limiter **explicitly** to a named Redis connection + fixed key + fixed prefix; do not rely on the default cache store. Use an atomic Redis primitive (Lua-scripted token bucket or `INCR`+`EXPIRE`) so the count is authoritative and shared.
- Add a **cross-process verification test**, not just a unit test: launch ≥2 processes (or ≥2 concurrent workers) hitting the limiter and assert the *combined* observed send rate ≤ `MaxSendRate`; and/or inspect the actual Redis key with `redis-cli` to confirm all workers touch one key.
- Assert at boot that the limiter store is Redis (fail fast if it resolves to `array`/`file`).

**Warning signs:**
SES `Throttling` errors persist after the limiter ships; the Redis key count never exceeds `MaxSendRate` when tested single-process but SES still throttles under load; rate scales with worker count.

**Phase to address:** Rate-limiter step (design + cross-process test as an explicit acceptance criterion).

---

### Pitfall 4: Fixed-window limiter allows a 2× burst at window edges → exceeds MaxSendRate anyway

**What goes wrong:**
Laravel's built-in `RateLimiter` (and naive `INCR` with a 1-second TTL) is a **fixed window**: it permits `N` sends in `[t, t+1s)` and another `N` in `[t+1s, t+2s)`. A burst clustered at 0.99s and 1.01s puts `~2N` sends into ~20ms. SES enforces a **rolling** rate, so it sees `2×MaxSendRate` instantaneously and returns `Throttling` — defeating the whole feature at exactly the moments of highest load.

**Why it happens:**
Fixed-window counters are the easiest thing to reach for and pass casual tests (which rarely straddle a window boundary).

**How to avoid:**
Use a **token bucket** (or GCRA / leaky-bucket / sorted-set sliding window) with capacity ≈ `MaxSendRate` and refill of `MaxSendRate` tokens/sec, implemented atomically in Redis (Lua). Token bucket naturally spaces sends and caps *instantaneous* rate. If a simpler design is required, enforce a **minimum inter-send interval** of `1 / MaxSendRate` seconds per permit. Do not ship a bare fixed-window counter.

**Warning signs:**
Intermittent `Throttling` under bursty load despite the limiter reporting "under limit"; throttle errors cluster right after each whole second.

**Phase to address:** Rate-limiter step (algorithm choice — this is a design decision, flag for the planner).

---

### Pitfall 5: Cache stampede on `getSendQuota()` when the ~5-min cache expires under load

**What goes wrong:**
`MaxSendRate` is sourced from SES `getSendQuota()` and cached ~5 min (SES-02). When the entry expires, up to 20 workers miss simultaneously and **all call `GetSendQuota` at once**. `GetSendQuota` has its **own low request rate** and shares SES API throttling — the stampede can itself return `Throttling`, and if the failure path is "no rate value → block or skip limiting," every worker either stalls or sends unthrottled for the refresh window.

**Why it happens:**
`Cache::remember` gives no single-flight guarantee; every concurrent miss recomputes. Under `maxProcesses=20` a cold cache means 20 concurrent API calls.

**How to avoid:**
- Single-flight the refresh: `Cache::lock()` (atomic Redis lock) so exactly one worker calls `GetSendQuota`; others wait briefly or use the last-known value.
- **Stale-while-revalidate:** keep a longer hard TTL and refresh in the background; never let the value go fully absent. Seed/refresh proactively via a scheduled command so the hot path rarely calls SES.
- On `GetSendQuota` failure, **fall back to the last-known good `MaxSendRate`** (or a conservative floor), never to "unlimited" and never to blocking all sends.

**Warning signs:**
Periodic (~every 5 min) spikes of `GetSendQuota` throttle errors; brief rate overshoots or send stalls aligned to cache-expiry boundaries.

**Phase to address:** Quota-sourcing/cache step.

---

### Pitfall 6: Thundering herd / synchronized retry storm when blocked workers wake together

**What goes wrong:**
Two coupled effects: (a) when a token-bucket refills or a fixed window resets, all blocked workers wake and fire in the same instant → burst → `Throttling`. (b) The vendor back-off `resolveSleepDuration(attempt)` is **fully deterministic** (`min * attempt^2`) with **no jitter** — every throttled worker sleeps the *same* duration and retries at the *same* moment, re-colliding on SES. With ≤20 workers this self-perpetuates.

**Why it happens:**
Deterministic back-off and hard window boundaries synchronize independent workers; jitter is easy to forget.

**How to avoid:**
Add **randomized jitter** to every sleep/back-off and to token-wait polling (full or decorrelated jitter). Prefer a token bucket that hands out permits continuously (no shared wake edge). When releasing a job back to the queue, use a **randomized delay**.

**Warning signs:**
Throttle errors arrive in synchronized bursts; retry timestamps cluster; effective throughput oscillates (bursts then idle).

**Phase to address:** Rate-limiter step + throttle-detection/back-off step (Bug-1 area — replace deterministic back-off with jittered back-off).

---

### Pitfall 7: Blocking design starves worker slots and collides with the 60s worker timeout / `retry_after`

**What goes wrong:**
If each job **blocks** waiting for a token, workers spend most of their wall-clock time sleeping. With `balance=auto` Horizon sees the backlog and scales `supervisor-2` toward `maxProcesses=20` — but all 20 just block on the same limiter, wasting processes and memory without raising throughput (throughput is capped by `MaxSendRate`, not by worker count). Worse, a low `MaxSendRate` (e.g., sandbox `1/s`, or a job that waits behind many others) can push a single job's blocking time past the **60s worker timeout** → SIGALRM kill → release → retry (feeds Pitfall 2). If someone "fixes" the timeout by raising it past `retry_after=90`, jobs get released while still running → double-processing.

**Why it happens:**
"Just sleep until a token is free" is the simplest limiter integration and looks fine at high `MaxSendRate`; it degrades badly at low rates / high contention.

**How to avoid:**
Bound the in-`send()` wait to a small budget; if no token within budget, **release the job with a jittered delay before touching SES** instead of blocking to timeout. Set an explicit `timeout` on `supervisor-2` and keep `timeout < retry_after (90)`. Consider capping `maxProcesses` relative to `MaxSendRate` (no value in 20 workers for a 1/s account). Ensure the limiter permit is **released/accounted even when `send()` throws** so permits don't leak and stall the bucket.

**Warning signs:**
Horizon shows many `sendportal-message-dispatch` processes with high wait time and low completion; `LongWaitDetected`; jobs failing with timeout; throughput not improving as processes scale up.

**Phase to address:** Rate-limiter step (release-vs-block decision) + a Horizon config step (`timeout`, process caps).

---

### Pitfall 8: Bug-2 fix throws the wrong exception type — dirty Horizon failure or double-counted retries

**What goes wrong:**
Replacing the `null` return on retry-exhaustion with a thrown exception is correct in spirit, but the *type/placement* matters. If the exception is thrown from a place already inside a retry loop, or is a type Laravel treats specially, it can (a) be swallowed/mis-handled, (b) cause the job to be marked failed without exhausting `tries` (or, conversely, double-count an attempt), or (c) surface as an opaque `TypeError`-adjacent trace that hides the "throttle exhausted" cause. If the same throttle is both retried inside `send()` *and* retried by Horizon `tries=3`, total attempts multiply (10 internal × 3 job = 30) and back-off math compounds.

**Why it happens:**
Two independent retry layers exist — the vendor internal `while ($attempt < 10)` loop **and** Horizon `tries=3`. Bolting an exception onto one without deciding who owns retries produces surprising multiplicative behavior.

**How to avoid:**
- Throw a **specific, named exception** (e.g., a `SesSendRateExhaustedException`) — not a bare `\Exception` or a `TypeError`, and not a Laravel control-flow exception — so logs and Horizon "failed jobs" clearly attribute the cause.
- **Decide the single retry owner.** Recommended: let the host `send()` do the *pacing* (proactive, so throttle should be rare) and do minimal/zero internal reactive retries, then let **Horizon `tries=3`** own retries. Avoid stacking a 10× internal loop under a 3× job loop.
- Ensure the thrown exception **propagates cleanly** out of `send()` → `RelayMessage` → `DispatchMessage::handle` (all declare `@throws Exception`) → the queued listener → normal Horizon retry/fail accounting. Confirm it counts as exactly one failed attempt.

**Warning signs:**
`failed_jobs` entries showing `TypeError` or generic exceptions instead of a throttle-named exception; attempt counts that don't match `tries=3`; jobs failing far faster or slower than expected.

**Phase to address:** Retry-exhaustion fix step (Bug-2), co-designed with the retry-ownership decision.

---

### Pitfall 9: `MaxSendRate` edge values — unlimited (`-1`), fractional, and sandbox accounts

**What goes wrong:**
`getSendQuota()` returns `MaxSendRate` as a **float**. Edge cases break naive limiter math:
- **Unlimited accounts:** SES may report a sentinel (accounts without a cap; `Max24HourSend = -1` denotes unlimited, and rate can be very high). Treating `-1` or a huge value as a literal token budget produces `1/rate ≈ 0` intervals or nonsense buckets.
- **Fractional / sub-1 rates:** e.g., `1.0`, `0.2`. Integer-flooring a fractional refill can yield **0 tokens/sec → all sends blocked forever** (division/`floor` to zero), or `1/rate` for `rate<1` must be handled (interval > 1s).
- **Sandbox accounts:** `MaxSendRate=1`, `Max24HourSend=200`, and sends only allowed to *verified* addresses. The limiter must still function at `1/s`; and near-zero rates must not divide-by-zero.

**Why it happens:**
Developers assume an integer like `14` and never test `-1`, `0`, `0.x`, or sandbox.

**How to avoid:**
- Cast to float; **treat `MaxSendRate <= 0` or the unlimited sentinel as "no limiting"** (bypass the limiter entirely) rather than feeding it into bucket math.
- Support **fractional rates** (token bucket with fractional refill, or interval `= 1/rate` allowing intervals > 1s); never `floor` the refill to zero.
- Add unit tests for `rate = 14.0`, `1.0`, `0.2`, `0`, `-1`, and a large "unlimited" value.
- Document sandbox behavior (verified-recipient requirement is SES account state, out of scope to fix, but note it so testers don't misdiagnose a sandbox rejection as a limiter bug).

**Warning signs:**
Sends completely stall on low/unlimited-rate accounts; divide-by-zero / `INF` in limiter math; limiter no-ops on high-rate accounts (if unlimited mishandled the other way).

**Phase to address:** Quota-sourcing step (parse/guard) + rate-limiter step (fractional math).

---

## Technical Debt Patterns

| Shortcut | Immediate Benefit | Long-term Cost | When Acceptable |
|----------|-------------------|----------------|-----------------|
| Use Laravel `RateLimiter`/`Cache::remember` on the default store | Few lines, "it works locally" | Silently per-process if default store isn't Redis → 20× overshoot | Never for the cross-process limiter; OK only if store is explicitly pinned to Redis |
| Fixed-window `INCR`+`EXPIRE(1s)` limiter | Trivial to write | 2× edge-of-window bursts → SES throttles anyway | Only as a stopgap with generous headroom below `MaxSendRate`; prefer token bucket |
| Match `getAwsErrorCode() === 'Throttling'` and retry all of it | Satisfies "use error code" literally | Daily-quota-exceeded retried as rate limit; real quota exhaustion hidden | Never — must sub-branch on message for the quota case |
| Block in `send()` until a token frees | Simple pacing | Starves workers, hits 60s timeout → double-send | Only with a bounded wait budget << timeout + release-before-SES fallback |
| Keep the vendor 10× internal retry loop *and* Horizon `tries=3` | No refactor of loop | 30 effective attempts, compounded back-off | Never both reactive layers at full strength; pick one owner |
| No idempotency marker (rely solely on `sent_at`) | Matches existing code | Crash in send→mark gap = duplicate email | Acceptable only if blocking never occurs between send and mark AND timeout margin is safe |

## Integration Gotchas

| Integration | Common Mistake | Correct Approach |
|-------------|----------------|------------------|
| SES `SendEmail` (v1) throttle detection | Assume one error code = one cause | `Throttling` covers **both** rate-exceeded and daily-quota-exceeded; branch on message to separate them |
| SES `GetSendQuota` | Call it on every send / every cache miss | Single-flight via `Cache::lock`, cache with stale-while-revalidate, fall back to last-known on failure — it has its own low rate limit |
| SES `SendEmail` idempotency | Expect an idempotency token to prevent dup | v1 `sendEmail` has none; dedup must be app-level (`sent_at` + optional Redis in-flight key) |
| Redis (Horizon vs cache vs queue) | Assume "Redis" is one shared namespace | Horizon prefix `..._horizon:` and app cache/queue may be different DB/prefix; pin the limiter connection+key explicitly |
| Laravel queued **listener** retries | Think `tries` is on a Job class only | `tries=3` is set at the Horizon supervisor level and applies to the listener; the internal vendor loop is a *second* retry layer |
| Worker `timeout` vs `retry_after` | Raise `timeout` to allow long blocks | Must keep `timeout(60) < retry_after(90)`; violating it double-processes every long job |

## Performance Traps

| Trap | Symptoms | Prevention | When It Breaks |
|------|----------|------------|----------------|
| Per-process limiter | Rate ≈ `MaxSendRate × processes`; SES throttles | Shared atomic Redis limiter + cross-process test | As soon as `processes > 1` (i.e., always in prod, `maxProcesses=20`) |
| Fixed-window bursts | Throttle errors right after each whole second | Token bucket / min-interval spacing | Under bursty load at any scale |
| `GetSendQuota` stampede | ~5-min periodic throttle spikes | Single-flight + stale-while-revalidate | Cold cache with many concurrent workers |
| Blocking starves workers | Many processes, high wait, flat throughput | Bounded wait + release-before-send | Low `MaxSendRate` / high contention |
| Synchronized back-off | Throttle errors in bursts, oscillating throughput | Jittered back-off/delays | Any multi-worker throttle event |

## Security / Safety Mistakes

| Mistake | Risk | Prevention |
|---------|------|------------|
| Editing `vendor/mettle/sendportal-core` to fix the bugs | Breaks the no-vendor-edit constraint; lost on `composer install`; lock/CI drift | Host-level `ThrottledSesAdapter extends SesMailAdapter` overriding `send()`; bind via adapter factory |
| Treating daily-quota-exceeded as retryable | Masks a real account limit; campaign silently stalls | Distinct fail-fast exception for the quota case |
| Falling back to "unlimited" when `GetSendQuota` fails | Blasts SES past rate → account reputation / throttling penalties | Fall back to last-known-good or a conservative floor |
| Logging full message content on throttle | PII in logs | Log message id + error code only |

## "Looks Done But Isn't" Checklist

- [ ] **Coordinated limiter:** verify **cross-process** (2+ workers), not just single-process unit test — inspect the actual shared Redis key.
- [ ] **Limiter store:** assert it resolves to Redis, not `array`/`file` (fail fast at boot).
- [ ] **Burst behavior:** test sends straddling a whole-second boundary — confirm no 2× overshoot (token bucket, not fixed window).
- [ ] **Double-send:** simulate worker-kill / DB-fail **between `send()` and `markSent()`** — confirm no duplicate on retry.
- [ ] **Block-before-send invariant:** assert no awaitable wait exists between the SES call and `markSent`.
- [ ] **Timeout ordering:** confirm `supervisor-2 timeout < retry_after (90)` and max in-`send` wait << timeout.
- [ ] **Bug-1:** test a `SesException` whose code is `Throttling` + message "Daily message quota exceeded" → confirm it does **not** enter the pace-and-retry path.
- [ ] **Bug-1:** test a non-throttle `SesException` (e.g., `MessageRejected`) → confirm it is **not** swallowed, propagates immediately.
- [ ] **Bug-2:** on retry exhaustion, confirm a **named exception** (not `null`, not `TypeError`) reaches Horizon and counts as one failed attempt.
- [ ] **Retry ownership:** confirm total attempts are not the product of an internal loop × `tries=3`.
- [ ] **`GetSendQuota`:** single-flight under concurrent cache miss; fallback on API failure.
- [ ] **Edge rates:** `MaxSendRate` of `-1`/unlimited, `0`, fractional (`0.2`), and sandbox `1.0` all handled (no divide-by-zero, no permanent stall).
- [ ] **Permit leak:** limiter permit released/accounted when `send()` throws.

## Recovery Strategies

| Pitfall | Recovery Cost | Recovery Steps |
|---------|---------------|----------------|
| Duplicate sends shipped (Pitfall 2) | HIGH | Cannot un-send; add idempotency marker + tighten timeout margin; audit `messages` for late `sent_at`; notify affected campaigns |
| Daily-quota retried as rate (Pitfall 1) | MEDIUM | Add message sub-branch; reprocess stuck messages after quota window; alert on quota exception |
| Per-process limiter (Pitfall 3) | MEDIUM | Re-bind to shared Redis; add cross-process test; expect SES throttling until fixed |
| Fixed-window bursts (Pitfall 4) | LOW/MEDIUM | Swap to token bucket / min-interval; no data loss, just re-tune |
| `GetSendQuota` stampede (Pitfall 5) | LOW | Add `Cache::lock` single-flight + longer soft TTL |
| Wrong Bug-2 exception type (Pitfall 8) | LOW | Introduce named exception; verify Horizon failure attribution |

## Pitfall-to-Phase Mapping

Phases are not yet defined for v1.1; mapping is expressed against the milestone's build steps so the roadmapper/planner can slot them.

| Pitfall | Prevention build step | Verification |
|---------|-----------------------|--------------|
| 1. Daily-quota false-positive | Throttle-detection fix (Bug-1) | Unit test: `Throttling` + "Daily message quota exceeded" is not retried as rate |
| 2. Double-send | Adapter-override + limiter + idempotency step | Fault-injection test killing worker between send and mark → no duplicate |
| 3. Not cross-process | Rate-limiter step | Multi-process/redis-key test asserts combined rate ≤ `MaxSendRate` |
| 4. Fixed-window burst | Rate-limiter step (algorithm) | Boundary-straddling burst test shows no 2× overshoot |
| 5. Quota stampede | Quota-sourcing/cache step | Concurrent cold-cache test → single `GetSendQuota` call + fallback |
| 6. Thundering herd | Limiter + back-off step | Jitter present; retries not time-clustered |
| 7. Blocking starves / timeout | Limiter step + Horizon config step | `timeout < retry_after`; bounded wait; release-before-send path exercised |
| 8. Wrong Bug-2 exception | Retry-exhaustion fix (Bug-2) | Named exception in `failed_jobs`; attempt count == `tries` |
| 9. MaxSendRate edge values | Quota-sourcing + limiter math | Tests for `-1`/unlimited, `0`, `0.2`, `1.0` (sandbox) |

## Sources

- Vendor code read end-to-end: `Traits/ThrottlesSending.php`, `Adapters/SesMailAdapter.php`, `Services/Messages/DispatchMessage.php`, `MarkAsSent.php`, `RelayMessage.php`, `Listeners/MessageDispatchHandler.php`; `config/horizon.php`, `config/queue.php`; `vendor/aws/aws-sdk-php/src/Exception/AwsException.php` (`getAwsErrorCode`). — Confidence HIGH
- AWS SES — throttling error codes (both "Maximum sending rate exceeded" and "Daily message quota exceeded" return the same `Throttling`/`ThrottlingException` code, differ only by message): [Errors related to sending quotas](https://docs.aws.amazon.com/ses/latest/dg/manage-sending-quotas-errors.html), [SES email sending errors](https://docs.aws.amazon.com/ses/latest/dg/troubleshoot-error-messages.html), [How to handle a "Throttling – Maximum sending rate exceeded" error](https://aws.amazon.com/blogs/messaging-and-targeting/how-to-handle-a-throttling-maximum-sending-rate-exceeded-error/), [Resolve max-rate vs daily-quota exceeded](https://repost.aws/knowledge-center/ses-sending-rate-quota-exceeded). — Confidence HIGH
- Laravel/Horizon worker `timeout` default 60s and `timeout < retry_after` requirement — Laravel queues documentation (job expiration/timeouts). — Confidence HIGH

---
*Pitfalls research for: coordinated SES rate limiting on Horizon + SES throttle-path bug fixes (v1.1)*
*Researched: 2026-07-25*
