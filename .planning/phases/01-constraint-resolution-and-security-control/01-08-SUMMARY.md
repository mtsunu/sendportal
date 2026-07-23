---
phase: 01-constraint-resolution-and-security-control
plan: 08
subsystem: testing
tags: [composer, security, route-audit, shell-parsing]
requires:
  - phase: 01-07
    provides: fail-closed production Composer route audit and shared command contract
provides:
  - bounded recursive classification of literal bash, sh, zsh, and eval payloads
  - explicit unclassified evidence for dynamic, malformed, and over-limit evaluator forms
affects: [phase-1-verification, composer-policy]
tech-stack:
  added: []
  patterns: [bounded quote-aware shell evaluator recursion]
key-files:
  created: [.planning/phases/01-constraint-resolution-and-security-control/01-08-SUMMARY.md]
  modified: [tests/Composer/ComposerPolicyGuardTest.php]
key-decisions:
  - "Limit evaluator recursion to four levels and 32 payloads per command scalar."
  - "Reject dynamic and concatenated evaluator payloads instead of attempting shell expansion."
patterns-established:
  - "Nested evaluator routes retain outer source provenance, evaluator trail, and raw/decoded payload data."
requirements-completed: [COMP-01, COMP-02, COMP-03]
coverage:
  - id: D1
    description: Literal bash/sh/zsh/eval Composer payloads are recursively classified through the shared route contract.
    requirement: COMP-03
    verification:
      - kind: integration
        ref: php tests/Composer/ComposerPolicyGuardTest.php --group=route-audit-fail-closed
        status: pass
    human_judgment: false
  - id: D2
    description: Dynamic, unsupported, and bounded evaluator forms emit deterministic unclassified audit failures.
    requirement: COMP-03
    verification:
      - kind: integration
        ref: php tests/Composer/ComposerPolicyGuardTest.php
        status: pass
    human_judgment: false
metrics:
  duration: 25min
  completed: 2026-07-23
status: complete
---

# Phase 01 Plan 08: Nested Shell Evaluator Audit Summary

**Bounded quote-aware recursion closes the nested bash/sh/zsh/eval Composer-route bypass while retaining the shared guard contract.**

## Performance

- **Duration:** 25 min
- **Started:** 2026-07-23T11:28:00Z
- **Completed:** 2026-07-23T11:53:28Z
- **Tasks:** 2/2
- **Files modified:** 2

## Accomplishments

- Added RED/GREEN disposable workflow fixtures for literal direct and guarded bash payloads.
- Recursively classifies literal bash, sh, zsh `-c` and one-word eval payloads with depth, visited-payload, and length bounds.
- Fails closed for dynamic, concatenated, malformed, extra-argument, unsupported-option, and over-limit evaluator forms.

## Task Commits

1. **Task 1: Trace one literal bash -c mutation through the production route audit** - `9776d23` (RED), `bc8e0af` (GREEN)
2. **Task 2: Expand bounded recursion to sh, zsh, eval, dynamic payloads, and fail-closed limits** - `482b90f` (RED), `d6750bb` (GREEN)

## Validation

- `php -l tests/Composer/ComposerPolicyGuardTest.php` — pass
- `php tests/Composer/ComposerPolicyGuardTest.php --group=route-audit-fail-closed` — pass
- `php tests/Composer/ComposerPolicyGuardTest.php` — pass
- `php tests/Composer/ComposerPolicyGuardTest.php --route-audit` — pass; exactly three supported CI/README records
- Manifest-policy, protected-artifact, lockfile, and vendor absence assertions — pass
- `ComposerPolicyLivePackagistTest.php` — intentionally not run; this plan changes no live dependency/runtime input boundary.

## Decisions Made

- Preserve raw and decoded evaluator payloads with source location and evaluator trail for deterministic audit evidence.
- Treat shell expansion and concatenation as outside the bounded grammar rather than emulating a shell.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Prevented a long payload fallback from re-entering the bounded parser.**
- **Found during:** Task 2
- **Issue:** The outer long-line error path called the Composer-text detector, which could throw the same length exception.
- **Fix:** The detector now uses a non-parsing evaluator/Composer lexical fallback after a segmentation bound error.
- **Files modified:** `tests/Composer/ComposerPolicyGuardTest.php`
- **Verification:** The payload-length fixture produces an explicit unclassified record and the focused suite passes.
- **Committed in:** `d6750bb`

**2. [Rule 1 - Bug] Excluded shell shebangs from evaluator classification.**
- **Found during:** Task 1
- **Issue:** `#!/bin/sh` was interpreted as an unsupported `sh` evaluator.
- **Fix:** Shell route classification ignores shebang segments before evaluator recognition.
- **Files modified:** `tests/Composer/ComposerPolicyGuardTest.php`
- **Verification:** Existing supported shell-route fixture expectations pass.
- **Committed in:** `bc8e0af`

**Total deviations:** 2 auto-fixed Rule 1 bugs. No scope expansion beyond the declared audit/test file.

## Known Stubs

None.

## Issues Encountered

None remaining.

## Self-Check: PASSED

- `tests/Composer/ComposerPolicyGuardTest.php` exists.
- RED and GREEN commits `9776d23`, `bc8e0af`, `482b90f`, and `d6750bb` exist.

## Next Phase Readiness

Phase 1's remaining nested-shell audit gap is closed; Phase 2 lockfile and advisory-expiry review remain unchanged.
