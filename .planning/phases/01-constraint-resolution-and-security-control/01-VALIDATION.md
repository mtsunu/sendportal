---
phase: 01
slug: constraint-resolution-and-security-control
# status lifecycle: draft (seeded by plan-phase) → validated (set by validate-phase §6)
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-07-22
---

# Phase 01 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Composer 2.10+ validation; PHPUnit `^10.5` after a successful install |
| **Config file** | `composer.json`, `phpunit.xml.dist` |
| **Quick run command** | `composer validate --strict --no-check-publish` |
| **Full suite command** | `vendor/bin/phpunit` after a locked install in Phase 3 |
| **Estimated runtime** | ~10 seconds for manifest validation |

---

## Sampling Rate

- **After every task commit:** Run `composer validate --strict --no-check-publish`
- **After every plan wave:** Run the isolated PHP 8.4 Composer solver without policy or platform bypass flags
- **Before `$gsd-verify-work`:** The approved dependency graph must be audited and the full PHPUnit suite green
- **Max feedback latency:** 60 seconds for manifest checks

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Threat Ref | Secure Behavior | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|------------|-----------------|-----------|-------------------|-------------|--------|
| TBD | TBD | 1 | COMP-01 | T-01-01 | A clean PHP 8.4 solve/install has no `--ignore-platform-req*`, `--no-blocking`, or development-branch dependency | clean-environment integration | Isolated copy: `composer install --prefer-dist --no-interaction --no-scripts --no-progress` | ❌ decision gate | ⬜ pending |
| TBD | TBD | 1 | COMP-02 | T-01-02 | Root manifest declares `php: ^8.2`, accurately covering PHP 8.2–8.4 | manifest | `composer validate --strict --no-check-publish` | ✅ | ⬜ pending |
| TBD | TBD | 1 | COMP-03 | T-01-03 | Roave is removed only alongside native advisory blocking/audit policy; no policy or platform bypass is added | resolver/security | Isolated copy: `composer update --dry-run --prefer-dist --no-interaction --no-scripts --no-progress` | ❌ decision gate | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `checkpoint:human-verify` — choose the response to the seven active advisories affecting all stable Laravel 11 releases while preserving the no-bypass security rule.
- [ ] Isolated clean-directory procedure — solver evidence must not create `composer.lock` or `vendor/` in the repository.
- [ ] Phase 3 CI assertion — require Composer 2.10+ and reject policy-disabling variables and platform-ignore flags.

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Security/scope reconciliation | COMP-01, COMP-03 | Current stable Laravel 11 versions are rejected by seven active advisories; no automated compliant graph exists today | At the human checkpoint, select an approved stable remediation, explicitly broaden scope, or pause. Do not accept `11.x-dev`, a policy override, or a broad advisory ignore. |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 60s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
