---
phase: 01-constraint-resolution-and-security-control
verified: 2026-07-24T00:00:00Z
status: passed
score: 7/7 must-haves verified
behavior_unverified: 0
overrides_applied: 0
re_verification:
  previous_status: gaps_found
  previous_score: 6/7
  gaps_closed:
    - "Tracked app/*.php and tools/*.php unmodeled dispatch now emits one source-provenanced unclassified-php record and fails the route audit. The PHP no-record fallback is no longer restricted to isSupportedProductionRoute(); it is gated by phpCommandShapedProgram() over token_get_all() with explicit guard/test/planning/vendor/generated exclusions."
    - "The exact regression probe from the previous report — a disposable clone with staged app/IndirectComposer.php (variable-function system) and tools/IndirectComposer.php (popen) — now makes --route-audit exit 1 with both staged paths recorded, alongside the unchanged three guarded CI/README records."
  gaps_remaining: []
  accepted_bounds:
    - "Concat-via-indirection ($r(\"composer\".\" install\")) escapes the source-level fallback uniformly across app/, scripts/, and tools/ because phpCommandShapedProgram() matches a single T_CONSTANT_ENCAPSED_STRING and does not interpret string concatenation. This is NOT the path-based blind spot the previous report blocked on (that is retired) — it is a uniform property of the literal-only helper, consistent with the plan's own accepted threat T-01-12-03 (no data-flow / no PHP evaluation). Truth #5 is scoped accordingly below. Owner-accepted this cycle."
gaps: []
deferred:
  - truth: "The three temporary advisory exceptions are removed or explicitly re-approved at their expiry."
    addressed_in: "Phase 2"
    evidence: "Phase 2's reviewed lockfile/security snapshot owns the explicit advisory-exception expiry."
---

# Phase 01: Constraint Resolution and Security Control Verification Report

**Phase Goal:** Operators can resolve a secure, accurately declared PHP 8.2–8.4 dependency graph on real PHP 8.4 without bypassing Composer safeguards.
**Verified:** 2026-07-24T00:00:00Z
**Status:** passed
**Re-verification:** Yes — after Plan 01-12.

## Goal Achievement

### Observable Truths

| # | Truth | Status | Evidence |
| --- | --- | --- | --- |
| 1 | A fresh real-PHP-8.4 environment resolves and installs the graph through the final guard without platform emulation or ignore flags. | ✓ VERIFIED | The protected resolver/install evidence and all dependency artifacts are unchanged. Current CLI is PHP 8.4.23; the root still has neither `composer.lock` nor `vendor/`. |
| 2 | Published Composer metadata declares PHP 8.2, 8.3, and 8.4 support without platform emulation. | ✓ VERIFIED | `require.php` remains `^8.2`, `config.platform` is absent, and `php bin/composer-policy validate --strict --no-check-publish` passed. |
| 3 | Roave is removed while platform checks and a narrow blocking dependency-security safeguard remain enabled. | ✓ VERIFIED | The manifest has no Roave dependency; native policy remains `ignore-unreachable: false`, `block: true`, `audit: fail`, and exactly the three documented advisory IDs. |
| 4 | The guard accepts only canonical commands and binds them to the integrity-checked repository Composer 2.10.2 PHAR under `PHP_BINARY`. | ✓ VERIFIED | The full dependency-free suite passed; the untouched guard/contract/provenance boundary remains protected. |
| 5 | All Composer-bearing workflow, shell, wrapper, evaluator, Composer-script, and PHP execution surfaces that carry a bounded literal command are detected and fail closed unless guarded. | ✓ VERIFIED (scoped) | Plan 12 closes the `app/`/`tools/` blind spot: staged variable-function, `popen`, and `call_user_func` dispatch under both trees now emit one source-provenanced `unclassified-php` record. Documented accepted bound: a command split across two string literals passed through indirect dispatch (`$r("composer"." install")`) is not identified, uniformly across all paths, per accepted threat T-01-12-03 (the parser performs no data-flow interpretation). Direct-API launches already catch concatenation at the expression level. |
| 6 | Delegated Composer stdout/stderr/status behavior remains safe and exact. | ✓ VERIFIED | `php tests/Composer/ComposerPolicyGuardTest.php` passed, including its process-I/O regressions. |
| 7 | The caller cannot replace the pinned Composer executable or project through PATH, `COMPOSER_BIN`, or working-directory selectors. | ✓ VERIFIED | The full dependency-free suite passed; the unchanged guard rejects selectors/overrides before PHAR delegation and fixes child cwd to the repository root. |

**Score:** 7/7 truths verified (0 present-only, 0 behavior-unverified). Truth #5 carries one owner-accepted documented bound; it is not an open gap.

### Plan 12 Closure Targets

