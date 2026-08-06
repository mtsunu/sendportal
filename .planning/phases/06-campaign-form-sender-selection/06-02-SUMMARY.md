---
phase: 06-campaign-form-sender-selection
plan: 02
subsystem: ui
tags: [laravel, blade, sendportal, campaigns, sender-selection, tdd, security]
requires:
  - phase: 06-campaign-form-sender-selection
    plan: 01
    provides: Host composer, package campaign overrides, and sender-picker tracer
provides:
  - Complete create/edit rendered-contract regression coverage
  - Empty, hostile-value, edit-preservation, tenancy, and submission-boundary hardening
  - Final package-source and zero-vendor-edit audit evidence
affects: [campaign-form, saved-senders, 07-campaign-auto-capture]
tech-stack:
  added: []
  patterns:
    - Defensive empty-collection fallback in a published Blade partial
    - Deterministic named-view fixtures for package-owned campaign forms
key-files:
  created:
    - .planning/phases/06-campaign-form-sender-selection/06-02-SUMMARY.md
  modified:
    - resources/views/vendor/sendportal/campaigns/create.blade.php
    - resources/views/vendor/sendportal/campaigns/partials/form.blade.php
    - tests/Feature/Workspaces/CampaignSenderSelectionTest.php
key-decisions:
  - Keep the convenience picker unnamed and change-only; package from_name/from_email remain the submission contract.
  - Treat absent sender data as an empty collection so the shared form remains manually usable.
  - Preserve synchronized package wrappers and never modify vendor/mettle/sendportal-core.
requirements-completed: [SENDER-06, SENDER-08]
actuals:
  tokens: 3500
  tasks: 3
  commits: 4
coverage:
  - id: D1
    description: "Create and edit preserve package form behavior while rendering the same blank-first Saved Sender picker without edit-time sender inference."
    requirement: SENDER-06
    verification:
      - kind: integration
        ref: "tests/Feature/Workspaces/CampaignSenderSelectionTest.php#edit_preserves_existing_from_values_without_selecting_a_matching_sender"
        status: pass
      - kind: integration
        ref: "tests/Feature/Workspaces/CampaignSenderSelectionTest.php#an_authenticated_user_sees_only_active_workspace_senders_in_the_create_picker"
        status: pass
    human_judgment: false
  - id: D2
    description: "Empty, isolated, long, and hostile sender collections remain safe; the picker is enabled, escaped, unnamed, and backed by editable package fields."
    requirement: SENDER-06
    verification:
      - kind: integration
        ref: "tests/Feature/Workspaces/CampaignSenderSelectionTest.php#empty_sender_collection_renders_an_enabled_blank_picker_and_manual_fields"
        status: pass
      - kind: integration
        ref: "tests/Feature/Workspaces/CampaignSenderSelectionTest.php#hostile_and_long_sender_values_are_escaped_and_rendered_safely"
        status: pass
      - kind: integration
        ref: "tests/Feature/Workspaces/CampaignSenderSelectionTest.php#shared_partial_falls_back_to_an_enabled_blank_picker_without_sender_data"
        status: pass
    human_judgment: false
  - id: D3
    description: "The browser contract copies both fields only on picker change, clears both via the blank option, and submits no sender identity."
    requirement: SENDER-06
    verification:
      - kind: integration
        ref: "tests/Feature/Workspaces/CampaignSenderSelectionTest.php#picker_script_is_change_only_and_covers_both_fields_and_blank_reset"
        status: pass
    human_judgment: true
    rationale: "PHPUnit verifies emitted HTML and JavaScript; actual browser event execution remains a required manual check."
  - id: D4
    description: "Published host overrides remain within the zero-vendor-edit boundary and preserve the installed package wrapper structure."
    requirement: SENDER-08
    verification:
      - kind: other
        ref: "git diff --check && test -z \"$(git diff --name-only -- vendor/mettle/sendportal-core)\""
        status: pass
      - kind: other
        ref: "php artisan route:list --path=campaigns -v"
        status: pass
    human_judgment: false
---

# Phase 06 Plan 02: Campaign Form Sender Selection Summary

**Create and edit campaign forms now have a complete host-only sender-picker contract covering edit preservation, empty/hostile data, tenancy isolation, and the unchanged package submission boundary.**

## Performance

- **Duration:** approximately 12 min
- **Started:** 2026-08-06T08:14:00Z
- **Completed:** 2026-08-06
- **Tasks:** 3/3
- **Files changed by this plan:** 3 host/test files

## Accomplishments

- Added RED coverage for edit preservation, empty and absent sender collections, hostile/long values, active-workspace isolation, no-default/no-submission behavior, and the change-only JavaScript contract.
- Hardened the shared published form partial with an empty-collection fallback while retaining editable `from_name` and `from_email` inputs.
- Audited create/edit wrappers and the shared partial against installed SendPortal Core v3.0.2; only the approved picker, fallback, copy, and host formatting differences remain.
- Preserved package fields, tracking/Summernote behavior, validation contract, primary CTA, and host-only delivery. No route, dependency, persistence, sender ID, or Phase 7 auto-capture was added.

