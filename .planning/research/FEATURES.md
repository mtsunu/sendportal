# Feature Research

**Domain:** Amazon SES per-second send-rate reliability (Laravel/Horizon email dispatch)
**Researched:** 2026-07-25
**Confidence:** HIGH (AWS-doc-backed; one v2 error-code detail MEDIUM)

> Scope reminder: This milestone (v1.1) adds **only** proactive pacing to the SES **per-second** `MaxSendRate` plus two throttle-path bug fixes. The existing **daily** `Max24HourSend` pre-check (`QuotaService::exceedsSesQuota`, wired into `CampaignDispatchController`) is correct and stays as-is — it is **not** re-scoped here.

---

## AWS Technical Reference (authoritative — planner must build against these)

### `GetSendQuota` response fields (SES v1 — `Aws\Ses\SesClient::getSendQuota()`)

| Field | Type | Meaning | Notes for pacing |
|-------|------|---------|------------------|
| `Max24HourSend` | double | Max emails allowed per **rolling** 24h window. | `-1` = **unlimited** (already handled by `QuotaService`). Rolling, not calendar-day. |
| `MaxSendRate` | double | Max emails/sec SES **accepts** from the account. | **This milestone's target.** Is a **double — can be fractional** (sandbox is `1.0`; production commonly `14.0`, `50.0`, `200.0`…). SES lets you **burst briefly** above it but not **sustain** it. Actual accepted rate may be lower than the max. |
| `SentLast24Hours` | double | Emails sent in the previous 24h. | Used by the existing daily pre-check only. |

- Same three fields exist identically in **SESv2** (`API_SendQuota`), so the field contract is stable across API versions.
- **No `-1` semantics apply to `MaxSendRate`** in practice — it is a positive rate for any active account. Treat a missing/zero/absent `MaxSendRate` as a fetch failure (fall back conservatively), mirroring how `QuotaService` logs and bails when `getSendQuota()` returns empty.

### Throttling error — the single most important planning fact

SES v1 (`sendEmail`) throws `Aws\Ses\Exception\SesException`. **Two different limit conditions share the exact same AWS error code `Throttling`**, distinguished only by message text:

| Condition | `getAwsErrorCode()` | Message text | Retriable? | Reached in this app? |
|-----------|--------------------|--------------|-----------|----------------------|
| **Per-second rate exceeded** | `Throttling` | `Maximum sending rate exceeded` | **Yes** — transient; wait + retry succeeds | The condition this milestone paces away |
| **Daily 24h quota exceeded** | `Throttling` | `Daily message quota exceeded` | **No** (short-term) — SES **drops** the message, wait ~24h | Pre-empted by the existing daily pre-check; rare race only |

- **Implication for SES-03:** detecting on `getAwsErrorCode() === 'Throttling'` (instead of exact message-string match) is the right robustness fix — **but the code alone does not distinguish rate-exceeded from daily-quota-exceeded.** The planner must decide consciously (see Anti-Features + Dependency Notes). Because daily quota is already pre-checked, treating `Throttling` as a retriable rate-throttle is acceptable; on the rare daily-quota race the retries simply exhaust and surface an exception (which is the desired SES-04 behavior anyway).
- SMTP interface renders the same as `454 Throttling failure: <message>` (not relevant — the adapter uses the API, not SMTP).
- **Sandbox limits are a different failure class**, not `Throttling`: sending to an unverified recipient in sandbox returns `MessageRejected` ("Email address is not verified"). Sandbox does impose `MaxSendRate = 1.0` and `Max24HourSend = 200`, so exceeding the sandbox **rate** still yields the normal `Throttling` / "Maximum sending rate exceeded" — i.e. pacing to `MaxSendRate` transparently respects sandbox too.

### v1 (`SesClient`) vs v2 (`SesV2Client`) — we stay on v1

