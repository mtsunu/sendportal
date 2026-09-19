---
phase: 5
slug: sender-identity-management
# Finalized planning metadata: execution-task statuses below remain pending until
# implementation runs, while this artifact records a complete validation plan.
status: validated
nyquist_compliant: true
wave_0_complete: true
created: 2026-08-05
---

# Phase 5 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit 10.5 (`phpunit/phpunit ^10.5`) |
| **Config file** | `phpunit.xml.dist` |
| **Quick run command** | `vendor/bin/phpunit tests/Feature/Workspaces/SenderControllerTest.php` |
| **Full suite command** | `vendor/bin/phpunit` |
| **Static-format command** | `vendor/bin/php-cs-fixer fix --dry-run --diff` over changed PHP files |
| **Estimated runtime** | Quick: seconds; full suite: under 90 seconds when test database is available |

The feature test uses the existing `RefreshDatabase`, workspace factories, and authenticated request conventions. No new dependency or test runner is required.

---

## Sampling Rate

- **After every TDD task commit:** Run `vendor/bin/phpunit tests/Feature/Workspaces/SenderControllerTest.php`.
- **After every plan wave:** Run `vendor/bin/phpunit` and `php artisan route:list --path=senders`.
- **Before `/gsd-verify-work`:** Run the focused suite, full suite, PHP-CS-Fixer dry run, route listing, and `git diff --check`.
- **Max feedback latency:** ~90 seconds for the automated local gate.

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Threat Ref | Secure Behavior | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|------------|-----------------|-----------|-------------------|-------------|--------|
| 05-01-01 | 01 | 1 | SENDER-01, SENDER-02, SENDER-05 | T-05-01, T-05-02, T-05-03 | Tracer RED proves active-workspace member create/list, workspace assignment, and list isolation | feature/TDD | `vendor/bin/phpunit tests/Feature/Workspaces/SenderControllerTest.php` | ✅ created in task | ⬜ pending |
| 05-01-02 | 01 | 1 | SENDER-01, SENDER-02, SENDER-05 | T-05-01, T-05-02, T-05-04 | Host migration/model/controller/Form Request/routes/sidebar/views make normalized, unique, relation-scoped create/list green | feature + route/lint | `vendor/bin/phpunit tests/Feature/Workspaces/SenderControllerTest.php && php artisan route:list --path=senders` | ✅ | ⬜ pending |
| 05-01-03 | 01 | 1 | SENDER-01, SENDER-02, SENDER-05 | T-05-01, T-05-04 | Tracer remains green, strict, formatted, and vendor-free | feature/style | `vendor/bin/phpunit tests/Feature/Workspaces/SenderControllerTest.php` | ✅ | ⬜ pending |
| 05-02-01 | 02 | 2 | SENDER-03, SENDER-04, SENDER-05 | T-05-05, T-05-06, T-05-07 | RED specifies update/delete, duplicate rejection, foreign-ID 404 on every sender-ID CRUD route, the exact six-route CRUD allowlist, and absent Phase 6/7 route/service identifiers | feature/TDD | `vendor/bin/phpunit tests/Feature/Workspaces/SenderControllerTest.php` | ✅ existing file | ⬜ pending |
| 05-02-02 | 02 | 2 | SENDER-03, SENDER-04, SENDER-05 | T-05-05, T-05-06, T-05-07 | Relation-scoped update/delete, validation, duplicate rejection, rendered confirmation, exact CRUD route allowlist, and absent later-phase identifiers pass | feature + route/lint | `vendor/bin/phpunit tests/Feature/Workspaces/SenderControllerTest.php && php artisan route:list --path=senders` | ✅ | ⬜ pending |
| 05-02-03 | 02 | 2 | SENDER-01..05 | T-05-05, T-05-06, T-05-08, T-05-09 | Full CRUD, normalization/unique index, UI DOM contract, cross-workspace boundary, and vendor safety remain green | full/style/security | `vendor/bin/phpunit && vendor/bin/php-cs-fixer fix --dry-run --diff && git diff --check && test -z "$(git diff --name-only -- vendor/mettle/sendportal-core)"` | ✅ | ⬜ pending |

The HTML/DOM assertion is automated in `SenderControllerTest`: the rendered delete form must contain the documented confirmation message/handler before the manual browser check verifies cancellation leaves the row unchanged.

---

## Wave 0 Requirements

- [ ] `tests/Feature/Workspaces/SenderControllerTest.php` — created by Plan 01 RED and expanded by Plan 02; covers all SENDER-01..05 behaviors, normalized duplicate rejection, foreign-workspace 404s, and the Phase 5 no-sender-use boundary.
- [ ] `database/migrations/2026_08_05_000000_create_senders_table.php` — must include the workspace foreign key and composite unique index for normalized sender pairs.
- [ ] Test database configured for the existing `RefreshDatabase` setup; use the repository's existing CI MySQL/PostgreSQL matrix when local services are unavailable.

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Delete cancellation leaves the sender row intact and no DELETE is issued | SENDER-04, SENDER-05 | Browser confirmation cancellation is an interaction-level check retained alongside the automated DOM contract assertion | As an ordinary active-workspace member, open Senders, click Delete Sender, cancel the exact confirmation, and verify the row remains visible; then confirm deletion and verify the success alert/empty state. |
| Responsive/overflow/empty/error visual states match the UI spec | SENDER-02, SENDER-03, SENDER-04 | Layout and browser rendering are not fully proven by server-side DOM assertions | Check owner and ordinary member flows at narrow and wide viewport widths against `05-UI-SPEC.md`. |

Campaign sender selection/use and campaign auto-capture are not Phase 5 behaviors. The executable Phase 5 route contract is exactly these named host routes: `senders.index`, `senders.create`, `senders.store`, `senders.edit`, `senders.update`, and `senders.destroy`; no other sender route is allowed. The tests must also assert that the planned later-phase identifiers `campaigns.sender-selection`, `campaigns.sender-auto-capture`, `App\\Services\\Campaigns\\SelectCampaignSender`, and `App\\Services\\Campaigns\\AutoCaptureCampaignSender` are absent/not exposed in Phase 5. Phase 6 owns campaign selection and must retain the same current-workspace relation boundary, while Phase 7 owns auto-capture and must reuse the resolved normalization/uniqueness contract.

---

## Validation Sign-Off

- [x] All tasks have `<automated>` verify or Wave 0 dependencies
- [x] Sampling continuity: no 3 consecutive tasks without automated verify
- [x] Wave 0 covers all MISSING references
- [x] No watch-mode flags
- [x] Feedback latency < 90s locally
- [x] `nyquist_compliant: true` set in frontmatter

**Approval:** approved — validation strategy and plan task/wave mapping finalized; execution evidence remains required at the commands listed above.
