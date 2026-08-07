---
phase: 07-sender-auto-capture-data-integrity
plan: 02
subsystem: api
tags: [laravel, middleware, observers, senders, campaign, message, integrity, sqlite, mysql, postgresql]

# Dependency graph
requires:
  - phase: 05-sender-identity-management
    provides: Workspace-scoped Sender model, CRUD normalization, and composite uniqueness.
  - phase: 06-campaign-form-sender-selection
    provides: Unchanged package From-field submission contract and host-only campaign view boundary.
  - phase: 07-sender-auto-capture-data-integrity
    provides: Creation-only after-commit Campaign observer and atomic sender capture service from Plan 07-01.
provides:
  - Fixed, non-blocking web session and API header warning transport for capture failures.
  - Campaign and message snapshot-integrity regression coverage across lifecycle states and later message generation.
  - Explicit host/vendor, schema, relationship, duplicate, deletion, and ordering boundary assertions.
affects: [sender-integrity, campaign-creation, api-warning-contract]

actuals:
  tokens: 4634
  tasks: 3
  commits: 3

tech-stack:
  added: []
  patterns:
    - Host API middleware consumes a fixed request attribute after the workspace boundary and adds only a fixed warning header.
    - Campaign and message From fields remain independent historical snapshots; sender-library CRUD has no live relationship to them.

key-files:
  created:
    - app/Http/Middleware/AttachSenderCaptureWarningHeader.php
    - tests/Feature/Workspaces/CampaignSenderIntegrityTest.php
  modified:
    - app/Observers/CampaignObserver.php
    - routes/api.php
    - tests/Feature/Workspaces/CampaignSenderAutoCaptureTest.php

key-decisions:
  - "Use request()->hasSession() to route capture failures to the existing web session warning or a fixed API request attribute; package JSON and status remain unchanged."
  - "Expose only the fixed X-SendPortal-Warning value and log trusted numeric identifiers plus exception class/code; never reflect sender input or exception messages."
  - "Prove historical independence through existing campaign/message From columns, with no sender_id column, sender relation, vendor edit, or collection-order dependency."

patterns-established:
  - "Best-effort observer recovery: swallow capture failure after the campaign write, record bounded diagnostics, and use the caller's existing response channel."
  - "Snapshot regression fixtures model draft, queued, sending, and sent message timestamps explicitly and assert later CreateMessages reads the current campaign snapshot."

requirements-completed: [SENDER-07, SENDER-09]

coverage:
  - id: D1
    description: "A forced capture failure leaves web and API campaign creation successful while exposing only the fixed session warning or additive API header and safe log context."
    requirement: SENDER-07
    verification:
      - kind: integration
        ref: "tests/Feature/Workspaces/CampaignSenderAutoCaptureTest.php::a_web_capture_failure_commits_the_campaign_and_flashes_a_safe_warning"
        status: pass
      - kind: integration
        ref: "tests/Feature/Workspaces/CampaignSenderAutoCaptureTest.php::an_api_capture_failure_commits_the_campaign_and_adds_a_safe_warning_header"
        status: pass
    human_judgment: false
  - id: D2
    description: "Sender edits and deletion leave campaign and existing message From snapshots readable and unchanged across draft, queued, sending, and sent rows."
    requirement: SENDER-09
    verification:
      - kind: integration
        ref: "tests/Feature/Workspaces/CampaignSenderIntegrityTest.php::sender_lifecycle_changes_do_not_mutate_historical_snapshots"
        status: pass
      - kind: integration
        ref: "tests/Feature/Workspaces/CampaignSenderIntegrityTest.php::later_message_generation_uses_the_current_campaign_snapshot"
        status: pass
    human_judgment: false
  - id: D3
    description: "Normalized duplicates, blank/null inputs, deleted-pair recreation, equal and adjacent independent rows, no sender relationship, and no sender_id schema boundary are executable."
    requirement: SENDER-07
    verification:
      - kind: integration
        ref: "DB_CONNECTION=sqlite DB_DATABASE=:memory: vendor/bin/phpunit tests/Feature/Workspaces/CampaignSenderAutoCaptureTest.php tests/Feature/Workspaces/CampaignSenderIntegrityTest.php"
        status: pass
      - kind: other
        ref: "git diff --name-only -- vendor/mettle/sendportal-core && git diff --check"
        status: pass
    human_judgment: false
  - id: D4
    description: "Cross-process duplicate convergence is retained for CI MySQL/PostgreSQL execution with two successful campaigns, one sender row, and no duplicate warning."
    requirement: SENDER-07
    verification: []
    human_judgment: true
    rationale: "The local SQLite run intentionally skips the process race; MySQL is not listening locally and the PostgreSQL readiness utility is unavailable, so the CI database matrix remains authoritative."

