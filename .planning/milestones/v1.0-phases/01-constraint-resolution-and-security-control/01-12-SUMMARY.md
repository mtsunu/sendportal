---
phase: 01-constraint-resolution-and-security-control
plan: 12
subsystem: dependency-security
tags: [composer, route-audit, provenance, php, fail-closed, token-aware]
requires:
  - phase: 01-11
    provides: finite Composer-script provenance and marker-gated supported-PHP no-record fallback
provides:
  - Scope-independent tracked-PHP no-record invariant covering app/ and tools/
  - Token-aware command-shaped PHP program-bearing helper as the sole PHP decision seam
  - Staged app/tools indirect-dispatch regression matrix with token-aware no-record controls
affects: [phase-01-verification, phase-02-lockfile, dependency-installation, supply-chain-security]
tech-stack:
  added: []
  patterns: [token-aware command-shaped PHP marker, tracked-production-PHP no-record invariant, disposable staged Git fixtures, source self-inspection assertions]
key-files:
  created:
    - .planning/phases/01-constraint-resolution-and-security-control/01-12-SUMMARY.md
  modified:
    - tests/Composer/ComposerPolicyGuardTest.php
key-decisions:
  - "Derive PHP program-bearing status solely from one token_get_all()-based command-shaped helper instead of a raw whole-source Composer regex."
  - "Replace the supported-production-route allowlist on PHP finalization with a tracked-production-PHP boundary plus explicit trusted/generated exclusions."
  - "Prove the boundary with source self-inspection assertions so a later regression that reintroduces a raw seam or an allowlist fails the suite."
patterns-established:
  - "Comments, docblocks, returned and thrown ComposerPolicyCommandContract reason strings, and prose passed to calls are no-record controls; only executable command-shaped literals trigger the fallback."
  - "Indirect dispatch stays outside phpProcessLaunches(); it is represented as source evidence rather than evaluated."
requirements-completed: [COMP-03]
coverage:
  - id: D1
    description: "Variable-function, popen, and call_user_func Composer dispatch staged under both app/ and tools/ each emit exactly one source-provenanced unclassified-php record and a nonempty route-audit failure without executing fixture PHP."
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
    description: "Comment-only, docblock-only, returned/thrown contract reason strings, and prose passed to calls produce no records, while literal command-shaped code strings in the same file remain detectable."
    requirement: COMP-03
    verification:
      - kind: integration
        ref: php tests/Composer/ComposerPolicyGuardTest.php --group=route-audit-fail-closed
        status: pass
    human_judgment: false
  - id: D3
    description: "The parser stays bounded: phpProcessLaunches() keeps its five literal APIs, phpCommandShapedProgram() is the sole PHP program-bearing seam, PHP finalization carries no raw whole-source Composer regex or production route allowlist, and exclusions for tests/planning/vendor/generated plus manifest/lock/PHAR non-source handling remain explicit."
    requirement: COMP-03
    verification:
      - kind: integration
        ref: php tests/Composer/ComposerPolicyGuardTest.php --group=route-audit-fail-closed
        status: pass
    human_judgment: false
  - id: D4
    description: "Production route audit retains exactly three guarded CI/README records with no root composer.lock and no vendor tree."
    requirement: COMP-03
    verification:
      - kind: integration
        ref: php tests/Composer/ComposerPolicyGuardTest.php --route-audit
        status: pass
      - kind: command
        ref: test ! -e composer.lock && test ! -d vendor
        status: pass
    human_judgment: false
metrics:
  duration: 2h 34m
  completed: 2026-07-23
status: complete
---

# Phase 01 Plan 12: PHP Provenance Audit Closure Summary

**Tracked application and tool PHP can no longer represent a Composer-bearing indirect dispatch while yielding zero route-audit evidence.**

## Performance