| Target | Status | Evidence |
| --- | --- | --- |
| Tracked `app/*.php` and `tools/*.php` marker-bearing unmodeled dispatch fails closed with source provenance. | ✓ VERIFIED | Independent disposable-clone probe: `app/IndirectComposer.php` (variable-function `system`) and `tools/IndirectComposer.php` (`popen`) both produce one `unclassified-php` record with own path, positive physical line, and raw command-bearing segment; `--route-audit` exits 1. |
| The no-record fallback is driven solely by one token-aware command-shaped helper, not a path allowlist and not a raw whole-source regex. | ✓ VERIFIED | `phpCommandShapedProgram()` over `token_get_all()` is the only PHP program-bearing seam; mutation checks confirm the finalization block contains none of `routeAuditMarker(`, `markerSourceLine(`, `containsComposerExecutableText(`, `preg_match(`, or `isSupportedProductionRoute(`. |
| Comment-only, docblock-only, contract reason strings, and arbitrary prose produce no records. | ✓ VERIFIED | No-record controls staged in call-argument forms (not `return`/assignment) across `app/Console/Kernel.php`, `public/index.php`, and `tools/composer/Notes.php` shapes; a mixed comment-and-code file still catches the literal command and anchors provenance to executable source. |
| Bounded direct extractor is unchanged. | ✓ VERIFIED | `ReflectionFunction` self-inspection asserts `phpProcessLaunches()` keeps its five literal APIs (`proc_open`, `exec`, `system`, `passthru`, `shell_exec`) and returns nothing for indirect dispatch. |
| Six staged `app/`+`tools/` command-shaped fixtures each fail closed. | ✓ VERIFIED | Table-driven matrix — variable-function, `popen`, `call_user_func` × {app, tools} — each requires one `unclassified-php` record and nonempty `routeAuditFailures()`; mutation A (reintroduce allowlist) and mutation D (add tools/ to trusted) both FAIL as designed. |
| Closure changes only the dependency-free audit source; no protected dependency/runtime artifact changes; no fixture execution. | ✓ VERIFIED | All three Plan 12 code commits (`0ce75e5`, `95cf1cb`, `33d5693`) touch only `tests/Composer/ComposerPolicyGuardTest.php`; no root lockfile/vendor exists. |

### Required Artifacts

| Artifact | Expected | Status | Details |
| --- | --- | --- | --- |
| `composer.json` | Honest PHP contract and exact native advisory policy without Roave. | ✓ VERIFIED | Strict guarded validation passes; root `scripts` reached before generic JSON exclusion. |
| `tools/composer/composer-2.10.2.phar` and `.sha256` | Fixed Composer distribution and strict provenance. | ✓ VERIFIED | No protected-artifact change; the guard suite validates the unchanged boundary. |
| `bin/composer-policy` | Isolated, allowlisted, canonical-root policy guard. | ✓ VERIFIED | Strict guarded manifest validation and dependency-free suite pass. |
| `tools/composer/ComposerPolicyCommandContract.php` | Shared canonical command contract. | ✓ VERIFIED | Required by guard and used by literal route classification; guarded forms still route through `ComposerPolicyCommandContract::decide()`. |
| `tests/Composer/ComposerPolicyGuardTest.php` | Finite source audit with no silent marker-bearing execution route in tracked production PHP. | ✓ VERIFIED | The `app/`/`tools/` PHP fallback is present and exercised; the previous HOLLOW/BLOCKER finding is retired. |
| `.github/workflows/ci.yml` | Pre-install dependency-free policy-route gate. | ✓ VERIFIED | Runs the full suite and production `--route-audit` before `php bin/composer-policy install`. |

### Key Link Verification

| From | To | Via | Status | Details |
| --- | --- | --- | --- | --- |
| `scripts/*.php` callable, variable-function, and `popen` programs | PHP audit finalization | Marker-gated `unclassified-php` fallback. | ✓ WIRED | Plan 11 fixtures pass; unchanged. |
| `app/*.php` and `tools/*.php` callable, variable-function, and `popen` programs | PHP audit finalization | Same token-aware command-shaped no-record fallback. | ✓ WIRED | Independent staged probe records both prefixes and exits 1; the previous NOT_WIRED/BLOCKER is retired. |
| `.github/workflows/ci.yml` | `tests/Composer/ComposerPolicyGuardTest.php` | Full suite and `--route-audit` before guarded install. | ✓ WIRED | CI source ordering and production route output confirm the dependency-free gate precedes installation. |

### Data-Flow Trace (Level 4)

| Artifact | Data Variable / Candidate | Source | Produces Real Security Evidence | Status |
| --- | --- | --- | --- | --- |
| Ordinary application/tool PHP audit | marker-bearing PHP program | `app/*.php` / `tools/*.php` → `phpCommandShapedProgram()` → no-record fallback → records → failures | Yes; independent clone probe produces source-provenanced unclassified evidence and a nonempty failure list. | ✓ FLOWING |
| Supported script PHP audit | marker-bearing PHP program | Tracked `scripts/*.php` → lexical direct launches or fallback → records → failures | Yes; Plan 11 fixture loop unchanged. | ✓ FLOWING |
| Root Composer script audit | `scripts` event handlers | Root `composer.json` → bounded extractor → records → failures | Yes; real Laravel hooks remain non-candidates. | ✓ FLOWING |
| Guard / manifest | argv and policy manifest | Caller → rejected overrides → pinned PHAR / policy probe | Yes | ✓ FLOWING |