## TDD Gate Evidence

- **RED:** `d1ea46d` — focused suite failed at the intended missing shared-partial sender fallback (`Undefined variable $senders`). Two test-fixture assertion defects found during the RED run were corrected before implementation.
- **GREEN:** `4dc18f3` — focused suite passed with 6 tests and 42 assertions after fallback and regression coverage were completed.
- **REFACTOR:** `19738ac`, `9357e65` — deterministic fixture reuse and exact package-wrapper synchronization; focused suite and formatter dry run remained green.

## Verification Results

| Check | Result |
|---|---|
| `DB_CONNECTION=sqlite DB_DATABASE=':memory:' vendor/bin/phpunit tests/Feature/Workspaces/CampaignSenderSelectionTest.php` | PASS — 6 tests, 42 assertions |
| `vendor/bin/phpunit` | PRE-EXISTING FAILURE — 77 passing tests; `SesDoubleSendTest::no_vendor_core_or_composer_manifest_was_changed` detects unrelated working-tree `composer.json`/`composer.lock` changes |
| PHP lint | PASS — provider and focused test |
| PHP-CS-Fixer dry run | PASS — 0 of 2 files fixable; PHP 8.4 compatibility warning only |
| `php artisan route:list --path=campaigns -v` | PASS — 28 campaign routes listed |
| `git diff --check` | PASS |
| Vendor boundary | PASS — no changed paths under `vendor/mettle/sendportal-core` |
| Manual browser check | NOT RUN — running installed app/browser session was unavailable |

## Package-Source Comparison

- Create and edit wrappers match the installed package source exactly.
- The shared partial retains every package field, tracking initializer, Summernote include, CTA, and form behavior.
- Intentional host-only differences are the Saved Sender picker, escaped data attributes, change-only autofill, `Return to Campaigns` copy, and the defensive sender fallback.
- The picker has no `name` or `sender_id`; existing `CampaignStoreRequest` fields remain authoritative.

## Manual Browser Check Required

In a running installed app, authenticate a verified user with an active workspace and visit both campaign create and edit pages. Confirm: blank initial picker; selecting a sender replaces both From fields; either field can then be edited; choosing the blank option clears both; zero saved senders leaves an enabled blank picker and usable manual fields; responsive Bootstrap layout and keyboard focus remain intact. Compare all three host overrides with package source after any dependency update.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Test fixture] Corrected script extraction in the new browser-contract assertion**

- **Found during:** Task 1 RED
- **Issue:** The helper selected the package layout's first `<script>` instead of the campaign form script, producing a fixture failure unrelated to sender behavior.
- **Fix:** Locate the script containing the picker handler before asserting the change contract.
- **Files modified:** `tests/Feature/Workspaces/CampaignSenderSelectionTest.php`
- **Verification:** Focused suite passed after correction.
- **Commit:** `d1ea46d`

**2. [Rule 1 - Test fixture] Kept long hostile values within the persisted schema**

- **Found during:** Task 3 full-suite verification
- **Issue:** The initial 300-character test suffix exceeded the MySQL `senders.label`/`from_name` column limit, while SQLite did not expose the mismatch.
- **Fix:** Reduced the suffix to 180 characters, still exercising long-value rendering without making the fixture invalid.
- **Files modified:** `tests/Feature/Workspaces/CampaignSenderSelectionTest.php`
- **Verification:** Focused suite and full suite rerun; the campaign test passed under MySQL.
- **Commit:** `19738ac`

**Total deviations:** 2 auto-fixed test correctness issues. **Impact:** No application scope expansion; all changes remain within the planned host view overrides and focused test.

## Known Stubs

None. The manual browser check is an unrun verification item, not a code stub; it is recorded in `.planning/WINDOWS.md` when available.

## Issues Encountered

- The full suite retains the pre-existing Composer-manifest guard failure caused by unrelated working-tree changes to `composer.json` and `composer.lock`. This matches the Phase 6 deferred-items ledger and was not fixed or masked.
- Manual browser verification was not executable in this environment and remains required before phase sign-off.

## Next Phase Readiness

Phase 6 is ready for human verification. SENDER-06 and SENDER-08 are covered by the focused rendered-contract suite, route/style/vendor-boundary checks, and the documented create/edit browser checklist. Phase 7 auto-capture remains absent as required.

## Self-Check: PASSED

- Summary file created at the planned path.
- Task commits `d1ea46d`, `4dc18f3`, `19738ac`, and `9357e65` exist.
- Focused suite, lint, formatter dry run, route smoke, diff hygiene, and zero-vendor-diff checks passed.
- Full-suite failure is isolated to the known pre-existing Composer manifest test.

---
*Phase: 06-campaign-form-sender-selection*
*Plan: 02*
*Completed: 2026-08-06*
