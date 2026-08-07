---
phase: 07-sender-auto-capture-data-integrity
plan: 01
subsystem: database
tags: [laravel, eloquent, observers, senders, campaign, sqlite, mysql, postgresql]

requires:
  - phase: 05-sender-identity-management
    provides: Workspace-scoped Sender model normalization and composite uniqueness.
  - phase: 06-campaign-form-sender-selection
    provides: Unchanged package From-field submission contract and host-only campaign view boundary.
provides:
  - Creation-only after-commit Campaign observer registered by the host.
  - Best-effort normalized sender capture with atomic duplicate arbitration.
  - Direct, web, API, mode, deduplication, blank-input, deletion, and supported-driver race coverage.
affects: [07-02, sender-integrity, campaign-creation]

actuals:
  tokens: 7800
  tasks: 3
  commits: 3

tech-stack:
  added: []
  patterns:
    - Host Eloquent observer implementing ShouldHandleEventsAfterCommit for package-owned model creation.
    - Parameterized insertOrIgnore followed by duplicate-key existence confirmation without label-updating upsert.

key-files:
  created:
    - app/Observers/CampaignObserver.php
    - app/Services/Senders/CaptureCampaignSender.php
    - tests/Feature/Workspaces/CampaignSenderAutoCaptureTest.php
  modified:
    - app/Providers/AppServiceProvider.php

key-decisions:
  - "Register a creation-only Campaign observer from AppServiceProvider so direct, package web, and package API saves share one host boundary without vendor edits."
  - "Derive a trimmed campaign-name label bounded to 255 UTF-8 code points, with Campaign sender as the empty-name fallback."
  - "Use the persisted campaign workspace_id and the existing normalized composite unique index; duplicate rows are silent no-ops and never overwrite labels or campaign values."

requirements-completed: [SENDER-07]

coverage:
  - id: D1
    description: "Host after-commit observer captures normalized sender identities from direct Campaign creation and package web/API stores."
    requirement: SENDER-07
    verification:
      - kind: integration
        ref: "tests/Feature/Workspaces/CampaignSenderAutoCaptureTest.php::a_direct_campaign_creation_captures_its_sender_identity"
        status: pass
      - kind: integration
        ref: "tests/Feature/Workspaces/CampaignSenderAutoCaptureTest.php::the_package_web_campaign_store_uses_the_same_capture_boundary"
        status: pass
      - kind: integration
        ref: "tests/Feature/Workspaces/CampaignSenderAutoCaptureTest.php::the_package_api_campaign_store_uses_the_same_capture_boundary"
        status: pass
    human_judgment: false
  - id: D2
    description: "Capture preserves campaign values and handles all planned modes, normalized duplicates, blank fields, bounded labels, and deleted-pair recreation."
    requirement: SENDER-07
    verification:
      - kind: integration
        ref: "DB_CONNECTION=sqlite DB_DATABASE=:memory: vendor/bin/phpunit tests/Feature/Workspaces/CampaignSenderAutoCaptureTest.php"
        status: pass
      - kind: other
        ref: "php -l app/Observers/CampaignObserver.php && php -l app/Services/Senders/CaptureCampaignSender.php && git diff --check"
        status: pass
    human_judgment: false
  - id: D3
    description: "Supported-driver cross-process duplicate creation coverage is present for CI MySQL/PostgreSQL execution."
    requirement: SENDER-07
    verification: []
    human_judgment: true
    rationale: "The local SQLite run intentionally skips the process race; the CI MySQL and PostgreSQL jobs must execute and prove the two-process convergence."

duration: 12 min
completed: 2026-08-07
status: complete
---

# Phase 7 Plan 1: Sender Auto-Capture & Data Integrity Summary

**Host-side Campaign observer and normalized atomic sender capture now cover direct, web, and API campaign creation without changing SendPortal Core.**

## Performance