| Aspect | SES v1 (`SesClient`, this app) | SESv2 (`SesV2Client`) |
|--------|-------------------------------|-----------------------|
| GetSendQuota fields | `Max24HourSend`/`MaxSendRate`/`SentLast24Hours` | **Identical** field names/semantics |
| Throttle error code | `Throttling` (message "Maximum sending rate exceeded") | `TooManyRequestsException` (HTTP 429) — **different code** [MEDIUM confidence] |
| Exception class | `Aws\Ses\Exception\SesException` | `Aws\SesV2\Exception\SesV2Exception` |

**Decision:** the vendor adapter (`SesMailAdapter`) uses `SesClient` (v1), so `Throttling` is the authoritative code to detect. Do **not** switch API versions — that would be scope creep and would change the error-code contract.

### `MaxSendRate` stability & caching (~5 min) — verdict: SAFE to cache

- AWS **auto-ramps** sending limits upward as account reputation builds; increases happen on the order of **hours/days**, never per-second. Decreases are rare and operator/AWS-initiated.
- A 5-minute cache therefore risks at most: (a) briefly pacing slightly **under** a freshly-raised limit — safe, self-corrects next fetch; (b) briefly pacing slightly **over** a freshly-lowered limit — covered by the reactive `Throttling` backoff that remains as a safety net.
- **Conclusion:** cache `MaxSendRate` ~5 min (per SES-02). No config override needed; the live value is the source of truth. Cache the whole `getSendQuota()` result to also serve the existing daily pre-check without a second API call.

---

## Feature Landscape

### Table Stakes (required for a "reliable SES sender")

| Feature | Why Expected | Complexity | Notes |
|---------|--------------|------------|-------|
| **Proactive per-second pacing to `MaxSendRate`** (SES-01) | A well-behaved sender stays under its accepted rate so SES near-never returns `Throttling`; reactive-only backoff wastes worker time and risks message loss. | HIGH | Must be **coordinated across all Horizon workers** (≤20 procs) — a per-process limiter lets N workers each send at the full rate → N× overshoot. Needs a shared/Redis-backed token/rate primitive. |
| **Source rate live from `getSendQuota()['MaxSendRate']`, cached ~5 min** (SES-02) | Hard-coding or config-overriding the rate drifts from the account's real (auto-ramped) limit. | LOW–MEDIUM | Reuse the existing adapter `getSendQuota()`; cache result (Redis) ~5 min; conservative fallback if fetch fails. |
| **Detect throttle by AWS error code `Throttling`** (SES-03) | Exact message-string match (`== 'Maximum sending rate exceeded.'`) is brittle — punctuation/wording drift silently breaks throttle handling and rethrows a retriable error. | LOW | Switch to `SesException::getAwsErrorCode() === 'Throttling'`. See daily-quota overlap caveat above. |
| **Fail with a clear exception on retry exhaustion** (SES-04) | Current loop falls through and returns `null`; `send(): string` then throws an opaque `TypeError` instead of a meaningful failure the queue can retry. | LOW | After N attempts, `throw` a descriptive exception; let Horizon's job retry/backoff handle redelivery. |
| **No dropped or duplicated messages under throttling** | Core correctness expectation of any mail sender. | (property of above) | Pacing + clean exception + queue retry must not double-send or silently drop. Verify idempotency of the retry path. |
| **Preserve existing daily-quota pre-check unchanged** | It is correct and prevents the non-retriable daily `Throttling` case entirely. | NONE (leave as-is) | Explicitly out of rescope; just don't regress it. |
| **No editing of `vendor/mettle/sendportal-core`** | Milestone constraint: host-level override only, upgrade-safe. | MEDIUM | Override the adapter/trait behavior from the host app (e.g. bind a host subclass / decorator) rather than patching vendor files. |

### Differentiators / Nice-to-have (defer unless cheap)

