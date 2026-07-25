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
| 01-01 / Task 1 | 01-01 | 1 | COMP-01, COMP-03 | T-01-01, T-01-03 | A clean PHP 8.4 solver uses a fresh COMPOSER_HOME, scrubbed override environment, and only the three resolved D-02 IDs | clean-environment solver | Isolated copy: `composer update --dry-run --prefer-dist --no-interaction --no-scripts --no-progress` | ✅ plan verification | ⬜ pending |
| 01-02 / Task 1 | 01-02 | 2 | COMP-02, COMP-03 | T-01-04, T-01-05 | Root manifest declares `php: ^8.2`, retains native blocking/audit policy, and permits only the documented per-ID exception | manifest | `composer validate --strict --no-check-publish` plus exact JSON policy assertion | ✅ plan verification | ⬜ pending |
| 01-02 / Task 2 | 01-02 | 2 | COMP-01, COMP-03 | T-01-04, T-01-06, T-01-07 | A clean install has no inherited/global Composer configuration or bypass, and the ignore-free reporting audit names exactly the approved three IDs | clean-environment integration | Temporary install, `composer audit --locked`, then ignore-free `composer audit --locked --format=json` exact-ID parser | ✅ plan verification | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [x] Resolved D-02 decision — the project owner accepted only the documented internal-only three-ID residual-risk exception; no human checkpoint remains.
- [ ] Isolated fresh-COMPOSER_HOME procedure — solver/install evidence must not create `composer.lock` or `vendor/` in the repository and must explicitly unset Composer policy/platform override variables.
- [ ] Phase 3 CI assertion — require Composer 2.10+ and reject policy-disabling variables and platform-ignore flags.

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| No manual-only Phase 1 verification remains | COMP-01, COMP-03 | The owner resolved the exception in D-02; its exact bounds, configured audit, and ignore-free evidence audit are automated plan checks. | Review the generated summary during normal phase review; execution must stop automatically if the configured or evidence audit diverges from the three approved IDs. |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 60s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
