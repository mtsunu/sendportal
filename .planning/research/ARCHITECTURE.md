# Architecture Research

**Domain:** SES sending-rate coordination via host-level adapter override (Laravel 11 + `mettle/sendportal-core` v3.0.2, Horizon/Redis workers)
**Researched:** 2026-07-25
**Confidence:** HIGH — every load-bearing claim verified against vendor source (`SesMailAdapter`, `MailAdapterFactory`, `ThrottlesSending`, `MessageDispatchHandler`, `DispatchMessage`, `RelayMessage`, `EventServiceProvider`), framework source (`DurationLimiterBuilder::block`, `LimiterTimeoutException`, `AwsException::getAwsErrorCode`), and committed config (`horizon.php`, `queue.php`).

## Standard Architecture

### Send path (verified) and the two injection points

```
MessageDispatchEvent (fired in vendor)
   │  Laravel event dispatcher → serialized CallQueuedListener job
   ▼
[queue: sendportal-message-dispatch]   Horizon supervisor-2, balance=auto, min 2 / MAX 20, tries=3
   │
   ▼
MessageDispatchHandler::handle()   ← VENDOR, ShouldQueue. Owns the $job. Cannot edit. ← wiring point B
   ▼
DispatchMessage::handle() → RelayMessage::handle()
   ▼
MailAdapterFactory::adapter($emailService)
   │  resolve(): $adapterClass = self::$adapterMap[type_id];  new $adapterClass($emailService->settings)
   │  PUBLIC STATIC map, lazy read at send-time, NO container DI       ← injection point A (CHOSEN)
   ▼
SesMailAdapter::send()  →  use ThrottlesSending::throttleSending()  →  SesClient::sendEmail()
                                    ▲ both bugs (SES-03/04) live in this trait
```

Two facts fix the whole design:

1. **Injection point A (adapter class) is the only boot-safe, DI-free seam.** `MailAdapterFactory::$adapterMap` is `public static` and is read *lazily at dispatch time* inside `resolve()` (verified lines 22–30, 64–70). Reassigning one entry in `AppServiceProvider::boot()` is durable regardless of provider boot order, because nothing reads the map during boot.
2. **The adapter has no access to the queued job.** `resolve()` calls `new $adapterClass($emailService->settings)` — it passes *only `settings`*, never the `EmailService` id and never the job. `send()` therefore cannot call `$job->release()`. Any "release-to-queue" behaviour must be reached indirectly (point B), which is vendor-owned.

### Component responsibilities (target design)

| Component | Responsibility | Implementation |
|-----------|----------------|----------------|
| `App\Mail\ThrottledSesAdapter` | Proactive coordinated pacing + fixed throttle handling. **Replaces** `send()` wholesale (does NOT call `parent::send()`). | extends `Sendportal\Base\Adapters\SesMailAdapter` |
| Coordinated limiter | Global per-second admission across ≤20 workers | `Redis::throttle()` duration limiter with native `->block()` |
| MaxSendRate source | Per-account/region send rate, cached ~5 min | `Cache::remember()` wrapping inherited `getSendQuota()` |
| `AppServiceProvider::boot()` | Rebind SES adapter class in the static map | one line, existing file |
| `SesSendThrottledException` (new) | Clear failure on limiter-timeout / retry-exhaustion so the vendor job's `tries=3` retries then fails loudly | host exception class |
| `config/sendportal-throttle.php` (new, optional) | `max_block_seconds`, `rate_cache_ttl`, local-retry knobs | plain config array |

## The central decision: BLOCK vs RELEASE

**Resolved: BLOCK as the primary pacing mechanism, RELEASE-via-throw only as the rare overflow valve.** This is a hybrid that sidesteps the vendor-ownership constraint entirely.

### Why release-to-queue at the adapter layer is not viable — every host-level path enumerated

The throttle point (`send()`) has no `$job`. To get real `release($delay)` behaviour without editing vendor, these are the only paths:

| # | Mechanism | Feasible without vendor edit? | Verdict |
|---|-----------|-------------------------------|---------|
| 1 | **Throw from `send()`; let the queue retry** | Yes — exception bubbles `send → RelayMessage → DispatchMessage::handle → MessageDispatchHandler::handle → CallQueuedListener` fails. | Works, **but every throw consumes one of `tries=3`**. A busy campaign burns 3 tries in milliseconds → message lands in `failed_jobs`. Unusable as the *steady* pacing loop; fine only as a rare exhaustion signal. |
| 2 | **Queue/job middleware** (`Illuminate\Queue\Middleware\RateLimited` → `$job->release()` without consuming a try) | **No.** `CallQueuedListener::middleware()` reads `method_exists($listener,'middleware') ? $listener->middleware() : []`. Middleware lives on the *listener* (`MessageDispatchHandler`), which is vendor. No global hook exists to attach middleware to someone else's listener. | Not feasible — would require editing the vendor listener. |
| 3 | **Own the event→listener wiring** — `Event::forget(MessageDispatchEvent::class)` then register a host listener/job with its own `tries`, `backoff`, and `middleware()` | Partially. `Event::forget` + host listener is technically possible, but (a) **boot-order fragile**: the host `AppServiceProvider`/`EventServiceProvider` may boot *before* the vendor `EventServiceProvider` registers `MessageDispatchHandler`, so the forget misses it and you get **double-send**; (b) the host listener would have to re-implement or re-invoke the vendor `DispatchMessage` orchestration; (c) far larger blast radius than a one-line map swap. | Feasible but heavy and risky. **Rejected**; kept only as documented fallback. |
| 4 | **Custom exception + a host `Queue::failing` / global exception handler that re-dispatches** | Possible but re-dispatch loses the reserved-job semantics and re-fires the event from scratch; equivalent to #1 in cost, more moving parts. | Rejected. |