- **Duration:** 2 h 34 m wall clock across a paused human-verify checkpoint
- **Started:** 2026-07-23T22:52+07:00
- **Completed:** 2026-07-23T23:26+07:00 (Task 2 resumed after tracer confirmation)
- **Tasks:** 2/2
- **Files modified:** 1

## Accomplishments

- Replaced the route-allowlist condition on PHP no-record finalization with a tracked-production-PHP boundary driven solely by `phpCommandShapedProgram()`, a `token_get_all()`-based command-shaped helper (Task 1).
- Staged the full six-case matrix — variable-function, `popen`, and `call_user_func` dispatch under both `app/` and `tools/` — each requiring exactly one `unclassified-php` record for its own path with unclassified classification, a finite positive source line, invocation-bearing raw segment and logical provenance, and a nonempty `routeAuditFailures()` result, with no fixture execution and no fixture-created lockfile or vendor tree.
- Added token-aware no-record controls across `app/Console/Kernel.php`, `public/index.php`, and `tools/composer/Notes.php` shapes: line comment, docblock, returned contract reason strings, thrown contract reason strings, and Composer-mentioning prose passed to `sprintf`/`trigger_error` calls. A mixed comment-plus-code case proves a literal command-shaped string is still detected and that provenance points at executable source rather than the comment.
- Added a direct-literal `app/` launch case confirming `app/` is not a route allowlist, and a guard-plus-contract-only fixture confirming the tracked guard and trusted command contract produce no records on their own.
- Added source self-inspection regressions via `ReflectionFunction`: `phpProcessLaunches()` keeps its literal five-API list and returns nothing for indirect dispatch; `phpCommandShapedProgram()` appears exactly once in PHP finalization and decides from `token_get_all()`; PHP finalization contains none of `routeAuditMarker(`, `markerSourceLine(`, `containsComposerExecutableText(`, `containsComposerOrEvaluatorText(`, `preg_match(`, or `isSupportedProductionRoute(`; `parseInvocation()` still classifies guarded forms through `ComposerPolicyCommandContract::decide()`.
- Pinned the exclusion contract: `tests/`, `.planning/`, `vendor/`, `bootstrap/cache/`, `storage/framework/`, and `bin/composer-policy` stay trusted, while `app/`, `tools/`, and `public/index.php` explicitly do not; manifest, lockfile, and PHAR/digest paths keep `non-source` handling.
- Pinned production output to exactly three records, all `supported`/`guard` on `README.md` or `.github/workflows/`, with root `composer.lock` and `vendor/` absent.

## Task Commits

1. **Task 1 RED: failing app PHP provenance test** — `0ce75e5`
2. **Task 1 GREEN: fail-closed tracked PHP command dispatch** — `95cf1cb`
3. **Task 2: expanded tracked-PHP provenance matrix** — `33d5693`

`f9c43be` is the intervening `wip: phase-01 paused at 01-12 task-2` checkpoint commit written by `$gsd-pause-work`; both Task 1 commits were preserved unchanged across the pause.

## Files Created/Modified

- `tests/Composer/ComposerPolicyGuardTest.php` — Adds `auditFunctionSource()` / `phpFinalizationSource()` self-inspection helpers, the six-case app/tools dispatch matrix, the token-aware no-record control set, and the bounded-parser, exclusion, and production-record regressions.

## Decisions Made

- The token-aware command-shaped helper is the only PHP program-bearing decision seam; the plan's "no raw whole-source Composer regex" requirement is enforced as an executable assertion over the extracted finalization block, not a review convention.
- Task 1's single `app/IndirectComposer.php` assertion was folded into the six-case matrix rather than duplicated. Its behavior is strictly retained and strengthened (exact record count, bounded source line, logical-plus-segment provenance, no fixture side effect); the RED/GREEN commits `0ce75e5` and `95cf1cb` remain untouched in history.
- `app/` and `tools/` are audited but are not supported production routes, so a direct literal Composer launch there classifies as `unclassified` rather than `supported` — asserted explicitly so the boundary is not mistaken for a new allowlist.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Test quality] Initial no-record controls could not have failed**

