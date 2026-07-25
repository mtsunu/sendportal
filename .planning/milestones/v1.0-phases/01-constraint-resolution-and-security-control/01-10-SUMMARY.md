---
phase: 01-constraint-resolution-and-security-control
plan: 10
subsystem: dependency-security
tags: [composer, route-audit, provenance, shell, php, docker, github-actions]
requires:
  - phase: 01-09
    provides: bounded compound-shell and inline-PHP route evidence plus the pre-install CI route gate
provides:
  - A source-level marker-bearing no-record invariant for tracked dependency routes
  - Bounded workflow alias, shell wrapper/control, PHP launch, Docker, and unknown-source provenance evidence
affects: [phase-01-verification, phase-02-lockfile, dependency-installation, supply-chain-security]
tech-stack:
  added: []
  patterns: [finite normalized route grammar, source-provenanced unclassified fallback, disposable staged Git fixtures]
key-files:
  created:
    - .planning/phases/01-constraint-resolution-and-security-control/01-10-SUMMARY.md
  modified:
    - tests/Composer/ComposerPolicyGuardTest.php
key-decisions:
  - "Treat every marker-bearing approved provenance candidate that yields no child evidence as one deterministic unclassified record."
  - "Accept only literal workflow aliases, bounded shell/PHP wrappers, and literal Docker command forms; reject expansion and unsupported syntax without execution."
patterns-established:
  - "Source-kind dispatch excludes planning/test evidence, preserves production CI/README evidence, and rejects marker-bearing unknown tracked sources."
  - "Direct and guarded fixtures use disposable staged Git roots so route auditing never executes fixture content."
requirements-completed: [COMP-03]
coverage:
  - id: D1
    description: "Marker-bearing workflow, shell, PHP, Docker, and unknown-source candidates produce supported, unsupported, or source-provenanced unclassified evidence."
    requirement: COMP-03
    verification:
      - kind: integration
        ref: php tests/Composer/ComposerPolicyGuardTest.php --group=route-audit-fail-closed
        status: pass
      - kind: integration
        ref: php tests/Composer/ComposerPolicyGuardTest.php
        status: pass
    human_judgment: false
  - id: D2
    description: "The real CI and README route evidence remains guarded and executes before dependency installation without changing dependency/runtime controls."
    requirement: COMP-03
    verification:
      - kind: integration
        ref: php tests/Composer/ComposerPolicyGuardTest.php --route-audit
        status: pass
      - kind: other
        ref: scoped CI-order, manifest-policy, lock/vendor, and protected-artifact assertions from 01-10-PLAN.md
        status: pass
    human_judgment: false
metrics:
  duration: 16min
  completed: 2026-07-23
status: complete
---

# Phase 01 Plan 10: Route Audit Closure Summary

**Finite source-provenanced route auditing now closes marker-bearing Composer bypasses across workflow aliases, shell controls/wrappers, PHP launches, Docker instructions, and unknown tracked sources.**

## Performance

- **Duration:** 16 min
- **Started:** 2026-07-23T21:39:45+07:00
- **Completed:** 2026-07-23T21:55:28+07:00
- **Tasks:** 2/2
- **Files modified:** 1

## Accomplishments

- Added staged RED/GREEN evidence for anchored workflow `run` aliases, normalized `php -r` wrapper/option forms, and marker-bearing unknown tracked sources.
- Added finite bounded handling for explicit shell wrappers/control bodies and literal Docker `RUN`, `CMD`, and `ENTRYPOINT` forms while preserving the shared Composer command contract.
- Made dynamic/variable-fed inline and tracked PHP process launches fail closed at program scope; corrected the inline launch-limit fixture to use its dedicated bound.
- Preserved exactly the existing three guarded production CI/README records and the pre-install dependency-free CI gate.

## Task Commits

1. **Task 1 RED: provenance audit fixtures** — `2b1fffb`
2. **Task 1 GREEN: marker-bearing source evidence** — `437939c`
3. **Task 2 RED: finite grammar matrix fixtures** — `415d9cb`
4. **Task 2 GREEN: shell, PHP, YAML, and Docker closure** — `35f4859`

## Files Created/Modified

- `tests/Composer/ComposerPolicyGuardTest.php` — Implements the bounded normalized grammar, provenance-aware records, source-level fallback, and staged fixture matrix.

## Decisions Made

- Treat unknown marker-bearing tracked sources as `unclassified-unknown-source`; never send them to a trusted extractor or classify them as supported.
- Keep the grammar deliberately finite: shell expansion, arbitrary YAML/Docker parsing, PHP data-flow, and unmodeled process APIs remain explicit unclassified evidence rather than interpreted behavior.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Made marker detection safe for large PHP sources and non-route source text**

- **Found during:** Task 1
- **Issue:** Marker detection could throw on a large PHP source before provenance dispatch, and broad lexical matching incorrectly treated shebangs, Markdown fences, and project instructions as executable routes.
- **Fix:** Added a bounded lexical fallback only on parser exhaustion, retained source-kind exclusions for non-source protected/configuration material, and excluded shebang/fence syntax from candidate finalization.
- **Files modified:** `tests/Composer/ComposerPolicyGuardTest.php`
- **Verification:** Focused route group, full dependency-free suite, and production audit pass with exactly three existing supported records.
- **Committed in:** `437939c`

---

**Total deviations:** 1 auto-fixed Rule 1 bug.

## Validation Evidence

- `php -l tests/Composer/ComposerPolicyGuardTest.php` — pass
- `php tests/Composer/ComposerPolicyGuardTest.php --group=route-audit-fail-closed` — pass
- `php tests/Composer/ComposerPolicyGuardTest.php` — pass
- `php tests/Composer/ComposerPolicyGuardTest.php --route-audit` — pass with exactly three guarded CI/README records
- Plan manifest-policy, CI-order, root `composer.lock`/`vendor` absence, and protected-artifact diff assertions — pass
- Live Packagist verification was not run: this plan performs no network, dependency, runtime, manifest, guard, PHAR, CI, or README change.

## Known Stubs

None.

## User Setup Required

None.

## Next Phase Readiness

The Phase 01 route-audit completeness blocker is closed without altering the PHP 8.2–8.4 dependency policy or Laravel/SendPortal Core boundary. Phase verification can now re-run against the finite grammar evidence.

## Self-Check: PASSED

- Confirmed the route-audit source and Summary exist.
- Confirmed all four RED/GREEN task commits exist.

---
*Phase: 01-constraint-resolution-and-security-control*
*Completed: 2026-07-23*
