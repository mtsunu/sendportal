---
phase: 01-constraint-resolution-and-security-control
plan: 04
subsystem: dependency-security-policy
tags: [composer, php-8.4, security-policy, ci, route-audit]
requires:
  - phase: 01-03
    provides: "A fail-closed Composer native policy with PHP ^8.2 and the approved three-ID advisory exception map."
provides:
  - "An interpreter-bound Composer >=2.10.0 and native-policy capability guard before dependency resolution."
  - "CI and documented operator dependency routes that invoke the same guard."
  - "A deterministic audit of every tracked Composer mutation occurrence."
affects: [phase-02-lockfile-review, phase-03-runtime-validation, ci]
tech-stack:
  added: []
  patterns:
    - "Resolve Composer from PATH, canonicalize it, and invoke every Composer subprocess as [PHP_BINARY, composer path, ...arguments]."
    - "Audit every tracked Composer mutation occurrence and fail supported routes that do not reach bin/composer-policy."
key-files:
  created:
    - "bin/composer-policy"
    - "tests/Composer/ComposerPolicyGuardTest.php"
  modified:
    - ".github/workflows/ci.yml"
    - "README.md"
key-decisions:
  - "Reject executable, policy, manifest, audit, and platform override inputs before Composer path resolution."
  - "Require both a parsed Composer >=2.10.0 version and a successful native policy command probe before delegation."
requirements-completed: [COMP-03]
coverage:
  - id: D1
    description: "Fail-closed Composer executable selection, version floor, policy capability, and bypass rejection."
    requirement: COMP-03
    verification:
      - kind: unit
        ref: "tests/Composer/ComposerPolicyGuardTest.php"
        status: pass
      - kind: integration
        ref: "Herd PHP 8.4.23 + Herd Composer 2.10.2 clean-home guarded update --dry-run"
        status: pass
    human_judgment: false
  - id: D2
    description: "CI and documented operator Composer routes reach the policy guard, while all tracked mutation occurrences are classified."
    requirement: COMP-03
    verification:
      - kind: unit
        ref: "tests/Composer/ComposerPolicyGuardTest.php --route-audit"
        status: pass
    human_judgment: false
duration: 9min
completed: 2026-07-23
status: complete
---

# Phase 01 Plan 04: Composer Policy Entry-Point Guard Summary

**Composer dependency resolution now fails closed unless the PATH-selected Composer runs under PHP_BINARY, reports 2.10.0 or newer, and proves native-policy command support.**

## Performance

- **Duration:** 9 min
- **Started:** 2026-07-23T00:23:52Z
- **Completed:** 2026-07-23T00:32:37Z
- **Tasks:** 2
- **Files modified:** 4 production/test files, 1 summary

## Accomplishments

- Added `bin/composer-policy`, which rejects `COMPOSER_BIN`, alternate manifests, policy/audit/platform bypass variables and options before resolving Composer; it probes version and `policy --help` through `PHP_BINARY` before delegation.
- Added dependency-free, strict-types regression coverage for Composer 2.9.5 rejection, a 2.10.2 version-spoofing shim, compliant interpreter-bound execution, and policy/platform bypass attempts.
- Routed CI and documented install/update commands through `php bin/composer-policy`; the route audit inspected all 229 tracked files and classified 87 mutation occurrences.

## Verification Evidence

| Check | Result |
| --- | --- |
| Dependency-free policy guard regression | Passed, including old Composer, spoofed `COMPOSER_BIN`, compliant native-policy capability, interpreter binding, and bypass rejections. |
| Composer 2.9.5 disposable install | Rejected before resolution with `Composer >= 2.10.0 is required`; no lockfile or `vendor/` directory appeared. |
| Herd PHP 8.4.23 / Composer 2.10.2 clean-home dry run | Passed through the guard with a scrubbed override environment; Composer reported PHP 8.4.x and resolved tagged Laravel 11 and SendPortal Core 3 selections without a repository lockfile. |
| Manifest boundary assertion | Passed: `php: ^8.2`, Laravel `^11.0`, Core `^3.0`, exact three advisory IDs, `block: true`, `audit: fail`, and `ignore-unreachable: false` remain unchanged. |
| Tracked route audit | Passed with 87 deterministic records. The output records path, original line, normalized form, operation, classification, and rationale for every match; CI and both README commands are supported and all planning/test/research occurrences are explicitly unsupported. |

