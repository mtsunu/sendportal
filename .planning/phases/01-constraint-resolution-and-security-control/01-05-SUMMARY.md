---
phase: 01-constraint-resolution-and-security-control
plan: 05
subsystem: dependency-security
tags: [composer, php-8.4, provenance, sha256, route-audit]
requires:
  - phase: 01-04
    provides: Composer native policy, guarded CI/operator entry points, and baseline route audit
provides:
  - Repository-tracked Composer 2.10.2 PHAR verified against a strict SHA-256 provenance record
  - Fail-closed Composer guard that never selects a PATH, COMPOSER_BIN, cache, or global executable
  - Per-command-chain mutation audit with isolated negative fixture repositories
affects: [phase-02-lockfile, phase-03-runtime-validation, dependency-installation]
tech-stack:
  added: [Composer 2.10.2 PHAR]
  patterns: [repository-owned executable provenance, PHP_BINARY argument-array subprocesses, per-chain command audit]
key-files:
  created:
    - tools/composer/composer-2.10.2.phar
    - tools/composer/composer-2.10.2.phar.sha256
  modified:
    - bin/composer-policy
    - tests/Composer/ComposerPolicyGuardTest.php
    - README.md
key-decisions:
  - "Use only the checked-in Composer 2.10.2 PHAR after strict provenance, digest, exact-version, and policy-capability checks."
  - "Audit each command-chain segment independently; a sibling guarded command cannot approve a direct Composer invocation."
patterns-established:
  - "Composer commands run as [PHP_BINARY, absolute repository PHAR, ...arguments] only after fail-closed preflight."
  - "Route-audit fixtures live in disposable Git roots so they cannot affect git ls-files production evidence."
requirements-completed: [COMP-01, COMP-02, COMP-03]
coverage:
  - id: D1
    description: Repository-owned Composer distribution rejects tampering, overrides, and PATH shadows before delegation.
    requirement: COMP-03
    verification:
      - kind: integration
        ref: php tests/Composer/ComposerPolicyGuardTest.php
        status: pass
      - kind: other
        ref: deliberate PATH-shadow marker proof
        status: pass
    human_judgment: false
  - id: D2
    description: Every supported Composer mutation is classified per command-chain segment and direct bypass forms fail.
    requirement: COMP-03
    verification:
      - kind: integration
        ref: php tests/Composer/ComposerPolicyGuardTest.php --route-audit
        status: pass
    human_judgment: false
  - id: D3
    description: A fresh isolated PHP 8.4 checkout resolves through the guarded repository distribution without platform bypasses.
    requirement: COMP-01
    verification:
      - kind: integration
        ref: PHP 8.4.23 isolated guarded install and strict validation
        status: pass
    human_judgment: false
metrics:
  duration: 10m 52s
  completed: 2026-07-23
status: complete
---

# Phase 01 Plan 05: Trusted Composer Distribution and Per-Chain Audit Summary

**Composer 2.10.2 is pinned as a repository-verified PHAR, and every tracked dependency mutation is audited per command-chain segment.**

## Performance

- **Duration:** 10m 52s
- **Started:** 2026-07-23T02:35:41Z
- **Completed:** 2026-07-23T02:46:33Z
- **Tasks:** 2 completed
- **Files modified:** 5

## Accomplishments

- Imported the official Composer 2.10.2 PHAR after separately downloading and matching its official SHA-256; the checked-in record captures the release URL, checksum source, verification method, and exact digest.
- Replaced PATH resolution with a canonical repository-root PHAR path, strict regular-file/provenance/hash validation, exact-version and native-policy probes, and PHP_BINARY array subprocesses.
- Reworked the route audit to tokenize normalized command chains and reject direct Composer forms independently, using disposable Git fixture roots for bypass regressions.
- Documented that guarded install/update commands use the tracked distribution with no invocation-time Composer download and fail closed when provenance is unavailable.

## Task Commits

1. **Task 1 RED: trusted-distribution regression** — `5019067` (test)
2. **Task 1 GREEN: pinned distribution and fail-closed guard** — `5897e6c` (feat)
3. **Task 2 RED: per-chain bypass regression** — `c2d4edc` (test)
4. **Task 2 GREEN: per-chain route audit** — `5d392c3` (test)
5. **Provenance clarification** — `3ae9b66` (fix)

## Files Created/Modified

- `bin/composer-policy` — fixed-path, strict-integrity Composer policy entry point.
- `tools/composer/composer-2.10.2.phar` — official pinned Composer distribution.
- `tools/composer/composer-2.10.2.phar.sha256` — strict, parseable release provenance and digest record.
- `tests/Composer/ComposerPolicyGuardTest.php` — dependency-free provenance, shadow, override, and per-chain audit regression suite.
- `README.md` — guarded-command distribution and fail-closed guidance.

## Verification

- `php -l bin/composer-policy` and `php -l tests/Composer/ComposerPolicyGuardTest.php` passed.
- `php tests/Composer/ComposerPolicyGuardTest.php` passed.
- `php tests/Composer/ComposerPolicyGuardTest.php --route-audit` passed with 12 real tracked records; CI and README guard records were present.
- The PHAR SHA-256 matched the checked-in record; a compliant-looking PATH shadow left no marker.
- A fresh temporary copy installed and validated successfully through the guard on Herd PHP 8.4.23 / Composer 2.10.2 with a fresh Composer home and all override variables removed.
- The repository still has no `composer.lock` or `vendor/`; the PHP `^8.2`, Laravel `^11.0`, Core `^3.0`, native blocking/audit policy, `ignore-unreachable: false`, and three approved advisory IDs were asserted unchanged.

## Decisions Made

- Trust only the fixed, repository-derived Composer 2.10.2 PHAR; do not probe or delegate to any caller-controlled executable, including one only presented for diagnostics.
- Treat provenance metadata as an exact four-line contract to prevent alternate filenames, release URLs, checksum sources, or extra digest entries from changing the trusted target.
- Treat each shell-chain segment as its own route decision, so a guarded neighbor never whitelists a direct Composer mutation.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Restored the policy guard executable mode**
- **Found during:** Task 2
- **Issue:** Replacing the guard file content changed its pre-existing executable bit from `0755` to `0644`.
- **Fix:** Restored mode `0755` before committing the completed route-audit task.
- **Files modified:** `bin/composer-policy`
- **Verification:** The guard ran through PHP_BINARY and retained its executable mode in Git.
- **Committed in:** `5d392c3`

**Total deviations:** 1 auto-fixed (Rule 1)

## Issues Encountered

The initial guarded implementation found the official Composer PHAR and checksum unavailable locally. A one-time HTTPS import from `getcomposer.org` was approved; the independently downloaded official SHA-256 matched `5ee7125f8a30a34d246cefdc0bc85b8a783b28f2aec968994118512350d28027`. No guard invocation downloads Composer or uses a cache fallback.

## User Setup Required

None.

## Next Phase Readiness

Phase 2 can generate and review the first committed lockfile using the now repository-owned guarded Composer route. The phase retains the intentional no-lockfile boundary in the main checkout.

## Self-Check: PASSED

- Confirmed all five implementation artifacts exist.
- Confirmed commits `5019067`, `5897e6c`, `c2d4edc`, `5d392c3`, and `3ae9b66` exist in Git history.

---

*Phase: 01-constraint-resolution-and-security-control*
*Completed: 2026-07-23*