- **Duration:** 12 min
- **Started:** 2026-08-07T01:03:57Z
- **Completed:** 2026-08-07T01:15:24Z
- **Tasks:** 3
- **Files modified:** 4

## Accomplishments

- Added a creation-only `ShouldHandleEventsAfterCommit` observer and registered it in the host provider, covering the vendor web/API repository save boundary and future direct Eloquent creates.
- Added tenant-safe `CaptureCampaignSender` normalization, bounded label derivation, blank-input no-op behavior, and parameterized `insertOrIgnore` duplicate handling that preserves user labels and campaign snapshots.
- Added route-complete and edge-case coverage for direct/web/API creation, campaign updates, draft/scheduled/queued/sending/sent/immediate modes, normalization, blank fields, deletion recreation, and CI-only supported-driver concurrency.

## Task Commits

Each task was committed atomically:

1. **Task 1: RED — specify the direct Campaign creation auto-capture tracer** - `4fbf565` (test)
2. **Task 2: GREEN — wire the after-commit observer and atomic sender capture service** - `7058521` (feat)
3. **Task 3: REFACTOR — expand auto-capture coverage across routes, modes, deletion, and races** - `71f4656` (refactor)

## Files Created/Modified

- `app/Observers/CampaignObserver.php` - Creation-only after-commit recovery boundary with safe log/session warning behavior.
- `app/Services/Senders/CaptureCampaignSender.php` - Normalized, bounded, workspace-trusted, atomic sender capture service.
- `app/Providers/AppServiceProvider.php` - Host registration of the Campaign observer.
- `tests/Feature/Workspaces/CampaignSenderAutoCaptureTest.php` - Focused production-path and edge-case integration suite.

## Decisions Made

- Keep the package boundary hard: no edits under `vendor/mettle/sendportal-core`, no new sender foreign key, and no submitted `sender_id`.
- Treat a zero-row insert as a silent duplicate only after confirming the normalized unique row exists; otherwise surface the persistence failure to the observer recovery boundary.
- Keep blank/null/whitespace From fields as silent no-ops and never normalize values back onto the campaign model.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Authenticated the route fixtures before resolving workspace-scoped EmailService factories**
- **Found during:** Task 3 (route-complete coverage)
- **Issue:** The package EmailService factory resolves the current workspace through auth/request state, and the initial helper call happened before `actingAs`.
- **Fix:** Authenticate the fixture user before creating web/API EmailService payloads.
- **Files modified:** `tests/Feature/Workspaces/CampaignSenderAutoCaptureTest.php`
- **Verification:** Focused suite passed with 15 tests and 35 assertions.
- **Committed in:** `71f4656`

---

**Total deviations:** 1 auto-fixed (1 blocking fixture issue)
**Impact on plan:** Required to exercise the real package routes; no production scope changed.

## Issues Encountered

- The full SQLite suite ran 93 tests with 6 skips and one unrelated pre-existing failure in `Tests\\Feature\\Ses\\SesDoubleSendTest::no_vendor_core_or_composer_manifest_was_changed` because the user-owned working tree already has modified `composer.json` and `composer.lock`. Phase 7 files and vendor paths were not changed by this plan.
- MySQL/PostgreSQL services are unavailable locally, so the process-level concurrency case remains a CI verification item as planned.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Wave 1 is ready for Plan 07-02, which adds API warning transport and campaign/message historical-integrity coverage.
- The API warning contract remains to be implemented in the next plan; the existing capture observer already provides the fixed web-session warning and safe log boundary.

## Self-Check: PASSED

- All four phase files exist and contain the planned observer, service, provider registration, and focused tests.
- RED, GREEN, and REFACTOR commits are present in order: `4fbf565`, `7058521`, `71f4656`.
- Focused verification, lint, route smoke, diff hygiene, and zero-vendor-edit checks passed; the SQLite full-suite failure is documented as pre-existing.

---
*Phase: 07-sender-auto-capture-data-integrity*
*Completed: 2026-08-07*
