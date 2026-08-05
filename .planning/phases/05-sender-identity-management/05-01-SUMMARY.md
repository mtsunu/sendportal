---
phase: 05-sender-identity-management
plan: 01
subsystem: sender-management
tags: [laravel, php, eloquent, blade, tenancy, tdd]

requires: []
provides:
  - Workspace-scoped Sender persistence and create/list HTTP slice
  - Member-visible Senders navigation and package-layout Blade pages
  - TDD feature coverage for owner/member create, normalization, and list isolation
affects: [05-02, campaign-sender-selection, sender-auto-capture]

actuals:
  tokens: 4148
  tasks: 3
  commits: 3

tech-stack:
  added: []
  patterns:
    - Relation-first workspace scoping through Workspace::senders()
    - Form Request normalization and workspace-pair uniqueness validation
    - Host-rendered sidebar fragments and SendPortal package-layout views

key-files:
  created:
    - database/migrations/2026_08_05_000000_create_senders_table.php
    - app/Models/Sender.php
    - app/Http/Controllers/Workspaces/SendersController.php
    - app/Http/Requests/Workspaces/SenderStoreRequest.php
    - resources/views/senders/index.blade.php
    - resources/views/senders/create.blade.php
    - resources/views/senders/_form.blade.php
    - resources/views/layouts/sidebar/sendersMenuItem.blade.php
    - tests/Feature/Workspaces/SenderControllerTest.php
  modified:
    - app/Models/Workspace.php
    - routes/web.php
    - app/Providers/AppServiceProvider.php

key-decisions:
  - "Keep sender CRUD routes separate from owner-only workspace-user routes so every active-workspace member can manage shared senders."
  - "Normalize sender input by trimming all fields and lowercasing only From Email; enforce the workspace/from_name/from_email uniqueness contract in both validation and the database."
  - "Extend the existing SendPortal sidebar resolver with a host fragment and do not edit vendor files."

requirements-completed: [SENDER-01, SENDER-02, SENDER-03, SENDER-04, SENDER-05]

coverage:
  - id: D1
    description: "Authenticated active-workspace owners and members can create normalized senders and see the current workspace list."
    requirement: SENDER-01
    verification:
      - kind: integration
        ref: "DB_CONNECTION=sqlite DB_DATABASE=':memory:' vendor/bin/phpunit tests/Feature/Workspaces/SenderControllerTest.php"
        status: pass
    human_judgment: false
  - id: D2
    description: "Senders page is reachable through protected named routes and package-shell sidebar navigation."
    requirement: SENDER-02
    verification:
      - kind: other
        ref: "php artisan route:list --path=senders -v"
        status: pass
    human_judgment: true
    rationale: "Responsive layout and visual sidebar rendering require browser verification beyond the server-side route and feature assertions."

duration: 5 min
completed: 2026-08-05
status: complete
---

# Phase 05 Plan 01: Sender Identity Management Summary

**Workspace-scoped sender persistence, normalized member create/list flows, and host-side SendPortal navigation are implemented without vendor edits.**

## Performance

- **Duration:** 5 min
- **Started:** 2026-08-05T14:41:00Z
- **Completed:** 2026-08-05T14:46:30Z
- **Tasks:** 3
- **Files modified:** 13 implementation/test/deferred files

## Accomplishments

- Added a reversible `senders` migration, `Sender` model, and `Workspace::senders()` relation with tenant foreign key and normalized identity uniqueness.
- Added authenticated, verified, `RequireWorkspace`-protected `senders.index`, `senders.create`, and `senders.store` routes with Form Request validation and relation-first creation.
- Added package-layout list/create views and a host sidebar fragment visible to all active-workspace members; no vendor files changed.
- Added RED/GREEN/REFACTOR TDD coverage for owner/member access, normalization, persistence, and cross-workspace list isolation.

## Task Commits

