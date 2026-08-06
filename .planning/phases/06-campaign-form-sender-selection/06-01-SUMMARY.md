---
phase: 06-campaign-form-sender-selection
plan: 01
subsystem: ui
tags: [laravel, blade, sendportal, campaigns, sender-selection, tdd]

requires:
  - phase: 05-sender-identity-management
    provides: Workspace-scoped Sender model and Workspace::senders() relation
provides:
  - Targeted campaign create/edit view composer with active-workspace senders
  - Published host campaign wrappers and shared sender picker form override
  - TDD tracer coverage for rendered picker, tenancy isolation, and package field compatibility
affects: [07-campaign-auto-capture, campaign-form, saved-senders]

actuals:
  tokens: 2805
  tasks: 3
  commits: 3

tech-stack:
  added: []
  patterns:
    - Targeted Laravel View::composer binding package wrapper views to host presentation data
    - Unnamed native Blade select with escaped data attributes and change-only jQuery autofill

key-files:
  created:
    - resources/views/vendor/sendportal/campaigns/create.blade.php
    - resources/views/vendor/sendportal/campaigns/edit.blade.php
    - resources/views/vendor/sendportal/campaigns/partials/form.blade.php
    - tests/Feature/Workspaces/CampaignSenderSelectionTest.php
  modified:
    - app/Providers/AppServiceProvider.php

key-decisions:
  - "Use one render-scoped composer for both named package campaign wrapper views rather than replacing package controllers or routes."
  - "Keep the picker unnamed and convenience-only; existing package from_name/from_email inputs remain the submission contract."
  - "Preserve package wrappers and form fields while using the approved Return to Campaigns copy and native blank-first picker."

requirements-completed: [SENDER-06, SENDER-08]

coverage:
  - id: D1
    description: "Active-workspace senders render in a blank-first Saved Sender picker with exact label/email options and foreign workspace rows excluded."
    requirement: SENDER-06
    verification:
      - kind: integration
        ref: "tests/Feature/Workspaces/CampaignSenderSelectionTest.php#an_authenticated_user_sees_only_active_workspace_senders_in_the_create_picker"
        status: pass
    human_judgment: false
  - id: D2
    description: "Published package campaign form preserves editable From fields and emits a change-only handler that copies both sender values without submitting sender identity."
    requirement: SENDER-06
    verification:
      - kind: automated_ui
        ref: "tests/Feature/Workspaces/CampaignSenderSelectionTest.php#an_authenticated_user_sees_only_active_workspace_senders_in_the_create_picker"
        status: pass
    human_judgment: true
    rationale: "PHPUnit verifies the rendered HTML and JavaScript contract; browser execution of create/edit selection, blank reset, and subsequent manual editing is deferred to end-of-phase verification."
  - id: D3
    description: "Host-side package view override boundary remains clean with no vendor/mettle/sendportal-core changes."
    requirement: SENDER-08
    verification:
      - kind: other
        ref: "git diff --check && test -z \"$(git diff --name-only -- vendor/mettle/sendportal-core)\""
        status: pass
    human_judgment: false

duration: 6min
completed: 2026-08-06
status: complete
---

# Phase 06 Plan 01: Campaign Form Sender Selection Summary

**A host-composed, workspace-scoped Saved Sender picker now feeds the existing editable campaign From fields through synchronized SendPortal Core view overrides without vendor edits.**

## Performance

- **Duration:** 6 min
- **Started:** 2026-08-06T08:11:24Z
- **Completed:** 2026-08-06T08:17:14Z
- **Tasks:** 3/3
- **Files modified:** 5 production/test files; 1 planning ledger

## Accomplishments

- Added a failing RED tracer test for rendered create-form sender selection and active-workspace isolation.
- Wired a targeted composer for `sendportal::campaigns.create` and `sendportal::campaigns.edit` using `currentWorkspace()->senders()->orderBy('label')`.
- Published synchronized create/edit/shared campaign views with an enabled blank native picker, escaped sender attributes, editable existing From controls, and change-only autofill.
- Kept the package CampaignStoreRequest contract intact: no picker `name`, `sender_id`, route, dependency, or vendor modification.

## TDD Gate Evidence

- **RED:** `4f79b61` — focused PHPUnit test initially failed on the absent picker (`0` rendered picker instances, expected `1`).
- **GREEN:** `acb85d1` — focused test passed with 14 assertions after composer and view overrides were added.
- **REFACTOR:** `a5a122a` — import ordering stabilized; focused test and configured PHP-CS-Fixer dry run remained green.

