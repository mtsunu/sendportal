---
phase: 02-reproducible-dependency-snapshot
verified: 2026-07-25T00:00:00Z
status: passed
score: 4/4 must-haves verified
behavior_unverified: 0
overrides_applied: 0
---

# Phase 2: Reproducible Dependency Snapshot Verification Report

**Phase Goal:** Operators, CI, and deployments install one validated, security-checked dependency graph rather than independently resolving packages.
**Verified:** 2026-07-25 (on real PHP 8.4.23 + Composer 2.10.2)
**Status:** passed
**Re-verification:** No — initial verification

## Goal Achievement

Every success criterion was verified by executing the guarded commands and the tracked-phar platform check directly, not by trusting SUMMARY.md. All commands run through `bin/composer-policy` (validate/audit/install/update) or the SHA-256-verified `tools/composer/composer-2.10.2.phar` (read-only platform check). No bypass flag was used.

### Observable Truths (Success Criteria)

| # | Truth (Success Criterion) | Status | Evidence |
| - | ------------------------- | ------ | -------- |
| 1 | Reviewed `composer.lock` committed, synchronized with `composer.json`, strict metadata validation succeeds (DEPS-01) | ✓ VERIFIED | `git ls-files composer.lock` → `composer.lock`; `git check-ignore composer.lock` → EXIT 1 (not ignored); `composer.lock` absent from `.gitignore`; `php bin/composer-policy validate --strict --no-interaction` → `./composer.json is valid`, EXIT 0; content-hash `41abd56c5581800607cc9d3c28862a76` |
| 2 | Locked graph passes PHP 8.4 platform-requirement check (DEPS-02) | ✓ VERIFIED | `php tools/composer/composer-2.10.2.phar check-platform-reqs --lock --no-interaction` → EXIT 0; 27 rows all `success` (php 8.4.23, every ext-* success); zero `failed`/`missing` rows |
| 3 | Locked graph passes a non-bypassed security audit / configured policy (DEPS-03) | ✓ VERIFIED | `php bin/composer-policy audit --locked --no-interaction` → EXIT 0, "Found 3 ignored security vulnerability advisories affecting 1 package" (laravel/framework), no non-ignored advisory; `composer.json` policy `block: true`, `audit: fail`; exactly 3 PKSA IDs; guard `$rationale` byte-identical to composer.json reasons |
| 4 | Local, CI, deployment install the committed lockfile instead of freshly resolving (DEPS-04) | ✓ VERIFIED | `php bin/composer-policy install --prefer-dist --no-interaction` → "Installing dependencies from lock file", "Nothing to install, update or remove", EXIT 0 (no re-resolution); README documents guarded `install` (local), `install --no-dev --optimize-autoloader` (deploy), reserves `update`, adds "Lockfile review"; `.github/workflows/ci.yml` guarded `install ... --no-scripts`, unchanged vs HEAD; route audit = 6 records, all classification=supported/executable=guard |

**Score:** 4/4 truths verified (0 present, behavior-unverified)

### Required Artifacts

| Artifact | Expected | Status | Details |
| -------- | -------- | ------ | ------- |
| `composer.lock` | Frozen, drift-proven, git-tracked snapshot | ✓ VERIFIED | Tracked; content-hash `41abd56c5581800607cc9d3c28862a76`; `aws/aws-sdk-php` locked at `3.388.13` (the frozen Phase-1 version, NOT the drifted `3.389.0`), confirming zero re-resolution drift |
| `.gitignore` | `composer.lock` ignore entry removed | ✓ VERIFIED | No `composer.lock` line present; `git check-ignore` EXIT 1 |
| `composer.json` | 3 advisory reasons naming v11.55.0 + forward expiry; block/audit strict | ✓ VERIFIED | `grep -c 'PKSA-'`=3, `grep -c 'v11.55.0'`=3, `grep -c 'Phase 2 lockfile review'`=0; `block:true`/`audit:fail` intact |
| `bin/composer-policy` | `$rationale` constant in lockstep | ✓ VERIFIED | Line 365 string byte-identical to composer.json reasons (direct string comparison: IDENTICAL); guarded install EXIT 0 confirms strict-equality manifest policy passes |
| `README.md` | Install-vs-update contract, --no-dev deploy, Lockfile review | ✓ VERIFIED | Lines 37-69: install contract, `--no-dev --optimize-autoloader` deploy install, "Lockfile review" three-command procedure |

### Key Link Verification