1. **Task 1: RED — specify the create/list vertical slice** - `daeecf5` (`test`)
2. **Task 2: GREEN — wire persistence, tenant-scoped create/list, and package-shell UI** - `a5d85a3` (`feat`)
3. **Task 3: REFACTOR — stabilize the tracer contract and formatting** - `f9dad40` (`refactor`)

## Verification Evidence

- Focused sender suite passes with SQLite fallback: **2 tests, 19 assertions**.
- Sender route list shows exactly three planned routes, all behind `auth`, `verified`, and `RequireWorkspace`, with no owner-only middleware.
- PHP lint passes for all new PHP files; `git diff --check` passes; vendor diff is empty.
- Default MySQL PHPUnit command is unavailable because the configured `laravel` test user is denied access. Full SQLite suite runs 66 tests with one unrelated pre-existing SetupTest failure.

## Files Created/Modified

- `database/migrations/2026_08_05_000000_create_senders_table.php` - Sender table, workspace foreign key, timestamps, and composite unique identity index.
- `app/Models/Sender.php`, `app/Models/Workspace.php` - Sender model and tenant relation.
- `app/Http/Controllers/Workspaces/SendersController.php` - Thin index/create/store controller.
- `app/Http/Requests/Workspaces/SenderStoreRequest.php` - Validation, normalization, and duplicate guard.
- `routes/web.php` - Protected sender route group.
- `app/Providers/AppServiceProvider.php`, `resources/views/layouts/sidebar/sendersMenuItem.blade.php` - Host sidebar seam and navigation item.
- `resources/views/senders/index.blade.php`, `create.blade.php`, `_form.blade.php` - Package-layout sender UI.
- `tests/Feature/Workspaces/SenderControllerTest.php` - Create/list TDD feature coverage.
- `deferred-items.md` - Environment-only verification limitations.

## Decisions Made

- All active-workspace members share the sender library; sender routes intentionally omit `OwnsCurrentWorkspace`.
- Sender identity uniqueness is `(workspace_id, from_name, from_email)` after trim/lowercase-email normalization; label remains descriptive metadata.
- Vendor SendPortal Core remains untouched; host sidebar HTML is composed through the existing resolver.

## Deviations from Plan

### Verification limitations

**1. [Environment] MySQL and formatter verification unavailable locally**
- **Found during:** Task 1 and Task 3 verification
- **Issue:** The configured MySQL test user cannot authenticate, and `vendor/bin/php-cs-fixer` is absent.
- **Resolution:** Ran the focused suite against SQLite in-memory, ran PHP lint, route inspection, and `git diff --check`; documented the remaining checks in `deferred-items.md`.
- **Verification:** Sender suite passes (2 tests, 19 assertions); route and vendor-safety checks pass.

**2. [Scope boundary] One unrelated full-suite SQLite failure**
- **Found during:** Overall verification
- **Issue:** Existing `SetupTest` expected 5 but received 0; sender tests remain green.
- **Resolution:** Did not modify unrelated setup behavior; recorded it in `deferred-items.md`.

**Total deviations:** 2 verification/environment deviations; 0 production-code auto-fixes.
**Impact on plan:** Sender implementation is complete and verified with the available database fallback; CI or a configured local test database should rerun the default MySQL/full formatter gates.

## Known Stubs

None. The empty sender collection copy is an intentional, fully rendered empty state rather than a data stub.

## Issues Encountered

- Local MySQL credentials and the optional formatter binary are unavailable; see `deferred-items.md`.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

Ready for Plan 02 to add relation-scoped edit/delete routes, hostile-input validation, duplicate rejection coverage, and foreign-ID 404 assertions. Campaign sender selection and auto-capture remain deferred to later phases.

## Self-Check: PASSED

- All nine created implementation/test files exist.
- Task commits `daeecf5`, `a5d85a3`, and `f9dad40` exist in git history.
- Focused sender verification and route/lint/vendor checks passed with the documented SQLite fallback.

---
*Phase: 05-sender-identity-management*
*Completed: 2026-08-05*
