---
gsd_state_version: 1.0
milestone: v1.1
milestone_name: SES Sending Reliability
status: Awaiting next milestone
stopped_at: Completed 04-01-PLAN.md — SES-01..05 green vs real Redis; full DB suite + php-cs-fixer are CI-only env gaps
last_updated: "2026-07-25T14:48:28.881Z"
last_activity: 2026-07-25
last_activity_desc: Milestone v1.1 completed and archived
progress:
  total_phases: 1
  completed_phases: 1
  total_plans: 1
  completed_plans: 1
  percent: 100
---

# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-07-25)

**Core value:** Operators can install and run SendPortal reliably on PHP 8.4 without bypassing dependency or platform requirements. For v1.1: campaign delivery via Amazon SES respects the account's per-second sending limit automatically, coordinated across all workers.
**Current focus:** None — v1.0 and v1.1 shipped 2026-07-25. Plan the next milestone with `/gsd-new-milestone`.

## Current Position

Phase: Milestone v1.1 complete
Plan: —
Status: Awaiting next milestone
Last activity: 2026-07-25 — Milestone v1.1 completed and archived

## Performance Metrics

**Velocity:**

- Total plans completed: 3
- Average duration: -
- Total execution time: 0 hours

**By Phase:**

| Phase | Plans | Total | Avg/Plan |
|-------|-------|-------|----------|
| 02 | 2 | - | - |
| 3 | 1 | - | - |

**Recent Trend:**

- Last 5 plans: -
- Trend: Not established

**Per-Plan Metrics:**

| Plan | Duration | Tasks | Files |
|------|----------|-------|-------|
| Phase 01 P01 | 6m | 1 tasks | 1 files |
| Phase 01 P02 | 7m | 2 tasks | 2 files |
| Phase 01-constraint-resolution-and-security-control P03 | 21m | 2 tasks | 2 files |
| Phase 01 P04 | 9min | 2 tasks | 5 files |
| Phase 01 P07 | 1h 42m | 3 tasks | 4 files |
| Phase 01 P12 | 2h 34m | 2 tasks | 1 files |
| Phase 02 P01 | 3min | 1 tasks | 2 files |
| Phase 02 P02 | 3min | 2 tasks | 3 files |
| Phase 03 P01 | 10min | 2 tasks | 1 files |
| Phase 04 P01 | 17min | 7 tasks | 12 files |

## Accumulated Context

### Decisions

Decisions are logged in PROJECT.md Key Decisions table. Recent decisions affecting current work:

- v1.1 Phase 4: Deliver SES pacing + throttle-bug fixes as a host-level override (`ThrottledSesAdapter` rebound via `MailAdapterFactory::$adapterMap` in `AppServiceProvider::boot()`); never edit `vendor/mettle/sendportal-core`.
- v1.1 Phase 4: Use Laravel's bundled `Redis::throttle()` `DurationLimiter` over the shared `default` connection as the cross-process pacing primitive — zero new Composer dependency.
- v1.1 Phase 4: Governing invariant — do ALL waiting BEFORE the SES call, never between `send()` and `markSent()`; bounded block `max_block_seconds=15 << timeout 60 < retry_after 90` to prevent double-send.
- v1.1 Phase 4: `Throttling` covers both rate-exceeded (retry) and daily-quota-exceeded (fail fast); gate on the AWS error code then sub-branch on message. Horizon `tries=3` is the single retry owner.
- Phase 1: Resolve Composer constraints and retain a compatible security safeguard; do not bypass platform or advisory checks.
- Phase 2: Commit and use one reviewed lockfile for ordinary local, CI, and deployment installs.
- Phase 3: Preserve Laravel 11 and prove the unchanged SendPortal Core host integration on PHP 8.4.
- [Phase ?]: Phase 4: SES pacing + 2 bug fixes shipped as host-level ThrottledSesAdapter (Redis DurationLimiter, code-gated throttle classifier, named SesSendThrottledException); no vendor edits, no new dependency.
- [Phase ?]: Phase 4: cross-process SES-01 proof asserts M sends occupy >= ceil(M/R) aligned integer-second windows (pigeonhole, stable) instead of a flaky per-recorded-timestamp <=R count.

### Pending Todos

None yet.

### Blockers/Concerns

- v1.1 Phase 4: Fixed-window edge burst — `DurationLimiter` may permit ~2N across a sub-second boundary. Ship the simple limiter; token-bucket escalation (SES-06) is deferred unless SES throttling is observed in production.
- v1.1 Phase 4: App-level idempotency beyond `sent_at` (SES-07) is an open design call — the block-before-send invariant + 15s bound is the minimum mitigation; decide during Phase 4 planning.

## Deferred Items

| Category | Item | Status | Deferred At |
|----------|------|--------|-------------|
| Framework lifecycle | Laravel major-version/security modernization | Separate milestone | 2026-07-22 |
| Quality hardening | Static analysis and coverage-configuration repair (HARD-03) | v2 | 2026-07-22 |
| SES reliability | Custom Redis-Lua token-bucket limiter (SES-06) | Future — only if fixed-window trips SES | 2026-07-25 |
| SES reliability | App-level idempotency marker beyond `sent_at` (SES-07) | Future — unless SES-05 fault-injection proves the bounded block insufficient | 2026-07-25 |

### v1.1 verification overrides (acknowledged at milestone close on 2026-07-25)

Environment-only checks from `04-VERIFICATION.md` (`human_needed`) — accepted as deferred to CI/prod; not code gaps.

| Category | Item | Status |
|----------|------|--------|
| verification | Full DB-backed PHPUnit suite (37 pre-existing Auth/Invitations/Setup/Workspaces tests) — needs CI MySQL (`sendportal_testing`) | human_needed — deferred to CI |
| verification | php-cs-fixer PSR-12 gate on `app/Mail`, `config/sendportal-throttle.php`, `tests/Feature/Ses` — needs CI Docker image | human_needed — deferred to CI (`php -l` + manual PSR-12 review passed) |
| verification | Live-SES pacing under real campaign volume (SES-01 CloudWatch check) — needs live AWS SES credentials | human_needed — deferred to prod |

## Session Continuity

Last session: 2026-07-25T13:20:04.587Z
Stopped at: Completed 04-01-PLAN.md — SES-01..05 green vs real Redis; full DB suite + php-cs-fixer are CI-only env gaps
Resume file: None

## Operator Next Steps

- Start the next milestone with /gsd-new-milestone
