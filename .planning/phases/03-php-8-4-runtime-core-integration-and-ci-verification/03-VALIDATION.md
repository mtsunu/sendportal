---
phase: 3
slug: php-8-4-runtime-core-integration-and-ci-verification
# status lifecycle: draft (seeded by plan-phase) → validated (set by validate-phase §6)
# audit-milestone §5.5 distinguishes NOT-VALIDATED (draft) from PARTIAL (validated + nyquist_compliant: false) (#2117)
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-07-25
---

# Phase 3 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit 10.5 (existing) + GitHub Actions CI gates (bash `-eo pipefail`) |
| **Config file** | `phpunit.xml.dist`; CI at `.github/workflows/ci.yml` |
| **Quick run command** | `vendor/bin/phpunit` |
| **Full suite command** | CI matrix job (PHP 8.2/8.3/8.4 × MySQL/PostgreSQL) via `.github/workflows/ci.yml` |
| **Estimated runtime** | ~ per-job CI runtime (multi-minute) |

---

## Sampling Rate

- **After every task commit:** Run `vendor/bin/phpunit` (or lint the edited `ci.yml` locally)
- **After every plan wave:** Push branch / trigger CI to exercise the full matrix
- **Before `/gsd-verify-work`:** CI matrix must be green on all three PHP versions and both engines
- **Max feedback latency:** CI job duration

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Threat Ref | Secure Behavior | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|------------|-----------------|-----------|-------------------|-------------|--------|
| {N}-01-01 | 01 | 1 | RUNTIME-01 / CI-01 / CI-02 | — | 8.4 matrix job runs, install is script-enabled | CI | `.github/workflows/ci.yml` job passes | ✅ | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky · (planner refines this table)*

---

## Wave 0 Requirements

*Existing infrastructure covers all phase requirements — PHPUnit suite and CI matrix already exist; this phase extends CI, it does not stand up new test infrastructure.*

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| CI job fails on each of the five failure surfaces independently | CI-02 | Requires inducing a failure to observe the gate; not a green-path assertion | Observe that Composer metadata, platform-reqs, audit, Laravel/Core boot, and either per-engine PHPUnit run each fail the job as its own step |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < CI job duration
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