### Complete tracked-route audit

The reproducible complete record is emitted by:

```sh
php tests/Composer/ComposerPolicyGuardTest.php --route-audit
```

The final run inspected every `git ls-files -z` entry, produced 87 records, and ended with `Composer route audit passed with 87 classified records.` Supported records were:

- `.github/workflows/ci.yml:43` — `php bin/composer-policy install -q --no-ansi --no-interaction --no-scripts --no-progress --prefer-dist`
- `README.md:38` — `php bin/composer-policy install --prefer-dist --no-interaction`
- `README.md:39` — `php bin/composer-policy update --prefer-dist --no-interaction`

All remaining records are planning, historical, research, or test prose and carry the deterministic `unsupported` classification and rationale from the audit output.

## Task Commits

1. **Task 1: Gate one real dependency-resolution path before Composer can run** — `c9faeff` (red test), `cedcc5b` (green implementation).
2. **Task 2: Make CI and the documented operator route consume the guarded entry point** — `25d4cf5` (red test), `e15ae1c` (green implementation).

## Files Created/Modified

- `bin/composer-policy` — strict-types pre-resolution Composer guard.
- `tests/Composer/ComposerPolicyGuardTest.php` — dependency-free guard regression and route audit.
- `.github/workflows/ci.yml` — existing install arguments routed through the guard.
- `README.md` — Composer floor and guarded install/update instructions.

## Decisions Made

- Use a non-shell PATH lookup plus argument-array `proc_open` calls so Composer cannot select its interpreter from a shebang or an environment-selected executable.
- Treat native policy capability as a separate required probe from the semantic version floor.
- Keep CI topology, PHP support, Laravel/Core bounds, native advisory policy, and the no-lockfile Phase 1 boundary intact.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Preserve delegated Composer output from the guard**
- **Found during:** Task 1
- **Issue:** The initial subprocess wrapper captured and discarded delegated Composer output, preventing verbose PHP/runtime evidence from reaching callers.
- **Fix:** Forwarded the delegated command output after all preflights complete while retaining argument-array execution.
- **Files modified:** `bin/composer-policy`
- **Verification:** The clean-home Herd PHP 8.4.23 / Composer 2.10.2 dry run passed and its required verbose output checks succeeded.
- **Committed in:** `cedcc5b`

**2. [Rule 1 - Tracking state] Reconciled the stale plan cursor before phase close-out**
- **Found during:** Required GSD tracking update
- **Issue:** `STATE.md` still named Plan 1 even though Plans 01-01 through 01-03 already had committed summaries; a single advance left Plan 2 marked ready despite all four summaries existing.
- **Fix:** Advanced the existing GSD state cursor through Plan 4 until it reached `ready_for_verification`, while preserving the recorded completion count.
- **Files modified:** `.planning/STATE.md`
- **Verification:** State now reports `Plan: 4 of 4`, four completed plans, and `Phase complete — ready for verification`.

**Total deviations:** 2 auto-fixed (Rule 1).
**Impact on plan:** Required for correct operator/CI diagnostics and accurate phase tracking; no scope expansion or policy-boundary change.

## Issues Encountered

- The restricted sandbox could not resolve Packagist DNS for the required clean-home dry run. The same disposable verification was rerun with approved network access and passed.

## Known Stubs

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Phase 2 can generate and review the first committed lockfile using the guarded Composer route.
- The temporary advisory exceptions still require the planned Phase 2 expiry/re-approval check.

## Self-Check: PASSED

- `bin/composer-policy`, `tests/Composer/ComposerPolicyGuardTest.php`, and this summary exist.
- TDD commits `c9faeff`, `cedcc5b`, `25d4cf5`, and `e15ae1c` exist in Git history.
- The dependency-free guard test and its full tracked-route audit pass.
