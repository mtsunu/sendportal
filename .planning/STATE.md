---
gsd_state_version: 1.0
milestone: v1.2
milestone_name: Saved Sender Identities
current_phase: 07
current_phase_name: sender-auto-capture-data-integrity
status: verifying
stopped_at: Completed 07-sender-auto-capture-data-integrity-02-PLAN.md
last_updated: "2026-09-19T07:50:11.789Z"
last_activity: 2026-09-19
last_activity_desc: Completed quick task 260919-klt: Tambahkan API transactional email berbasis queue dengan token workspace, validasi payload, dan regression test
progress:
  total_phases: 3
  completed_phases: 3
  total_plans: 6
  completed_plans: 6
  percent: 100
---

# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-08-06)

**Core value:** Operators can install and run SendPortal reliably on PHP 8.4 without bypassing dependency or platform requirements. For v1.2: operators save a sender identity once and reuse it via a dropdown when creating a campaign.
**Current focus:** Phase 07 — sender-auto-capture-data-integrity

## Current Position

Phase: 07 (sender-auto-capture-data-integrity) — EXECUTING
Plan: 2 of 2
Status: Phase complete — ready for verification
Last activity: 2026-08-07 — Phase 07 execution started

Progress: [████████████████████] 4/4 plans ([██████████] 100%)

## Performance Metrics

**Velocity:**

- Total plans completed: 7
- Average duration: -
- Total execution time: 0 hours

**By Phase:**

| Phase | Plans | Total | Avg/Plan |
|-------|-------|-------|----------|
| 02 | 2 | - | - |
| 3 | 1 | - | - |
| 05 | 2 | - | - |
| 06 | 2 | - | - |

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
| Phase 05 P01 | 5 min | 3 tasks | 13 files |
| Phase 05 P02 | 13 min | 3 tasks | 9 files |
| Phase 06 P01 | 6 min | 3 tasks | 5 files |
| Phase 06 P02 | 12 min | 3 tasks | 3 files |
| Phase 07 P01 | 12 min | 3 tasks | 4 files |
| Phase 07 P02 | 11 min | 3 tasks | 5 files |

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
- v1.2 Roadmap: Split into 3 phases — Phase 5 (Sender CRUD, SENDER-01/02/03/04/05), Phase 6 (campaign-form dropdown + zero-vendor-edit delivery, SENDER-06/08), Phase 7 (auto-capture + data integrity, SENDER-07/09) — each an independently shippable increment of user value.
- v1.2 Roadmap: Phase 7's auto-capture hook (Campaign model observer given `$guarded = []`, vs. a host wrapper) is explicitly left as a plan-time decision, not fixed at roadmap level.
- [Phase ?]: Phase 05 Plan 01: all active-workspace members manage shared senders through separate RequireWorkspace-protected routes; normalization trims all fields and lowercases From Email with composite workspace/from_name/from_email uniqueness.
- [Phase 05]: Sender CRUD uses active-workspace relation lookups for every record operation and omits owner-only middleware for shared-member management.
- [Phase 05]: Sender normalization is centralized as trim-all-fields plus lowercase From Email, with workspace/from_name/from_email duplicate protection.
- [Phase ?]: Use one render-scoped composer for both named package campaign wrapper views rather than replacing package controllers or routes.
- [Phase ?]: Keep the picker unnamed and convenience-only; existing package from_name/from_email inputs remain the submission contract.
- [Phase ?]: Preserve package wrappers and form fields while using the approved Return to Campaigns copy and native blank-first picker.
- [Phase ?]: Phase 06 Plan 02: Keep the convenience picker unnamed and change-only; package from_name/from_email remain the submission contract.
- [Phase ?]: Phase 06 Plan 02: Treat absent sender data as an empty collection so the shared form remains manually usable.
- [Phase 06]: Deliver campaign sender selection through a targeted host view composer and published package-view overrides; keep `vendor/mettle/sendportal-core` unchanged.
- [Phase 06]: Keep the Saved Sender picker unnamed and change-only so the package `from_name`/`from_email` submission contract remains authoritative.
- [Phase 06]: Phase 7 may choose a host seam for auto-capture, but must preserve the Phase 6 vendor boundary and campaign-form behavior.
- [Phase 07]: Register a creation-only Campaign observer from AppServiceProvider so direct, package web, and package API saves share one host boundary without vendor edits.
- [Phase 07]: Derive a trimmed campaign-name label bounded to 255 UTF-8 code points, with Campaign sender as the empty-name fallback.
- [Phase 07]: Use the persisted campaign workspace_id and the existing normalized composite unique index; duplicate rows are silent no-ops and never overwrite labels or campaign values.
- [Phase 07]: Use request()->hasSession() to route capture failures to the existing web session warning or a fixed API request attribute. — API requests can boot the app without session middleware; the request channel must be detected by actual session availability.
- [Phase 07]: Expose only the fixed X-SendPortal-Warning value and safe log context. — The warning and logs must never reflect sender input or raw exception messages.
- [Phase 07]: Keep campaign and message From snapshots independent from mutable sender-library rows. — No sender_id column or campaign sender relation is needed; existing snapshot columns preserve historical behavior after sender CRUD.

### Pending Todos

None yet.

### Blockers/Concerns

- v1.1 Phase 4: Fixed-window edge burst — `DurationLimiter` may permit ~2N across a sub-second boundary. Ship the simple limiter; token-bucket escalation (SES-06) is deferred unless SES throttling is observed in production.
- v1.1 Phase 4: App-level idempotency beyond `sent_at` (SES-07) is an open design call — the block-before-send invariant + 15s bound is the minimum mitigation; decide during Phase 4 planning.
- v1.2 Phase 7: Vendor boundary remains HARD (SENDER-08, zero `vendor/mettle/sendportal-core` edits) — auto-capture (SENDER-07) requires a host seam into campaign creation (Campaign model observer or host wrapper) since the controller is package-owned. Resolve the exact seam during Phase 7 planning.

### Quick Tasks Completed

| # | Description | Date | Commit | Status | Directory |
|---|-------------|------|--------|--------|-----------|
| 260919-klt | Tambahkan API transactional email berbasis queue dengan token workspace, validasi payload, dan regression test | 2026-09-19 | 4b37dbc | Verified | [260919-klt-tambahkan-api-transactional-email-berbas](./quick/260919-klt-tambahkan-api-transactional-email-berbas/) |

Last activity: 2026-09-19 - Completed quick task 260919-klt: Tambahkan API transactional email berbasis queue dengan token workspace, validasi payload, dan regression test

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

Last session: 2026-08-07T01:30:31.806Z
Stopped at: Completed 07-sender-auto-capture-data-integrity-02-PLAN.md
Resume file: None

## Operator Next Steps

- Plan Phase 7 with /gsd-plan-phase 7
