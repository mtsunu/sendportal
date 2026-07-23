---
phase: 01-constraint-resolution-and-security-control
plan: 11
subsystem: dependency-security
tags: [composer, route-audit, provenance, php, fail-closed]
requires:
  - phase: 01-10
    provides: bounded workflow, shell, Docker, and literal-PHP route audit grammar
provides:
  - Finite root Composer script-handler provenance and rejection evidence
  - Source-level fail-closed evidence for indirect marker-bearing supported PHP programs
affects: [phase-01-verification, phase-02-lockfile, dependency-installation, supply-chain-security]
tech-stack:
  added: []
  patterns: [finite Composer-script grammar, marker-gated PHP program fallback, disposable staged Git fixtures]
key-files:
  created:
    - .planning/phases/01-constraint-resolution-and-security-control/01-11-SUMMARY.md
  modified:
    - tests/Composer/ComposerPolicyGuardTest.php
key-decisions:
  - "Treat root composer.json scripts as a finite event-to-scalar-or-list-of-scalars provenance surface before generic JSON exclusion."
  - "Emit one unclassified-php source record for any supported marker-bearing PHP program without a bounded child launch record."
patterns-established:
  - "Composer script shortcuts normalize only @composer and @php forms needed for the bounded literal grammar; guarded commands still use ComposerPolicyCommandContract."
  - "Indirect PHP dispatch remains unclassified evidence rather than a callback, variable, or data-flow interpretation."
requirements-completed: [COMP-01, COMP-02, COMP-03]
coverage:
  - id: D1
    description: "Root Composer scripts retain finite event, ordinal, physical-line, and raw-handler evidence; direct and @composer mutations fail while canonical literal guards remain supported."
    requirement: COMP-03
    verification:
      - kind: integration
        ref: php tests/Composer/ComposerPolicyGuardTest.php --group=route-audit-fail-closed
        status: pass
      - kind: integration
        ref: php tests/Composer/ComposerPolicyGuardTest.php --route-audit
        status: pass
    human_judgment: false
  - id: D2
    description: "Indirect callable, variable-function, popen, and other marker-bearing supported PHP dispatches fail closed with deterministic source-level evidence."
    requirement: COMP-03
    verification:
      - kind: integration
        ref: php tests/Composer/ComposerPolicyGuardTest.php
        status: pass
      - kind: integration
        ref: php tests/Composer/ComposerPolicyGuardTest.php --group=route-audit-fail-closed
        status: pass
    human_judgment: false
metrics:
  duration: 9min
  completed: 2026-07-23
status: complete
---

# Phase 01 Plan 11: Composer Script and PHP Dispatch Closure Summary

**Finite root Composer-script provenance and marker-bearing PHP no-record fallbacks now prevent direct dependency mutations from silently escaping the route audit.**

## Performance

- **Duration:** 9 min
- **Started:** 2026-07-23T22:21:20+07:00
- **Completed:** 2026-07-23T22:30:01Z
- **Tasks:** 2/2
- **Files modified:** 1

## Accomplishments

- Audited only root `composer.json` script handlers before generic JSON exclusion, preserving event, ordinal, source line, raw handler, operation, and classification evidence.
- Rejected direct Composer and `@composer` script mutations, retained Laravel hooks as non-candidates, and routed literal direct/`@php` guard forms through `ComposerPolicyCommandContract`.
- Added one deterministic `unclassified-php` fallback for marker-bearing supported PHP programs that have no bounded process-launch record, without executing fixtures or interpreting callbacks/data flow.

## Task Commits

1. **Task 1 RED: Composer script audit fixtures** — `041bab0`
2. **Task 1 GREEN: root Composer script provenance** — `59f4133`
3. **Task 2 RED: indirect PHP dispatch fixtures** — `9cd9865`
4. **Task 2 GREEN: PHP no-record fallback** — `5b04d64`

## Files Created/Modified

- `tests/Composer/ComposerPolicyGuardTest.php` — Adds the bounded root-script extractor, staged provenance regressions, and supported-PHP finalizer.

## Decisions Made

- Restrict Composer-script extraction to the root manifest's top-level `scripts` map and scalar/list handler shapes; unsupported marker-bearing shapes are audit failures, not interpreted script behavior.
- Keep the five literal PHP process APIs unchanged; unmodeled dispatch is represented as source evidence rather than inferred execution.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Scoped the PHP finalizer to supported production route paths**

- **Found during:** Task 2
- **Issue:** Applying the new source fallback to every PHP file treated the shared command-contract source as a route candidate, breaking an existing non-route control fixture.
- **Fix:** Limited finalization to supported production route paths while retaining the required `scripts/*.php` behavior.
- **Files modified:** `tests/Composer/ComposerPolicyGuardTest.php`
- **Verification:** Focused route group, full dependency-free suite, and production audit pass with exactly the existing guarded CI/README records.
- **Committed in:** `5b04d64`

---

**Total deviations:** 1 auto-fixed Rule 1 bug.

## Validation Evidence

- `php -l tests/Composer/ComposerPolicyGuardTest.php` — pass
- `php tests/Composer/ComposerPolicyGuardTest.php --group=route-audit-fail-closed` — pass
- `php tests/Composer/ComposerPolicyGuardTest.php` — pass
- `php tests/Composer/ComposerPolicyGuardTest.php --route-audit` — pass with three existing guarded CI/README records
- Manifest-policy, CI-order, protected-artifact, and absent root `composer.lock`/`vendor` assertions — pass

## Known Stubs

None.

## User Setup Required

None.

## Next Phase Readiness

The two verified Phase 01 route-audit bypasses now have finite, source-provenanced fail-closed coverage without changing the Composer manifest, policy, guard, PHAR, CI matrix, lockfile boundary, or Laravel/SendPortal Core integration.

## Self-Check: PASSED

- Confirmed the route-audit source and Summary exist.
- Confirmed all four RED/GREEN task commits exist.

---
*Phase: 01-constraint-resolution-and-security-control*
*Completed: 2026-07-23*
