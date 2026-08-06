---
phase: 07
slug: sender-auto-capture-data-integrity
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-08-06
---

# Phase 07 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit 10.5 with Laravel 11 `RefreshDatabase` |
| **Config file** | `phpunit.xml.dist` |
| **Quick run command** | `vendor/bin/phpunit tests/Feature/Workspaces/CampaignSenderAutoCaptureTest.php tests/Feature/Workspaces/CampaignSenderIntegrityTest.php` |
| **Full suite command** | `vendor/bin/phpunit` |
| **Estimated runtime** | ~30 seconds locally; longer in CI database matrix |

## Sampling Rate

- **After every task commit:** Run the focused sender-capture and integrity tests.
- **After every plan wave:** Run `vendor/bin/phpunit`.
- **Before `$gsd-verify-work`:** The full suite and the MySQL/PostgreSQL CI matrix must be green.
- **Max feedback latency:** 60 seconds for the focused suite.

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Threat Ref | Secure Behavior | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|------------|-----------------|-----------|-------------------|-------------|--------|
| 07-01-01 | 01 | 1 | SENDER-07 | T-07-01 | Every production Campaign creation path invokes capture without vendor edits. | feature | `vendor/bin/phpunit tests/Feature/Workspaces/CampaignSenderAutoCaptureTest.php` | ❌ W0 | ⬜ pending |
| 07-01-02 | 01 | 1 | SENDER-07 | T-07-02 | Normalized workspace/name/email uniqueness converges concurrent duplicates without changing labels or submitted campaign values. | feature/integration | `vendor/bin/phpunit tests/Feature/Workspaces/CampaignSenderAutoCaptureTest.php` | ❌ W0 | ⬜ pending |
| 07-02-01 | 02 | 2 | SENDER-09 | T-07-03 | Sender edit/delete leaves campaign and existing message sender snapshots unchanged. | feature | `vendor/bin/phpunit tests/Feature/Workspaces/CampaignSenderIntegrityTest.php` | ❌ W0 | ⬜ pending |
| 07-02-02 | 02 | 2 | SENDER-07, SENDER-09 | T-07-04 | Capture failures keep the campaign committed and emit the documented warning/log signal. | unit/feature | `vendor/bin/phpunit tests/Feature/Workspaces/CampaignSenderAutoCaptureTest.php tests/Feature/Workspaces/CampaignSenderIntegrityTest.php` | ❌ W0 | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

## Wave 0 Requirements

- [ ] `tests/Feature/Workspaces/CampaignSenderAutoCaptureTest.php` — capture, normalization, deduplication, failure handling, and creation-path coverage.
- [ ] `tests/Feature/Workspaces/CampaignSenderIntegrityTest.php` — campaign/message snapshot isolation after sender CRUD and later message generation.
- [ ] Existing PHPUnit/Laravel test infrastructure — no framework installation required.

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Cross-process duplicate convergence on both supported database drivers | SENDER-07 | Local MySQL/PostgreSQL services are unavailable; CI owns the driver matrix. | Run the focused tests in the MySQL and PostgreSQL CI jobs and confirm both campaigns succeed with exactly one normalized sender and no race warning. |

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