### Behavioral Spot-Checks

| Behavior | Command / Probe | Result | Status |
| --- | --- | --- | --- |
| Syntax and complete grammar regression matrix | `php -l … && php tests/Composer/ComposerPolicyGuardTest.php` | Passed (full suite). | ✓ PASS |
| Focused route-audit closure regression group | `php tests/Composer/ComposerPolicyGuardTest.php --group=route-audit-fail-closed` | Passed. | ✓ PASS |
| Production route audit | `php tests/Composer/ComposerPolicyGuardTest.php --route-audit` | Passed with exactly 3 guarded CI/README records. | ✓ PASS |
| Independent `app/` + `tools/` unmodeled dispatch (the previous BLOCKER probe) | Disposable clone staged `app/IndirectComposer.php` (`$runner('composer install')`) and `tools/IndirectComposer.php` (`popen('composer update','r')`), then `--route-audit` | Exit 1; both staged paths recorded as `unclassified-php` alongside the 3 guarded records. | ✓ PASS |
| Adversarial edge-shape sweep | Disposable clone, variable-function/`popen` dispatch of `composer.phar install`, double-space, uppercase, across app/scripts/tools | 4/5 forms caught uniformly; concat-via-indirection escapes uniformly (documented bound). | ✓ PASS (bound recorded) |
| Guarded manifest boundary | `php bin/composer-policy validate --strict --no-check-publish` | Passed on PHP 8.4.23. | ✓ PASS |

### Requirements Coverage

| Requirement | Source Plans | Description | Status | Evidence |
| --- | --- | --- | --- | --- |
| COMP-01 | 01-01, 01-02, 01-05 through 01-12 | Clean PHP 8.4 install without platform ignores or emulation. | ✓ SATISFIED | Real-PHP-8.4 resolver/install proof and protected inputs unchanged; current runtime PHP 8.4.23. |
| COMP-02 | 01-02, 01-05 through 01-12 | Accurate PHP 8.2–8.4 declaration. | ✓ SATISFIED | `^8.2`, no platform emulation, strict guarded validation pass. |
| COMP-03 | 01-01 through 01-12 | Roave replacement retains platform and dependency-security controls. | ✓ SATISFIED | The `app/`/`tools/` route-audit blind spot is closed; a direct Composer mutation represented by bounded-literal indirect dispatch in tracked production PHP now fails the pre-install gate. One accepted literal-concatenation bound is documented, consistent with T-01-12-03. |

No Phase 1 requirement is orphaned. Root `composer.lock` and `vendor/` remain absent at the intentional Phase 1/Phase 2 boundary.

### Anti-Patterns Found

| File | Line | Pattern | Severity | Impact |
| --- | ---:| --- | --- | --- |
| `tests/Composer/ComposerPolicyGuardTest.php` | `phpProcessLaunches()` | Direct extractor intentionally recognizes only five APIs. | ℹ️ INFO | Acceptable because the scope-independent token-aware fallback now supplies evidence for tracked production PHP; the previous path restriction that broke this safety net is removed. |
| `tests/Composer/ComposerPolicyGuardTest.php` | `phpCommandShapedProgram()` | Literal-only match: a command split across concatenated string literals in indirect dispatch is not identified. | ℹ️ INFO | Documented accepted bound (T-01-12-03), uniform across all path prefixes; not a path-based asymmetry. |

### Deferred Items

| # | Item | Addressed In | Evidence |
| --- | --- | --- | --- |
| 1 | Reassess/remove temporary advisory exceptions. | Phase 2 | The reviewed lockfile/security snapshot owns the explicit expiry. |

### Gaps Summary

Plan 12 closes the blocking gap from the prior report. The exact probe that previously exited 0 with only three guarded records now exits 1, recording both `app/` and `tools/` indirect dispatch with source provenance, verified against a disposable clone rather than the artifact's own fixtures. All seven observable truths are satisfied. Truth #5 is scoped to its honest bound: a command split across two string literals and passed through indirect dispatch is not identified, uniformly across `app/`, `scripts/`, and `tools/` — this is a property of the deliberately literal-only, non-data-flow parser accepted at T-01-12-03, not a path-specific fail-open, and the direct-API path already covers expression-level concatenation. This bound was surfaced explicitly and accepted by the owner this cycle.

**Escalation Gate:** Cleared. Phase 01 may be marked complete and advance to Phase 02.

---

_Verified: 2026-07-24T00:00:00Z_
_Verifier: the agent (inline adversarial re-verification after the background gsd-verifier terminated on an infrastructure login error)_