## Task Commits

1. **Task 1: RED — specify the create-form sender-selection tracer** — `4f79b61` (`test`)
2. **Task 2: GREEN — wire the host composer and published campaign-form tracer** — `acb85d1` (`feat`)
3. **Task 3: REFACTOR — stabilize the tracer seam and package override contract** — `a5a122a` (`refactor`)

## Files Created/Modified

- `app/Providers/AppServiceProvider.php` — Targeted two-view composer with null-safe empty collection fallback.
- `resources/views/vendor/sendportal/campaigns/create.blade.php` — Synchronized package create wrapper.
- `resources/views/vendor/sendportal/campaigns/edit.blade.php` — Synchronized package edit wrapper.
- `resources/views/vendor/sendportal/campaigns/partials/form.blade.php` — Picker inserted before From fields; package form and tracking behavior retained.
- `tests/Feature/Workspaces/CampaignSenderSelectionTest.php` — Rendered sender option, ordering, isolation, blank state, no-submission, and JS contract assertions.

## Package-Source Comparison

The three host overrides were copied from the installed SendPortal Core v3.0.2 campaign create/edit/partial views. Wrapper structure, layout, card, form actions, CSRF/method fields, package components, Summernote include, tracking script, and submit behavior remain synchronized. Intentional host changes are the Saved Sender picker, change handler, and `Return to Campaigns` secondary label. `git diff --name-only -- vendor/mettle/sendportal-core` remained empty.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Test setup] Added a route resolver to deterministic named-view rendering**

- **Found during:** Task 1 RED
- **Issue:** Direct package layout rendering had no route object, causing the package layout's `request()->route()->getName()` call to fail before reaching the intended missing-picker assertion.
- **Fix:** Installed a named campaign-create route resolver in the focused test, preserving deterministic view rendering without adding an application route.
- **Files modified:** `tests/Feature/Workspaces/CampaignSenderSelectionTest.php`
- **Verification:** RED rerun failed at the intended missing picker assertion; later GREEN/REFACTOR focused runs passed.
- **Committed in:** `4f79b61`

**2. [Rule 1 - Test assertion] Scoped the blank-option selection assertion**

- **Found during:** Task 2 GREEN
- **Issue:** A global `selected` absence assertion incorrectly matched the package Template option, not the sender picker.
- **Fix:** Scoped the assertion to the sender blank option and added the explicit no-`sender_id` boundary assertion.
- **Files modified:** `tests/Feature/Workspaces/CampaignSenderSelectionTest.php`
- **Verification:** Focused test passed with 14 assertions.
- **Committed in:** `acb85d1`

**3. [Rule 3 - Verification command] Supplied the project config to PHP-CS-Fixer**

- **Found during:** Task 3 verification
- **Issue:** The plan's multi-path PHP-CS-Fixer invocation failed because this installed version requires an explicit config for multiple paths.
- **Fix:** Reran the same dry-run with `--config=.php-cs-fixer.dist.php`; no violations were found.
- **Files modified:** None
- **Verification:** Configured dry run passed with `Found 0 of 2 files that can be fixed`.
- **Committed in:** `a5a122a`

---

**Total deviations:** 3 auto-fixed (2 test correctness, 1 verification command compatibility). **Impact:** No application scope expansion; all changes remain within the planned host seam, overrides, and focused test.

## Issues Encountered

- Focused suite, PHP lint, configured PHP-CS-Fixer, route listing, and diff hygiene passed.
- The full PHPUnit suite has one pre-existing failure in `Tests\\Feature\\Ses\\SesDoubleSendTest::no_vendor_core_or_composer_manifest_was_changed` because unrelated working-tree `composer.json` and `composer.lock` changes predate this plan. It is recorded in `deferred-items.md`; campaign files do not touch Composer or vendor files.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

Plan 01's production tracer is green and ready for Plan 02's edit/empty/escaping and browser-boundary expansion. Manual browser verification remains required for create/edit selection, blank reset, and post-autofill editing.

## Self-Check: PASSED

- All four created production/test files exist.
- Task commits `4f79b61`, `acb85d1`, and `a5a122a` exist in git history.
- Focused test and refactor verification passed.
- Zero vendor diff verified.

---
*Phase: 06-campaign-form-sender-selection*
*Plan: 01*
*Completed: 2026-08-06*