| From | To | Via | Status | Details |
| ---- | -- | --- | ------ | ------- |
| `composer.json` | `composer.lock` | `validate --strict` synchronization | ✓ WIRED | EXIT 0, `./composer.json is valid` |
| `composer.json` advisory map | `bin/composer-policy` `$rationale` | strict byte-equality (guard fails closed on mismatch) | ✓ WIRED | Byte-identical; guarded install EXIT 0 |
| committed lock | local/CI/deploy installs | guarded lock-consuming `install` | ✓ WIRED | install consumes lock, no re-resolution; ci.yml + README document the same guarded path |

### Requirements Coverage

| Requirement | Source Plan | Description | Status | Evidence |
| ----------- | ----------- | ----------- | ------ | -------- |
| DEPS-01 | 02-01, 02-02 | Reviewed committed `composer.lock` synchronized under `validate --strict` | ✓ SATISFIED | Truth 1 |
| DEPS-02 | 02-02 | Locked graph passes `check-platform-reqs` on PHP 8.4 | ✓ SATISFIED | Truth 2 |
| DEPS-03 | 02-02 | Locked graph passes non-bypassed security check | ✓ SATISFIED | Truth 3 |
| DEPS-04 | 02-02 | Local/CI/deploy install committed lock, not fresh resolution | ✓ SATISFIED | Truth 4 |

All four phase requirement IDs appear in PLAN frontmatter and are mapped to Phase 2 in REQUIREMENTS.md (all marked Complete). No orphaned requirements.

### Behavioral Spot-Checks

| Behavior | Command | Result | Status |
| -------- | ------- | ------ | ------ |
| Strict validation | `php bin/composer-policy validate --strict --no-interaction` | `./composer.json is valid`, EXIT 0 | ✓ PASS |
| PHP 8.4 platform | `php tools/composer/composer-2.10.2.phar check-platform-reqs --lock --no-interaction` | 27 rows success, EXIT 0 | ✓ PASS |
| Non-bypassed audit | `php bin/composer-policy audit --locked --no-interaction` | 3 ignored / 1 package, EXIT 0 | ✓ PASS |
| Lock-consuming install | `php bin/composer-policy install --prefer-dist --no-interaction` | from lock file, no re-resolution, EXIT 0 | ✓ PASS |
| Guard suite (cross-phase regression) | `php tests/Composer/ComposerPolicyGuardTest.php` | "Composer policy guard tests passed (full suite)", EXIT 0 | ✓ PASS |
| Route audit | `php tests/Composer/ComposerPolicyGuardTest.php --route-audit` | 6 records, all supported/guard, EXIT 0 | ✓ PASS |

### Anti-Patterns Found

None. No unreferenced `TBD`/`FIXME`/`XXX` debt markers in `composer.json`, `bin/composer-policy`, `README.md`, or `.gitignore`.

### Orchestrator Deviation Checks (confirmed, not taken on faith)

1. **`update --lock` substituted for plan-prescribed `update --prefer-dist`** — CONFIRMED LEGITIMATE. A full `update` re-resolves and bumps `aws/aws-sdk-php` to `3.389.0`. The committed lock holds `aws/aws-sdk-php 3.388.13` (the Phase-1-approved version) with content-hash `41abd56c5581800607cc9d3c28862a76` and passes `validate --strict`. Zero drift; the freeze-only invariant held.
2. **Cross-phase guard-test regression fix (commit 1fb1c31)** — CONFIRMED. Full guard suite exits 0. Route audit reports exactly 6 classified route records (1 in ci.yml, 5 in README), every one classification=supported / executable=guard. No un-guarded route slipped in. The stale "3 tracked records" count and the on-disk lockfile-absence precondition were correctly updated for the now-committed lock and the 3 added guarded README commands.

All four commits exist: `40466bf`, `6b2df99`, `ea04c01`, `1fb1c31`.

### Human Verification Required

None. Every success criterion resolves to a deterministic command outcome executed on the real PHP 8.4.23 + Composer 2.10.2 runtime.

### Gaps Summary

No gaps. The phase goal is achieved: a single reviewed, tracked, synchronized `composer.lock` is the frozen install contract; it is PHP 8.4 platform-clean, passes a non-bypassed audit with exactly three owner-accepted advisory exceptions (guard in lockstep), and local/CI/deploy paths are documented and wired to consume the committed lock rather than re-resolving. Phase 3 CI/runtime work correctly remains out of scope (ci.yml byte-unchanged, no PHP 8.4 job or new gate added).

---

_Verified: 2026-07-25_
_Verifier: Claude (gsd-verifier)_