duration: 11 min
completed: 2026-08-07
status: complete
---

# Phase 7 Plan 2: Sender Auto-Capture & Data Integrity Summary

**Fixed web/API capture-failure recovery and immutable campaign/message sender snapshots are now enforced at the host boundary.**

## Performance

- **Duration:** 11 min
- **Started:** 2026-08-07T01:17:03Z
- **Completed:** 2026-08-07T01:28:33Z
- **Tasks:** 3
- **Files modified:** 5

## Accomplishments

- Added `AttachSenderCaptureWarningHeader` after `RequireWorkspace` on the host API group; failures retain the package response contract and add only the fixed `X-SendPortal-Warning` header.
- Updated the creation-only after-commit observer to use session detection for web flashes and a fixed request attribute for API responses, with safe diagnostic logging and no raw sender/exception data.
- Added campaign/message integrity coverage for four message lifecycle states, sender edit/delete, later `CreateMessages` snapshots, direct campaign From edits, duplicate/no-op/recreation behavior, independent rows, and the no-vendor/no-relation boundary.

## Task Commits

Each task was committed atomically:

1. **Task 1: RED — add failure-recovery and snapshot-integrity tests** - `679cbc0` (test)
2. **Task 2: GREEN — add the host-only API warning transport** - `4a3f4bb` (feat)
3. **Task 3: REFACTOR — harden phase-wide regression and boundary checks** - `f3cef02` (refactor)

## Files Created/Modified

- `app/Http/Middleware/AttachSenderCaptureWarningHeader.php` - Adds the fixed API warning header only when the observer marks the current request.
- `app/Observers/CampaignObserver.php` - Separates session-backed web warning delivery from API request-attribute delivery while preserving after-commit, created-only, best-effort behavior.
- `routes/api.php` - Registers the host warning middleware after the workspace boundary for package API routes.
- `tests/Feature/Workspaces/CampaignSenderAutoCaptureTest.php` - Proves web/API failure recovery, fixed header safety, and the existing capture edge cases.
- `tests/Feature/Workspaces/CampaignSenderIntegrityTest.php` - Proves immutable campaign/message snapshots and the no-relationship/schema boundary.

## Decisions Made

- Keep the package API body and status untouched; transport capture failure through a fixed additive header owned by the host.
- Distinguish web and API request channels with `Request::hasSession()` rather than the mere existence of a session binding, because API requests may boot the application without session middleware.
- Keep sender maintenance independent from historical campaign/message data and explicitly assert the absence of `sender_id` and a campaign `sender` relation.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 2 - Missing Critical] Corrected an unavailable read-first fixture path**
- **Found during:** Task 1 (RED — add failure-recovery and snapshot-integrity tests)
- **Issue:** The plan referenced `tests/Feature/Api/ApiTokenTest.php`, which is not present in this checkout.
- **Fix:** Read the existing API-token analogue at `tests/Feature/Auth/WorkspaceApiTokenTest.php` before writing the route failure fixtures; no production or scope change was required.
- **Files modified:** None for this correction.
- **Verification:** The RED run failed only at the expected missing API header; the focused GREEN run passed.
- **Committed in:** `679cbc0` (test checkpoint)

---

**Total deviations:** 1 auto-fixed (1 missing-critical read-first path)
**Impact on plan:** The unavailable reference was replaced with the repository's actual API-token test analogue; all planned behavior and file scope remained unchanged.

## Issues Encountered

- The parallel PHP-CS-Fixer invocation could not open a local TCP socket in the sandbox; the required formatter check passed with `--sequential` and found zero fixable files.
- The full SQLite suite ran 98 tests with 6 skips and one unrelated pre-existing failure in `Tests\\Feature\\Ses\\SesDoubleSendTest::no_vendor_core_or_composer_manifest_was_changed`, because the user-owned working tree already has modified `composer.json` and `composer.lock`. No Phase 07 or vendor file was changed by this plan.
- MySQL is unavailable on `127.0.0.1:3306`, and `pg_isready` is not installed locally; cross-driver race verification remains a CI job responsibility.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Phase 07 implementation and focused regression coverage are complete for SENDER-07 and SENDER-09.
- CI should run the existing full suite on both MySQL and PostgreSQL to execute the process-level duplicate convergence case that SQLite skips locally.

## Self-Check: PASSED

- Both Plan 07-02 test suites pass locally: 20 tests, 82 assertions, 1 intentional SQLite concurrency skip.
- RED, GREEN, and REFACTOR commits are present in order: `679cbc0`, `4a3f4bb`, `f3cef02`.
- Formatter, PHP lint, route registration, whitespace, no-vendor-edit, and host/schema boundary checks passed; the unrelated Composer-manifest full-suite failure is documented above.

---
*Phase: 07-sender-auto-capture-data-integrity*
*Completed: 2026-08-07*
