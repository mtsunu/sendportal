---
phase: 2
slug: reproducible-dependency-snapshot
# status lifecycle: draft (seeded by plan-phase) → validated (set by validate-phase §6)
# audit-milestone §5.5 distinguishes NOT-VALIDATED (draft) from PARTIAL (validated + nyquist_compliant: false) (#2117)
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-07-24
---

# Phase 2 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.
> Seeded from `02-RESEARCH.md` § Validation Architecture. This is a Composer-command,
> exit-code-based phase — verification is native `composer` review commands on real
> PHP 8.4, NOT a PHPUnit/unit-test suite (the PHPUnit runtime matrix is Phase 3).

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Composer 2.10.2 native review commands (exit-code based); routed through the Phase-1 guard `bin/composer-policy` where a guarded route exists |
| **Config file** | `composer.json` (manifest + `config.policy`), `composer.lock` (the frozen graph) |
| **Quick run command** | `php bin/composer-policy validate --strict --no-interaction` |
| **Full suite command** | Three-command review: `validate --strict` + `check-platform-reqs --lock` (tracked phar) + `audit --locked`, plus a guarded `install` smoke |
| **Estimated runtime** | ~15 seconds (network-free; audit uses the locked graph) |

---

## Sampling Rate

- **After every task commit:** Run `php bin/composer-policy validate --strict --no-interaction` (fast composer.json↔composer.lock sync check)
- **After every plan wave / before committing the lock:** Run the full three-command review procedure **plus** a guarded `install`/`update` smoke (catches Pitfall 2 — the `composer.json`↔`bin/composer-policy` advisory-constant lockstep)
- **Before `/gsd-verify-work`:** All three review checks EXIT 0 on PHP 8.4 with evidence captured
- **Max feedback latency:** ~15 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Threat Ref | Secure Behavior | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|------------|-----------------|-----------|-------------------|-------------|--------|
| 02-01-01 | 01 | 1 | DEPS-01 | — | Committed lock synchronized with manifest; no drift from Phase-1 graph | command-exit | `php bin/composer-policy validate --strict --no-interaction` (EXIT 0, `./composer.json is valid`); `git ls-files composer.lock` non-empty; empty name→version drift diff | ✅ existing | ⬜ pending |
| 02-02-01 | 02 | 2 | DEPS-01, DEPS-03 | Silent broadening / reason drift | Exactly 3 owner-approved IDs ignored; manifest+guard reasons in lockstep | command-exit | `php bin/composer-policy audit --locked --no-interaction` (EXIT 0, 3 ignored) **and** guarded `install` EXIT 0 (no `Composer manifest policy rejected`); `grep -c 'Phase 2 lockfile review' composer.json` = 0 | ✅ existing | ⬜ pending |
| 02-02-02 | 02 | 2 | DEPS-02, DEPS-04 | Bypass-flag forced green | Locked graph platform-clean on PHP 8.4; all paths install from lock, no bypass | command-exit | `php tools/composer/composer-2.10.2.phar check-platform-reqs --lock --no-interaction` (all rows `success`, EXIT 0); `git diff --quiet` on `ci.yml`; README documents `install` vs `update` + `--no-dev --optimize-autoloader` | ✅ existing | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

*None — verification is native Composer-command exit-code based; all commands exist and pass on the target today. No test-framework install or stub files required. (The PHP 8.4 CI job that would automate these permanently is Phase 3 / CI-01, explicitly out of scope.)*

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| README "Lockfile review" + `install` vs `update` + `--no-dev` deploy guidance is accurate and repeatable | DEPS-04 | Documentation review — correctness of prose/procedure is not command-assertable | Read README "Dependency management" / "Lockfile review"; confirm the three review commands and the `install` (lock-consuming) vs `update` (upgrade-only) + production `install --no-dev --optimize-autoloader` guidance match the guarded reality |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 15s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** {pending / approved YYYY-MM-DD}