**Conclusion:** the only *clean* seam (injection point A, the static map) reaches solely the adapter, which cannot release the job cheaply. Therefore the pacing must happen **inside `send()`**, and the natural pacing primitive there is a **bounded block**. Release-via-throw (#1) is retained *only* for the two genuinely-exceptional exits (limiter wait exceeded, or local SES-throttle retries exhausted), where riding `tries=3` → `failed_jobs` is the correct loud-failure behaviour and directly satisfies **SES-04**.

### Making BLOCK safe against Horizon timeouts and `tries=3`

Blocking ties up one of ≤20 supervisor-2 slots, so the wait must be bounded and provably shorter than the reservation/timeout windows.

- **Native bounded block.** Use `Redis::throttle("sp:ses:rate:{key}")->allow($rate)->every(1)->block($maxBlockSeconds)->then($send)`. Verified: `DurationLimiterBuilder::block($timeout)` exists and `DurationLimiter` sleeps (~750ms) polling for a slot, throwing `Illuminate\Contracts\Redis\LimiterTimeoutException` if the window elapses. Coordination is global via one Redis key — this is the documented Laravel job rate-limiting primitive.
- **Bound below the reservation window.** `config/queue.php` `redis.retry_after = 90` (verified) and Horizon worker `timeout` default 60s. **Set `max_block_seconds = 15`** (comfortably `< 60 < 90`), so a blocked worker can never exceed `retry_after` and trigger duplicate processing by a second worker, and never hit the worker timeout. Actual SES `sendEmail` latency (tens–hundreds of ms) plus a ≤15s block stays well inside both.
- **Starvation math.** Steady-state per-worker wait ≈ `concurrency / rate`. At the SES sandbox floor (rate≈1/s) with 20 workers that is ~20s > 15s cap, so under extreme under-provisioning some jobs will hit the cap and take path #1 (throw → retry) — acceptable degradation, and Horizon `balance=auto` will also scale supervisor-2 *down* toward `minProcesses=2` when the queue drains slowly, naturally relieving the contention. At realistic rates (14, 50, 200/s) the block is sub-second and no job approaches the cap.
- **Interaction with `tries=3`.** The steady path never throws, so it never consumes tries. Only limiter-timeout or SES-throttle-exhaustion throws `SesSendThrottledException`; the vendor job then retries (up to 3) and, if still failing, fails loudly to `failed_jobs`. No `null`-return `TypeError` (SES-04) because we never fall off the end of a retry loop returning `null`.

## Limiter key and MaxSendRate cache placement

**Limiter key — derive from settings, not `email_service` id.** The adapter never receives the id (only `settings`). Correctly, SES `MaxSendRate` is an **account-per-region** quota, so two `email_service` rows sharing one SES account *should* share one limiter. Key on a stable hash of the throttle-relevant settings:

```
$key = md5(($this->config['region'] ?? '') . '|' . ($this->config['key'] ?? ''));   // region + access-key-id
// limiter:  "sp:ses:rate:{$key}"      rate-cache: "sp:ses:maxrate:{$key}"
```

This is *more* correct than per-`email_service` isolation and needs no factory override.

**MaxSendRate read — inside `send()`, cached ~5 min (SES-02).** `getSendQuota()` is an inherited public method (returns `['MaxSendRate' => float, ...]`). Read it lazily and cache it; never at boot (no SES creds available, and quota drifts):

```php
$rate = (int) max(1, floor(
    Cache::remember("sp:ses:maxrate:{$key}", now()->addSeconds(300),
        fn () => $this->getSendQuota()['MaxSendRate'])
));
```

Floor to an integer ≥1 because the duration limiter's `allow($n)` is an integer per-second count; a sandbox `1.0` still yields a working 1/s limiter.

## AppServiceProvider static-map rebind — soundness

**Sound and boot-safe.** The rebind is a single assignment in the existing `AppServiceProvider::boot()`:

```php
use Sendportal\Base\Factories\MailAdapterFactory;
use Sendportal\Base\Models\EmailServiceType;
// ...
MailAdapterFactory::$adapterMap[EmailServiceType::SES] = \App\Mail\ThrottledSesAdapter::class;
```

Why it is safe: (1) the map is `public static`, mutation is allowed; (2) `resolve()` reads it *lazily at dispatch time*, so no provider-boot-order dependency (unlike the `Event::forget` alternative); (3) the factory instantiates `new $adapterClass($settings)` — our subclass's constructor is the inherited `BaseMailAdapter(array $config)`, signature-compatible, so instantiation is unchanged; (4) blast radius is exactly the SES adapter — SMTP/Mailgun/etc. entries untouched. Register it in `boot()` (not `register()`) so it runs after the package's providers have registered the factory binding.

## Recommended `ThrottledSesAdapter::send()` shape

Override `send()` **without calling `parent::send()`** — the parent re-enters the buggy `ThrottlesSending` trait. Reuse only the safe inherited helpers (`resolveClient()`, `resolveMessageId()`, `getSendQuota()`, `$this->config`, all `protected`/`public`).

```
send():
  key   = md5(region|access-key-id)
  rate  = Cache::remember("sp:ses:maxrate:$key", 300, getSendQuota()['MaxSendRate'])   // SES-02
  try {
    return Redis::throttle("sp:ses:rate:$key")->allow(rate)->every(1)
                 ->block(max_block_seconds)                                            // SES-01 (proactive, coordinated, bounded)
                 ->then(fn () => $this->doSend(...));
  } catch (LimiterTimeoutException) { throw new SesSendThrottledException(...); }       // rare overflow → tries=3

doSend(...):
  attempts = 0
  loop (attempts < local_max):
    try { return resolveMessageId($this->resolveClient()->sendEmail([...payload from vendor send()...])); }
    catch (SesException $e):
      if ($e->getAwsErrorCode() === 'Throttling') { attempts++; usleep(backoff); continue; }   // SES-03 (error code, not string)
      throw $e;
  throw new SesSendThrottledException('SES throttle retries exhausted');                          // SES-04 (clear, no null)
```

Notes: `getAwsErrorCode()` is verified on `AwsException` (parent of `SesException`); `'Throttling'` is SES's throttle error code. The proactive limiter makes the inner `doSend` retry a thin safety net rather than the main defence.

## Recommended build order (exact host files)

1. **`config/sendportal-throttle.php`** (new) — `max_block_seconds` (default 15), `rate_cache_ttl` (300), `local_max_retries`, `key_prefix`. Pure data; no dependencies.
2. **`app/Mail/SesSendThrottledException.php`** (new) — thin `extends \RuntimeException`. Needed by the adapter's failure paths.
3. **`app/Mail/ThrottledSesAdapter.php`** (new) — the override above. Depends on 1 + 2. This is the core of the milestone (SES-01..04).
4. **`app/Providers/AppServiceProvider.php`** (modify) — add the one-line static-map rebind in `boot()`. Depends on 3 existing.
5. **Tests** — unit-test `doSend` throttle detection/exhaustion with a mocked `SesClient` (SES-03/04); feature/integration-test that concurrent `send()` calls are paced by the Redis limiter and that the map rebind routes SES through `ThrottledSesAdapter` (SES-01/02). Redis must be available in the test env for the limiter path (Horizon/queue already assume Redis).

**Do not touch:** anything under `vendor/mettle/sendportal-core` (host-override constraint), the vendor `EventServiceProvider` wiring, or `config/horizon.php` supervisor topology (the design works within the existing ≤20 slots).

## Anti-patterns to avoid

- **Calling `parent::send()`** — re-enters the buggy `ThrottlesSending` trait; the override must reimplement the send payload with fixed handling.
- **`Event::forget` + host listener** — boot-order fragile, risks double-send, large blast radius. Only a last resort if a future requirement truly needs per-job `release()` without a try penalty.
- **Keying the limiter on `email_service` id** — not available at the adapter (only `settings` is passed) and semantically wrong; SES quota is per account/region.
- **Reading `MaxSendRate` at boot or per-send uncached** — no creds at boot; uncached adds a `getSendQuota` API round-trip to every message. Cache ~5 min inside `send()`.
- **Unbounded block or block ≥ `retry_after`/timeout** — a block ≥60s trips the worker timeout and ≥90s trips `retry_after` → duplicate sends. Cap at 15s.

## Sources

- Vendor source (read in-repo, HIGH): `vendor/mettle/sendportal-core/src/Factories/MailAdapterFactory.php`, `.../Adapters/SesMailAdapter.php`, `.../Adapters/BaseMailAdapter.php`, `.../Traits/ThrottlesSending.php`, `.../Listeners/MessageDispatchHandler.php`, `.../Services/Messages/{DispatchMessage,RelayMessage}.php`, `.../Providers/EventServiceProvider.php`.
- Framework source (read in-repo, HIGH): `Illuminate/Redis/Limiters/DurationLimiter*.php` (`block()` + `LimiterTimeoutException`), `Illuminate/Contracts/Redis/LimiterTimeoutException.php`, `aws/aws-sdk-php/src/Exception/AwsException.php` (`getAwsErrorCode()`).
- Config (read in-repo, HIGH): `config/horizon.php` (supervisor-2 min2/max20, tries=3), `config/queue.php` (`redis.retry_after=90`), `app/Providers/AppServiceProvider.php`.
