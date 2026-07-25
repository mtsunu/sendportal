# Roadmap: SendPortal PHP 8.4 Compatibility

## Milestones

- ✅ **v1.0 PHP 8.4 Compatibility** — Phases 1-3 (shipped 2026-07-25)
- 🚧 **v1.1 SES Sending Reliability** — Phase 4 (in progress)

## Phases

<details>
<summary>✅ v1.0 PHP 8.4 Compatibility (Phases 1-3) — SHIPPED 2026-07-25</summary>

- [x] Phase 1: Constraint Resolution and Security Control (12/12 plans) — completed 2026-07-24, verified passed (7/7 truths)
- [x] Phase 2: Reproducible Dependency Snapshot (2/2 plans) — completed 2026-07-24
- [x] Phase 3: PHP 8.4 Runtime, Core Integration, and CI Verification (1/1 plan) — completed 2026-07-25

Full details archived in `.planning/milestones/v1.0-ROADMAP.md`.

</details>

### 🚧 v1.1 SES Sending Reliability (Phase 4)

- [x] **Phase 4: Coordinated SES rate limiting + 2 bug fixes** — Pace SES sends to the account's per-second MaxSendRate across all workers and fix the throttle-path misclassification and null-return bugs, via a host-level adapter override. (completed 2026-07-25)

## Phase Details

### Phase 4: Coordinated SES rate limiting + 2 bug fixes
**Goal**: SES campaign sends are proactively paced to the account's per-second `MaxSendRate`, coordinated (Redis-backed, genuinely cross-process) across all Horizon `sendportal-message-dispatch` workers (≤20), and the two latent throttle-path bugs — throttle-code misclassification and the `null`-return `TypeError` on retry exhaustion — are fixed. Delivered entirely as a host-level override (`app/Mail/ThrottledSesAdapter` rebound via `MailAdapterFactory::$adapterMap` in `AppServiceProvider::boot()`), with no `vendor/` edits and no new Composer dependency.
**Depends on**: Nothing new (builds on the shipped v1.0 PHP 8.4 runtime and install contract)
**Requirements**: SES-01, SES-02, SES-03, SES-04, SES-05
**Success Criteria** (what must be TRUE):
  1. Under a simulated fleet of concurrent workers, combined SES `sendEmail` calls never exceed `MaxSendRate`/sec — the limiter is proven cross-process (shared Redis key), not per-process, by a ≥2-worker verification test.
  2. A `Throttling` rate-exceeded error is retried and the message eventually sends; a `Throttling` daily-quota-exceeded error fails fast without local retry.
  3. Retry exhaustion surfaces a named exception (`SesSendThrottledException`), never a `TypeError` from a `null` return, and the affected message is not marked sent — Horizon (`tries=3`) is the single retry owner.
  4. Fault injection between the SES call and `markSent()` does not double-send: all waiting happens *before* the SES call and the block is bounded well below the worker timeout (`< 60s`).
  5. No `vendor/` files are changed and no new Composer dependency is added; `MaxSendRate` is sourced live from `getSendQuota()`, cached ~5 min with safe edge-value handling (`-1`/unlimited/`0`/fractional/sandbox `1.0`) and single-flight refresh; the existing `QuotaService` daily pre-check is unchanged.
**Plans**: 1 plan
- [x] 04-01-PLAN.md — Coordinated SES rate limiting + 2 bug fixes: tracer-first ThrottledSesAdapter (host override, Redis DurationLimiter pacing, cached MaxSendRate, throttle-code sub-branch, named exhaustion exception) rebound via AppServiceProvider; full SES-01..05 test suite.

**Build order** (internal to this phase, not separate phases):
1. Config + quota sourcing — `config/sendportal-throttle.php`, cached `MaxSendRate` read (`md5(region|access-key-id)` key), edge-value guards, single-flight + last-known-good fallback (SES-02).
2. Adapter + limiter + throttle detection + exhaustion exception — `SesSendThrottledException`, `ThrottledSesAdapter` (wholesale `send()` override, bounded `Redis::throttle(...)->block(15)->then()`, code-`Throttling` gate sub-branched on message for rate-vs-daily-quota, named exhaustion exception), one-line `AppServiceProvider` rebind (SES-01, SES-03, SES-04, SES-05).
3. Verification hardening — cross-process (≥2 worker) limiter test, double-send fault-injection test, rate-vs-daily-quota branch test, exhaustion-throws-named-exception test, store-is-Redis and timeout-ordering assertions (SES-05 verification).

## Progress

| Phase | Milestone | Plans Complete | Status | Completed |
|-------|-----------|----------------|--------|-----------|
| 1. Constraint Resolution and Security Control | v1.0 | 12/12 | Complete | 2026-07-24 |
| 2. Reproducible Dependency Snapshot | v1.0 | 2/2 | Complete | 2026-07-24 |
| 3. PHP 8.4 Runtime, Core Integration, and CI Verification | v1.0 | 1/1 | Complete | 2026-07-25 |
| 4. Coordinated SES rate limiting + 2 bug fixes | v1.1 | 1/1 | Complete   | 2026-07-25 |
