---
phase: 5
slug: sender-identity-management
# status lifecycle: draft (seeded by plan-phase) → validated (set by validate-phase §6)
status: draft
nyquist_compliant: false
wave_0_complete: false
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
| 05-02-01 | 02 | 2 | SENDER-03, SENDER-04, SENDER-05 | T-05-05, T-05-06, T-05-07 | RED specifies update/delete, duplicate rejection, foreign-ID 404, HTML confirmation contract, cancellation, and no Phase 5 sender-use surface | feature/TDD | `vendor/bin/phpunit tests/Feature/Workspaces/SenderControllerTest.php` | ✅ existing file | ⬜ pending |
| 05-02-02 | 02 | 2 | SENDER-03, SENDER-04, SENDER-05 | T-05-05, T-05-06, T-05-07 | Relation-scoped update/delete, validation, duplicate rejection, and rendered confirmation pass; foreign IDs cannot be used | feature + route/lint | `vendor/bin/phpunit tests/Feature/Workspaces/SenderControllerTest.php && php artisan route:list --path=senders` | ✅ | ⬜ pending |
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

Campaign sender selection/use and campaign auto-capture are not Phase 5 behaviors. Phase 5 must expose only workspace-scoped sender CRUD; Phase 6 owns the campaign selection integration and must retain the same current-workspace relation boundary, while Phase 7 owns auto-capture and must reuse the resolved normalization/uniqueness contract.

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 90s locally
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
