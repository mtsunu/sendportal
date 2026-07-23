---
phase: 01-constraint-resolution-and-security-control
plan: 09
subsystem: dependency-security
tags: [composer, route-audit, shell, php-r, github-actions]
requires:
  - phase: 01-08
    provides: bounded shell evaluator recursion and fail-closed route-audit evidence
provides:
  - Recursive audit classification for exact brace groups and shell-function bodies
  - Literal inline php -r process-launch classification with explicit bounded failure evidence
  - A mandatory pre-install CI gate for the dependency-free route-audit suite
affects: [phase-01-verification, phase-02-lockfile, dependency-installation]
tech-stack:
  added: []
  patterns: [bounded compound-shell extraction, literal inline-PHP launch inspection, fail-closed route evidence]
key-files:
  created:
    - .planning/phases/01-constraint-resolution-and-security-control/01-09-SUMMARY.md
  modified:
    - tests/Composer/ComposerPolicyGuardTest.php
    - .github/workflows/ci.yml
key-decisions:
  - "Recognize only exact literal brace groups and named empty-argument shell functions; all other Composer-bearing compound forms yield unclassified evidence."
  - "Treat exact php -r literal programs as a bounded process-launch boundary and route their literal commands through the established shell/Composer contract."
  - "Run the standalone dependency-free policy suite and production route audit before guarded CI dependency installation."
patterns-established:
  - "Nested compound and inline-PHP records retain outer workflow provenance and a deterministic recursion trail."
  - "Parser limits become a single unclassified audit failure rather than an empty record set or runtime execution."
requirements-completed: [COMP-03]
coverage:
  - id: D1
    description: Literal brace groups and exact shell-function bodies classify guarded Composer commands and reject direct Composer mutations.
    requirement: COMP-03
    verification:
      - kind: integration
        ref: php tests/Composer/ComposerPolicyGuardTest.php --group=route-audit-fail-closed
        status: pass
    human_judgment: false
  - id: D2
    description: Literal php -r process launches recurse through the shared shell and Composer contract, while dynamic or bounded forms fail closed.
    requirement: COMP-03
    verification:
      - kind: integration
        ref: php tests/Composer/ComposerPolicyGuardTest.php
        status: pass
    human_judgment: false
  - id: D3
    description: Every unchanged CI matrix entry executes the policy suite and production audit before installing dependencies.
    requirement: COMP-03
    verification:
      - kind: other
        ref: scoped CI workflow ordering assertion from 01-09-PLAN.md
        status: pass
    human_judgment: false
duration: 30min
completed: 2026-07-23
status: complete
---

# Phase 01 Plan 09: Compound Shell and Inline PHP Audit Summary

**The Composer route audit now follows literal brace/function shell bodies and inline `php -r` process launches through the existing guarded-command contract, while unsupported forms fail with source evidence before CI installation.**

## Performance

- **Duration:** 30 min
- **Started:** 2026-07-23T13:27:00Z
- **Completed:** 2026-07-23T13:57:03Z
- **Tasks:** 2/2
- **Files modified:** 2

## Accomplishments

- Added RED/GREEN staged workflow fixtures that prove direct Composer in brace groups and exact function bodies yields nonempty unsupported failures, while guarded forms remain supported.
- Added bounded literal `php -r` inspection for `system`, `exec`, `passthru`, `shell_exec`, and `proc_open` process launches, including nested evaluator recursion and deterministic unclassified failures.
- Made the dependency-free full route-policy suite and production `--route-audit` mandatory CI steps before each existing guarded installation.

## Task Commits

1. **Task 1 RED: compound workflow regression coverage** — `1343489`
2. **Task 1 GREEN: bounded compound route classifier and CI gate** — `af12d55`
3. **Task 2 RED: inline PHP regression coverage** — `ec2ef60`
4. **Task 2 GREEN: bounded inline PHP route classifier** — `aeb6bca`

## Files Created/Modified

- `tests/Composer/ComposerPolicyGuardTest.php` — Adds bounded compound-shell and inline-PHP classification plus disposable staged regression fixtures.
- `.github/workflows/ci.yml` — Runs both dependency-free route-policy commands before guarded dependency installation.

## Validation Evidence

- `php -l tests/Composer/ComposerPolicyGuardTest.php` — passed.
- `php tests/Composer/ComposerPolicyGuardTest.php --group=route-audit-fail-closed` — passed.
- `php tests/Composer/ComposerPolicyGuardTest.php` — passed.
- `php tests/Composer/ComposerPolicyGuardTest.php --route-audit` — passed with three supported production records.
- Scoped CI ordering assertion — passed.
- Exact manifest policy assertion, protected-artifact diff gate, and root lockfile/vendor absence checks — passed.
- Live Packagist proof — intentionally not run; no dependency, guard, manifest, PHAR, or runtime input changed.

## Decisions Made

- Exact brace groups and named `name() { ...; }` bodies are the only compound forms recursively interpreted; unmatched, dynamic, malformed, unsupported, or over-bound forms fail closed.
- Exact `php -r <literal>` programs are inspected without execution and only reuse the existing five literal process-launch forms.
- The CI matrix and install arguments remain unchanged; the new route regression gate runs before installation.

## Deviations from Plan

None - plan executed exactly as written.

## Known Stubs

None.

## Issues Encountered

- Repository metadata writes required the approved Git commit path because the sandbox could not create `.git/index.lock`; no source or planning artifacts were affected.

## User Setup Required

None.

## Next Phase Readiness

COMP-03 route-security coverage now includes the previously verified compound-shell and inline-PHP edges. Phase 2 retains lockfile and advisory-expiry ownership; Phase 3 retains PHP 8.4 CI matrix and runtime validation.

## Self-Check: PASSED

- Confirmed both implementation files and this summary exist.
- Confirmed all four RED/GREEN task commits exist in the repository history.

---
*Phase: 01-constraint-resolution-and-security-control*
*Completed: 2026-07-23*
