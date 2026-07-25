# Requirements: SendPortal — v1.1 SES Sending Reliability

**Defined:** 2026-07-25
**Core Value:** Operators can run SendPortal on PHP 8.4 reliably — campaign delivery via Amazon SES respects the account's sending limits without operator intervention or wasted throttle errors.

## v1.1 Requirements

Requirements for this milestone. Each maps to roadmap phases. Scope is a focused
reliability fix delivered via a host-level override — no `vendor/` edits, no new
Composer dependencies, no product-UI change.

### SES Sending Rate

- [ ] **SES-01**: SES campaign sending is proactively paced to the account's per-second max send rate, coordinated (Redis-backed, genuinely cross-process) across all Horizon `sendportal-message-dispatch` workers (≤20 processes) — combined throughput never exceeds the account rate.
- [ ] **SES-02**: The per-second send rate is sourced from SES `getSendQuota()['MaxSendRate']` and cached (~5 min), with safe handling of unlimited (`-1`), zero, fractional, and sandbox (`1.0`) values, and single-flight refresh to avoid a cache-stampede across workers.
- [ ] **SES-03**: SES throttle responses are detected by AWS error code (`Throttling`) and correctly disambiguated — the per-second rate case is retried, while the daily-quota-exceeded case (same code, different message) fails fast instead of being retried.
- [ ] **SES-04**: When send retries are exhausted, sending fails with a clear named exception (never the current `null`-return `TypeError`), and Horizon (`tries=3`) is the single retry owner (no 10×loop × 3-tries retry amplification).
- [ ] **SES-05**: Rate-limiting pacing introduces no duplicate sends — all waiting happens *before* the SES call (never between `send()` and `markSent()`), and the block is bounded well below the worker timeout (`< 60s`), verified by fault-injection.

## Future Requirements

Deferred to a future release. Tracked but not in this roadmap.

### SES Sending Rate

- **SES-06**: Custom Redis-Lua token-bucket limiter to eliminate fixed-window edge bursts — only if the bundled `DurationLimiter` (fixed window) actually trips SES throttling under production load.
- **SES-07**: App-level idempotency marker beyond `sent_at` to dedupe a crash landing in the `send()`→`markSent()` gap (SES v1 `sendEmail` has no idempotency token).

### Quality / CI (separate future milestone)

- **HARD-01**: CI records a concise dependency-upgrade evidence summary (PHP/Composer versions, audit result, DB-matrix outcomes).
- **HARD-02**: A minimal tenant-safe SendPortal Core behavior smoke test covers one representative package flow under PHP 8.4.
- **HARD-03**: Repair static analysis and application coverage configuration.

## Out of Scope

Explicitly excluded. Documented to prevent scope creep.

| Feature | Reason |
|---------|--------|
| Migrating to the AWS SESv2 client (`SesV2Client`) | Changes the API/error contract (`TooManyRequestsException` / HTTP 429); v1 `SesClient` + `Throttling` code is sufficient for pacing. |
| Config/env override for the send rate | Rate is authoritatively sourced from SES (`MaxSendRate`); a manual override invites drift from the real account limit. |
| UI display of quota/rate or per-message tracking changes | This is a delivery-reliability milestone, not a product-UI change. |
| Changing the daily-quota pre-check (`Max24HourSend` via `QuotaService::exceedsQuota`) | Already correct and proactive; the daily quota is a separate concern from the per-second rate. |
| Editing `vendor/mettle/sendportal-core/**` | Host-level override only — keeps the dependency upgradable and preserves the v1.0 install contract. |
| New Redis idempotency key beyond `sent_at` | Deferred to SES-07 unless the SES-05 fault-injection test proves the bounded-block invariant is insufficient. |

## Traceability

Which phases cover which requirements. Finalized during roadmap creation.

| Requirement | Phase | Status |
|-------------|-------|--------|
| SES-01 | Phase 4 | Pending |
| SES-02 | Phase 4 | Pending |
| SES-03 | Phase 4 | Pending |
| SES-04 | Phase 4 | Pending |
| SES-05 | Phase 4 | Pending |

**Coverage:**
- v1.1 requirements: 5 total
- Mapped to phases: 5
- Unmapped: 0 ✓

---
*Requirements defined: 2026-07-25*
*Last updated: 2026-07-25 after milestone v1.1 definition*