| Feature | Value Proposition | Complexity | Notes |
|---------|-------------------|------------|-------|
| Log/metric line when pacing kicks in or rate is refreshed | Operator visibility into effective rate and throttle frequency. | LOW | Keep to a single info log at cache refresh + count of throttle retries — do not build a dashboard. Acceptable if trivial. |
| Surface effective `MaxSendRate` in UI | Operator sees the live cap. | MEDIUM | **Feature creep for a focused fix** — defer. Not needed for reliability. |
| Per-configuration-set / dedicated-IP-pool rate awareness | Fine-grained pacing for advanced setups. | HIGH | Out of scope; account-level `MaxSendRate` is the correct v1.1 granularity. |
| Adaptive/dynamic backoff tuning | Marginally fewer retries. | MEDIUM | Existing exponential backoff (`resolveSleepDuration`) is adequate as a safety net; don't rewrite. |

### Anti-Features (avoid — scope creep or incorrect)

| Feature | Why Requested | Why Problematic | Alternative |
|---------|---------------|-----------------|-------------|
| Retry **all** `Throttling` errors indefinitely | "Throttling is retriable" | Daily-quota-exceeded **also** carries code `Throttling` but is **not** short-term retriable — infinite/long retry burns workers and never succeeds. | Bounded attempts (existing 10-cap) → then throw (SES-04); rely on daily pre-check to keep the daily case out of the hot path. |
| Making the rate a config/env override | "Let operators tune it" | Drifts from the account's real auto-ramped limit; invites over-sending and reputation damage. | Source live from `getSendQuota()`, cache ~5 min (SES-02). No override. |
| Migrating to SESv2 (`SesV2Client`) for "modern" throttling | v2 is newer | Changes the error-code contract (`TooManyRequestsException`), breaks vendor-adapter parity, large blast radius for a focused fix. | Stay on v1; detect `Throttling`. |
| Client-side sleeping inside the request thread as the *primary* control | Simple to add | Blocks a Horizon worker per message; doesn't coordinate across workers → still overshoots collectively. | Shared Redis rate limiter that gates before send; keep reactive sleep only as a fallback. |
| Rebuilding/expanding the daily-quota check | "While we're in here" | Explicitly out of scope; the daily check is correct. | Leave `QuotaService` untouched. |
| Rich metrics/dashboard/alerting for send rate | "Observability" | Non-trivial UI/infra work orthogonal to the reliability fix. | At most one log line; defer real observability. |

## Feature Dependencies

```
SES-01 Coordinated pacing to MaxSendRate
    └──requires──> SES-02 Live cached MaxSendRate source (need a value to pace to)
    └──requires──> shared/Redis rate primitive (coordination across ≤20 workers)

SES-04 Clean exception on retry exhaustion
    └──requires──> SES-03 Code-based Throttling detection (must know it's a throttle before retry/exhaust)

SES-01 (proactive pacing) ──reduces load on──> SES-03/SES-04 (reactive path becomes rare safety net)

SES-03 code == 'Throttling' ──conflicts-with──> daily-quota case (same code, not short-retriable)
      (mitigated by the preserved daily pre-check)
```

### Dependency Notes

- **SES-01 requires SES-02:** you cannot pace to a rate you haven't sourced; the cached `MaxSendRate` is the pacing target. Build SES-02 first (or together).
- **SES-01 requires a shared coordination primitive:** Horizon runs multiple worker processes (config declares queues incl. `sendportal-message-dispatch`); a per-process limiter multiplies the send rate by the worker count. Redis-backed limiter is the correct mechanism.
- **SES-04 requires SES-03:** the exhaustion-exception path lives inside the same throttle-handling logic that SES-03 rewrites; do them in the same unit of work (both live in `ThrottlesSending`).
- **SES-03 vs daily-quota overlap:** documented above — same `Throttling` code. The preserved daily pre-check (`QuotaService`) is what makes code-only detection safe; call this out in the plan so it isn't "fixed" independently.
- **Latent bug to note for SES-03:** the current match is `$e->getMessage() == 'Maximum sending rate exceeded.'` (trailing period). AWS documents the message as "Maximum sending rate exceeded" — an exact-string match is brittle regardless of the exact punctuation on the wire; this is concrete evidence for switching to code-based detection. (I did not byte-verify the live wire string; treat the period as a possible mismatch, not a confirmed one.)

## MVP Definition

### Launch With (v1.1)