- **Found during:** Task 2 mutation checking
- **Issue:** The first `contract reason strings` and `arbitrary prose` controls placed their text in `return` statements and plain assignments. `phpCommandInvocationLine()` rejects those regardless of the marker grammar, so the controls would have passed even if the command-shape gate were broadened to a raw Composer match.
- **Fix:** Reshaped them into call-argument forms — `throw new RuntimeException('a canonical Composer command is required')`, `throw new InvalidArgumentException('Composer command aliases are forbidden')`, `sprintf('Operators must run Composer through bin/composer-policy before deploying.')`, `sprintf('The %s manifest is validated in CI.', 'composer.json')`, and `trigger_error('Direct Composer usage is reviewed by the maintainer.', E_USER_NOTICE)` — so only the command-shape gate can reject them.
- **Files modified:** `tests/Composer/ComposerPolicyGuardTest.php`
- **Verification:** Mutation B below now fails on these controls; it did not before the reshape.
- **Committed in:** `33d5693`

---

**Total deviations:** 1 auto-fixed Rule 1 test-quality issue. No plan scope, dependency, or runtime boundary was changed.

## Validation Evidence

Plan verification command, run end to end:

- `php -l tests/Composer/ComposerPolicyGuardTest.php` — pass
- `php tests/Composer/ComposerPolicyGuardTest.php --group=route-audit-fail-closed` — pass
- `php tests/Composer/ComposerPolicyGuardTest.php` — pass (full suite)
- `php tests/Composer/ComposerPolicyGuardTest.php --route-audit` — pass with exactly 3 classified records (`.github/workflows/ci.yml:47`, `README.md:38`, `README.md:39`), all `guard`/`supported`
- `test ! -e composer.lock && test ! -d vendor` — pass

### Mutation checks (assertions proven non-vacuous)

Each mutation was applied to a working copy, run, then reverted; the suite passed again after every revert.

| Mutation | Injected regression | Result |
|----------|---------------------|--------|
| A | Reintroduce `\|\| ! isSupportedProductionRoute($path)` into the tracked-PHP exclusion | FAIL — `app/IndirectComposer.php variable function dispatch must produce exactly one source-level unclassified PHP fallback` |
| B | Broaden `phpCommandShapedLiteral()` to a raw `~composer~i` match | FAIL — `thrown contract reason strings in app/Console/Kernel.php must stay outside the tracked-PHP detector` |
| C | Add `\|\| routeAuditMarker($contents)` to the program-bearing decision | FAIL — `Tracked-PHP finalization must not reintroduce the seam routeAuditMarker(` |
| D | Add `tools/` to `isTrustedPhpAuditSource()` | FAIL — `tools/IndirectComposer.php variable function dispatch must produce exactly one source-level unclassified PHP fallback` |

## Known Stubs

None.

## User Setup Required

None. The human-verify checkpoint that paused this plan was satisfied by the operator's tracer confirmation before Task 2 resumed.

## Next Phase Readiness

The verified PHP provenance blind spot is closed with source-level, token-aware, mutation-checked coverage. No Composer constraint, policy, guard, PHAR provenance, CI matrix, lockfile boundary, or Laravel 11 / SendPortal Core 3 integration was modified — `tests/Composer/ComposerPolicyGuardTest.php` is the only changed file, consistent with D-01 through D-03. Phase 01 verification can be re-run to confirm the remaining `01-VERIFICATION.md` gap is retired.

## Self-Check: PASSED

- Confirmed `tests/Composer/ComposerPolicyGuardTest.php` and this Summary exist.
- Confirmed Task 1 commits `0ce75e5` and `95cf1cb` are intact and Task 2 commit `33d5693` exists.
- Confirmed the full verification command and all four mutation checks were executed, not inferred.

---
*Phase: 01-constraint-resolution-and-security-control*
*Completed: 2026-07-23*
