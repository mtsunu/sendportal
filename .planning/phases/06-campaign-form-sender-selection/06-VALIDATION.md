---
phase: 06
slug: campaign-form-sender-selection
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-08-06
---

# Phase 06 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit 10.5.64 |
| **Config file** | `phpunit.xml.dist` |
| **Quick run command** | `DB_CONNECTION=sqlite DB_DATABASE=':memory:' vendor/bin/phpunit tests/Feature/Workspaces/CampaignSenderSelectionTest.php` |
| **Full suite command** | `vendor/bin/phpunit` |
| **Estimated runtime** | ~30 seconds |

## Sampling Rate

- **After every task commit:** Run the focused campaign sender selection suite.
- **After every plan wave:** Run `vendor/bin/phpunit`.
- **Before `/gsd-verify-work`:** Full suite must be green or any pre-existing environment limitation must be documented.
- **Max feedback latency:** 30 seconds

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Threat Ref | Secure Behavior | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|------------|-----------------|-----------|-------------------|-------------|--------|
| 06-01-01 | 01 | 1 | SENDER-06 | T-06-01 / T-06-02 | Current-workspace sender options only; blank native picker | feature/rendered HTML | `DB_CONNECTION=sqlite DB_DATABASE=':memory:' vendor/bin/phpunit tests/Feature/Workspaces/CampaignSenderSelectionTest.php` | ❌ pending | ⬜ pending |
| 06-01-02 | 01 | 1 | SENDER-08 | T-06-03 / T-06-SC | Escaped host override with no vendor mutation | integration | `git diff --check && test -z "$(git diff --name-only -- vendor/mettle/sendportal-core)` | ✅ | ⬜ pending |
| 06-02-01 | 02 | 2 | SENDER-06 | T-06-08 / T-06-10 | Edit/empty/no-default/no-submission contracts | feature/rendered HTML | `DB_CONNECTION=sqlite DB_DATABASE=':memory:' vendor/bin/phpunit tests/Feature/Workspaces/CampaignSenderSelectionTest.php` | ❌ pending | ⬜ pending |
| 06-02-02 | 02 | 2 | SENDER-08 | T-06-09 / T-06-SC | Package source parity and zero vendor edits | integration | `php artisan route:list --path=campaigns -v && git diff --check && test -z "$(git diff --name-only -- vendor/mettle/sendportal-core)` | ✅ | ⬜ pending |
| 06-01-03 | 01 | 1 | SENDER-08 | T-06-05 / T-06-SC | Refactor preserves package fields and host-only boundary | static/integration | `php -l app/Providers/AppServiceProvider.php && git diff --check && test -z "$(git diff --name-only -- vendor/mettle/sendportal-core)` | ✅ | ⬜ pending |
| 06-02-03 | 02 | 2 | SENDER-06, SENDER-08 | T-06-09 / T-06-SC | Final refactor confirms UI behavior and upgrade boundary | static/manual | `php artisan route:list --path=campaigns -v && git diff --check && test -z "$(git diff --name-only -- vendor/mettle/sendportal-core)` | ✅ | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

## Wave 0 Requirements

- Existing infrastructure covers all phase requirements; the focused test is created in Plan 01 Task 1 rather than Wave 0.

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|-----------|-------------------|
| Browser autofill and editability | SENDER-06 | PHPUnit verifies emitted HTML/JavaScript but not browser event execution | On campaign create and edit, confirm blank initial state, select a sender, verify both fields change, edit either field, choose the blank option to clear both, and repeat with zero saved senders. |

## Validation Sign-Off

- [x] All tasks have `<automated>` verify or Wave 0 dependencies
- [x] Sampling continuity: no 3 consecutive tasks without automated verify
- [x] No Wave 0 dependencies are required; all tasks have their own automated verification.
- [x] No watch-mode flags
- [x] Feedback latency < 30s
- [x] `nyquist_compliant: true` set in frontmatter

**Approval:** pending execution validation