- [ ] **SES-02** — live `MaxSendRate` sourced from `getSendQuota()`, cached ~5 min — everything paces to this.
- [ ] **SES-01** — Redis-coordinated pacing to `MaxSendRate` across all workers — the core reliability outcome.
- [ ] **SES-03** — detect throttle via `getAwsErrorCode() === 'Throttling'`, not message string.
- [ ] **SES-04** — throw a clear exception on retry exhaustion (no `null` → `TypeError`); let queue retry.
- [ ] Host-level override (no `vendor/` edits) covering the adapter/trait behavior.

### Add After Validation (v1.x)

- [ ] Minimal single-line log at rate-refresh + throttle-retry count — only if trivial.

### Future Consideration (v2+)

- [ ] Per-configuration-set / dedicated-IP rate granularity — defer until account-level pacing proves insufficient.
- [ ] Operator-facing UI showing effective send rate — defer; not a reliability requirement.

## Feature Prioritization Matrix

| Feature | User Value | Implementation Cost | Priority |
|---------|------------|---------------------|----------|
| SES-01 coordinated pacing | HIGH | HIGH | P1 |
| SES-02 live cached rate | HIGH | LOW | P1 |
| SES-03 code-based throttle detection | HIGH | LOW | P1 |
| SES-04 clean exhaustion exception | HIGH | LOW | P1 |
| Single info log line | LOW | LOW | P3 |
| Rate in UI | LOW | MEDIUM | P3 |
| SESv2 migration | LOW (negative) | HIGH | Do-not |

**Priority key:** P1 must have for this milestone · P2 should have · P3 defer.

## Operator-visible "good" behavior (acceptance signal)

- A campaign sends **steadily at ~`MaxSendRate`** (e.g. ~14/sec), not in a bursty spike-then-throttle sawtooth.
- **Near-zero** `Throttling`/"Maximum sending rate exceeded" errors in logs (the reactive path is a rare safety net, not the norm).
- Adding more Horizon workers does **not** increase the aggregate send rate beyond `MaxSendRate` (coordination proof).
- **No dropped and no duplicated** messages; a genuinely failed send surfaces a clear exception and is retried by the queue, not swallowed as a `TypeError`.

## Sources

- [GetSendQuota — Amazon SES API Reference (v1)](https://docs.aws.amazon.com/ses/latest/APIReference/API_GetSendQuota.html) — field semantics, `-1` = unlimited — HIGH
- [SendQuota — Amazon SES API Reference V2](https://docs.aws.amazon.com/ses/latest/APIReference-V2/API_SendQuota.html) — identical field contract across API versions — HIGH
- [Errors related to the sending quotas for your Amazon SES account](https://docs.aws.amazon.com/ses/latest/dg/manage-sending-quotas-errors.html) — "Maximum sending rate exceeded" vs "Daily message quota exceeded", both under `Throttling`; 454 SMTP form — HIGH
- [How to handle a "Throttling – Maximum sending rate exceeded" error (AWS Messaging Blog)](https://aws.amazon.com/blogs/messaging-and-targeting/how-to-handle-a-throttling-maximum-sending-rate-exceeded-error/) — retriable, back off up to 10 min, reduce concurrency/rate — HIGH
- [Amazon SES email sending errors](https://docs.aws.amazon.com/ses/latest/dg/troubleshoot-error-messages.html) — error classification incl. sandbox `MessageRejected` — HIGH
- [get_send_quota — Boto3 documentation](https://boto3.amazonaws.com/v1/documentation/api/latest/reference/services/ses/client/get_send_quota.html) — field types (double), rolling-window semantics — HIGH
- Local vendor code: `vendor/mettle/sendportal-core/src/Traits/ThrottlesSending.php` (brittle string match `== 'Maximum sending rate exceeded.'`, `null`-return-after-10-attempts bug), `src/Services/QuotaService.php` (preserved daily pre-check, `-1` handling), `src/Adapters/SesMailAdapter.php` (`SesClient` v1, `getSendQuota()`, `send(): string`) — HIGH

---
*Feature research for: Amazon SES per-second send-rate reliability*
*Researched: 2026-07-25*
