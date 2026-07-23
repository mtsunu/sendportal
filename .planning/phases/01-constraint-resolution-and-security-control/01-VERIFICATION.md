---
phase: 01-constraint-resolution-and-security-control
verified: 2026-07-23T15:06:18Z
status: gaps_found
score: 6/7 must-haves verified
behavior_unverified: 0
overrides_applied: 0
re_verification:
  previous_status: gaps_found
  previous_score: 6/7
  gaps_closed:
    - "The Plan 09 exact PHP CLI wrapper and variable-fed system/exec paths now have bounded parser handling and passing staged regressions."
    - "Plan 10's known shell, evaluator, workflow, Docker, literal PHP, and unknown-source fixture matrix reaches either the shared command contract or explicit unclassified evidence."
  gaps_remaining:
    - "Composer scripts are categorically excluded as JSON/non-source, so staged post-install-cmd direct Composer and @composer forms produce zero records and zero failures."
    - "Supported scripts/*.php using call_user_func('system', ...), a variable function, or popen() produce zero records and zero failures."
  regressions:
    - "The claimed source-level marker-bearing no-record invariant does not cover composer.json script hooks or unmodeled PHP dispatch."
gaps:
  - truth: "All Composer-bearing execution surfaces are detected and either use the complete guard contract or fail closed."
    status: failed
    reason: "auditRoutes() classifies every .json file as non-source and immediately continues. A disposable staged composer.json with post-install-cmd entries for direct composer install, @composer update, and a guarded form produced records=[] and routeAuditFailures()=[]; Composer scripts are reachable during the documented script-enabled guarded install."
    artifacts:
      - path: "tests/Composer/ComposerPolicyGuardTest.php"
        issue: "routeSourceKind() returns non-source for .json at lines 1767-1768, and auditRoutes() skips non-source files at lines 2257-2258; no Composer-script extractor or rejection path exists."
    missing:
      - "Add a finite composer.json scripts provenance extractor before the JSON/non-source exclusion."
      - "Reject direct Composer and @composer script handlers with source-provenanced failures while preserving the current non-Composer Laravel scripts used by the documented script-enabled guarded install."
      - "Add disposable staged fixtures for post-install-cmd direct Composer and @composer forms that require nonempty records and routeAuditFailures()."
  - truth: "Every supported PHP process-dispatch form containing a Composer, guard, or evaluator marker is classified or fails closed with source evidence."
    status: failed
    reason: "The PHP extractor recognizes only proc_open, exec, system, passthru, and shell_exec. In disposable staged scripts/*.php routes, call_user_func('system', 'composer install'), $launch='system'; $launch('composer install'), and popen('composer install', 'r') each returned records=[] and routeAuditFailures()=[]."
    artifacts:
      - path: "tests/Composer/ComposerPolicyGuardTest.php"
        issue: "phpProcessLaunches() hard-codes five direct APIs at lines 1665-1666; popen is not a marker API, and auditRoutes() only emits a PHP program fallback when phpProcessLaunches() is nonempty at lines 2348-2352."
    missing:
      - "Make any Composer/guard/evaluator-bearing PHP program with no bounded launch record emit one unclassified-php record."
      - "Treat indirect/callable dispatch and unmodeled launch APIs (including call_user_func, variable functions, and popen) as source-provenanced unclassified evidence rather than modeling their data flow."
      - "Add disposable staged regressions for all three forms that require nonempty records and routeAuditFailures()."
deferred:
  - truth: "The three temporary advisory exceptions are removed or explicitly re-approved at their expiry."
    addressed_in: "Phase 2"
    evidence: "Phase 2's reviewed lockfile/security snapshot owns the explicit advisory-exception expiry."
---

# Phase 01: Constraint Resolution and Security Control Verification Report

**Phase Goal:** Operators can resolve a secure, accurately declared PHP 8.2–8.4 dependency graph on real PHP 8.4 without bypassing Composer safeguards.
**Verified:** 2026-07-23T15:06:18Z
**Status:** gaps_found
**Re-verification:** Yes — after Plan 01-10.

## Goal Achievement

### Observable Truths

| # | Truth | Status | Evidence |
| --- | --- | --- | --- |
| 1 | A fresh real-PHP-8.4 environment resolves and installs the graph through the final guard without platform emulation or ignore flags. | ✓ VERIFIED | The protected manifest, guard, PHAR/provenance, CI, README, and live-test source are unchanged; the prior independent PHP 8.4.23 resolver/install/audit proof remains applicable. Current PHP is 8.4.23 and root remains without `composer.lock` or `vendor/`. |
| 2 | Published Composer metadata declares PHP 8.2, 8.3, and 8.4 support without platform emulation. | ✓ VERIFIED | `require.php` is `^8.2`; `config.platform` is absent; the exact policy assertion and `php bin/composer-policy validate --strict --no-check-publish` pass. |
| 3 | Roave is removed while platform checks and a narrow blocking dependency-security safeguard remain enabled. | ✓ VERIFIED | No Roave dependency; native policy has `ignore-unreachable: false`, `block: true`, `audit: fail`, and exactly the three documented advisory IDs. |
| 4 | The guard accepts only canonical commands and binds them to the integrity-checked repository Composer 2.10.2 PHAR under `PHP_BINARY`. | ✓ VERIFIED | The dependency-free suite passes; the guard resolves repository-relative paths, validates the four-line SHA-256 provenance record/version/policy capability, and delegates through `[PHP_BINARY, $composerPath, ...$arguments]`. |
| 5 | All Composer-bearing workflow, shell, wrapper, evaluator, Composer-script, and PHP execution surfaces are detected and fail closed unless guarded. | ✗ FAILED | Plan 10 closes its reproduced grammar classes, but the independent staged Composer-script and PHP-dispatch probes below each returned zero records and zero failures. |
| 6 | Delegated Composer stdout/stderr/status behavior remains safe and exact. | ✓ VERIFIED | `php tests/Composer/ComposerPolicyGuardTest.php` passes, including the existing subprocess/process-I/O regressions. |
| 7 | The caller cannot replace the pinned Composer executable or project through PATH, `COMPOSER_BIN`, or working-directory selectors. | ✓ VERIFIED | The full dependency-free suite passes; `bin/composer-policy` rejects selectors/overrides before PHAR verification and fixes child cwd to the repository root. |

**Score:** 6/7 truths verified (0 present, behavior-unverified)

### Required Artifacts

| Artifact | Expected | Status | Details |
| --- | --- | --- | --- |
| `composer.json` | Honest PHP contract and exact native advisory policy without Roave. | ✓ VERIFIED | Substantive JSON policy assertion and guarded strict validation pass. |
| `tools/composer/composer-2.10.2.phar` and `.sha256` | Fixed Composer distribution and strict provenance. | ✓ VERIFIED | The immutable protected-artifact diff is empty; the guard verifies the exact record and digest. |
| `bin/composer-policy` | Isolated, allowlisted, canonical-root policy guard. | ✓ VERIFIED | Substantive repository-root guard with private Composer home and shared command contract; dependency-free guard tests pass. |
| `tools/composer/ComposerPolicyCommandContract.php` | Shared canonical command contract. | ✓ VERIFIED | Required by the guard and consumed by guarded route classification. |
| `tests/Composer/ComposerPolicyGuardTest.php` | Finite normalized grammar and source-level no-record invariant. | ✗ HOLLOW / BLOCKER | Substantive and wired into CI, but it explicitly skips `.json` sources and fails to identify three supported PHP dispatch forms. |
| `.github/workflows/ci.yml` | Pre-install dependency-free policy-route gate. | ✓ VERIFIED | The full suite and production `--route-audit` run after checkout and before `php bin/composer-policy install` in every matrix entry. |

### Key Link Verification

| From | To | Via | Status | Details |
| --- | --- | --- | --- | --- |
| Workflow, shell, evaluator, Docker, literal PHP, and unknown-source fixtures | `auditRoutes()` / `routeAuditFailures()` | Bounded provenance extraction, normalized classification, source fallback. | ✓ WIRED | Focused and full dependency-free route tests pass; Plan 10's existing fixture matrix exercises these documented classes. |
| `composer.json` script hooks | `auditRoutes()` / `routeAuditFailures()` | Composer-script provenance extraction. | ✗ NOT_WIRED / BLOCKER | `.json` maps to `non-source` then is skipped before candidate finalization; staged direct Composer and `@composer` scripts yield no evidence. |
| `scripts/*.php` indirect or unmodeled dispatch | `phpProcessLaunches()` / PHP fallback | PHP token extraction and program-level no-record fallback. | ✗ PARTIAL / BLOCKER | Five direct APIs are modeled, but `call_user_func`, variable functions, and `popen` do not form a launch record; the fallback is conditional on an existing launch. |
| `.github/workflows/ci.yml` | `tests/Composer/ComposerPolicyGuardTest.php` | Full suite and explicit production audit before install. | ✓ WIRED | CI ordering assertion passes and source order is visible at workflow lines 42–47. |

### Data-Flow Trace (Level 4)

| Artifact | Data Variable / Candidate | Source | Produces Real Security Evidence | Status |
| --- | --- | --- | --- | --- |
| Guard / manifest | argv and policy manifest | Caller → rejected overrides → pinned PHAR / policy probe | Yes | ✓ FLOWING |
| Plan 10 audit grammar | workflow/shell/Docker/literal-PHP candidates | `git ls-files` → provenance extractor → records → failures | Yes for the tested finite grammar | ✓ FLOWING |
| Composer scripts | `composer.json.scripts` entries | `routeSourceKind()` returns `non-source` → `auditRoutes()` continues | No | ✗ DISCONNECTED |
| Indirect PHP launches | callable/variable/unmodeled process expression | `phpProcessLaunches()` returns none → program fallback is not entered | No | ✗ DISCONNECTED |

### Behavioral Spot-Checks

| Behavior | Command / Probe | Result | Status |
| --- | --- | --- | --- |
| Syntax and Plan 10 route regression group | `php -l tests/Composer/ComposerPolicyGuardTest.php && php tests/Composer/ComposerPolicyGuardTest.php --group=route-audit-fail-closed` | Passed. | ✓ PASS |
| Full dependency-free guard suite | `php tests/Composer/ComposerPolicyGuardTest.php` | Passed. | ✓ PASS, insufficient coverage |
| Production route audit | `php tests/Composer/ComposerPolicyGuardTest.php --route-audit` | Passed using only tracked evidence. | ✓ PASS, insufficient coverage |
| Guarded manifest boundary | Exact JSON assertion plus `php bin/composer-policy validate --strict --no-check-publish` | Passed on PHP 8.4.23. | ✓ PASS |
| Protected Phase 01 boundary | Protected-artifact diff; root lockfile/vendor absence checks | Passed; no protected source artifact changed. | ✓ PASS |
| Composer script hooks | Disposable staged Git `composer.json`: `post-install-cmd` direct `composer install`, `@composer update`, and guarded form | `records=[]`, `routeAuditFailures()=[]`. No fixture content was executed. | ✗ FAIL |
| Indirect/unmodeled PHP dispatch | Disposable staged Git `scripts/*.php`: `call_user_func('system', 'composer install')`, variable function, and `popen('composer install', 'r')` | Each case returned `records=[]`, `routeAuditFailures()=[]`. No fixture content was executed. | ✗ FAIL |

### Probe Execution

Step 7c: SKIPPED — no declared `scripts/*/tests/probe-*.sh` probes. The relevant dependency-free suite and disposable staged route probes were run directly.

### Requirements Coverage

| Requirement | Source Plans | Description | Status | Evidence |
| --- | --- | --- | --- | --- |
| COMP-01 | 01-01, 01-02, 01-05 through 01-08 | Clean PHP 8.4 install without platform ignores or emulation. | ✓ SATISFIED | The protected live-proof test and all guarded resolver inputs are unchanged; current PHP is 8.4.23. |
| COMP-02 | 01-02, 01-05 through 01-08 | Accurate PHP 8.2–8.4 declaration. | ✓ SATISFIED | `^8.2`, no platform emulation, and guarded strict validation pass. |
| COMP-03 | 01-01 through 01-10 | Roave conflict is removed without weakening platform checks or the replacement safeguard. | ✗ BLOCKED | Direct Composer can be concealed in script-enabled Composer hooks and supported PHP dispatch forms without any audit evidence. |

No Phase 1 requirement is orphaned. Root `composer.lock` and `vendor/` remain absent at the intentional Phase 1/Phase 2 boundary.

### Anti-Patterns Found

| File | Line | Pattern | Severity | Impact |
| --- | ---:| --- | --- | --- |
| `tests/Composer/ComposerPolicyGuardTest.php` | 1767–1768, 2257–2258 | All `.json` files are classified `non-source` and skipped. | 🛑 BLOCKER | `composer.json` script hooks bypass the claimed source-level no-record invariant. |
| `tests/Composer/ComposerPolicyGuardTest.php` | 1665–1666, 2348–2352 | Five hard-coded direct PHP APIs; fallback requires an existing recognized launch. | 🛑 BLOCKER | Indirect/callable and `popen` Composer dispatch disappear as zero records. |
| `tests/Composer/ComposerPolicyGuardTest.php` | 1089–2157 | `return null` / empty-array matches in bounded parser helpers. | ℹ️ INFO | Normal parser control flow; not a stub by itself. |

No unreferenced `TBD`, `FIXME`, or `XXX` debt markers were found in the Phase 01 implementation artifact.

### Deferred Items

| # | Item | Addressed In | Evidence |
| --- | --- | --- | --- |
| 1 | Reassess/remove temporary advisory exceptions. | Phase 2 | The reviewed lockfile/security snapshot owns the explicit expiry. |

### Gaps Summary

Plan 10 materially improves the route audit and closes the previously reported exact wrapper and variable-fed direct-launch cases. Its broader claim is nevertheless false: `composer.json` is intentionally excluded from provenance before its `scripts` hooks can be inspected, and the PHP path only falls back after it has recognized one of five direct launch APIs. These are observable, reproducible zero-record gaps, not uncertainty. Because the documented successful install runs Composer scripts, a future `post-install-cmd` direct `composer` or `@composer` entry would execute outside the guard audit; likewise, the three supported PHP dispatch forms can conceal direct Composer. No later roadmap phase explicitly schedules either audit-boundary repair, so neither gap is deferred.

**Escalation Gate:** Do not mark Phase 01 complete or advance to Phase 02 until a focused closure plan adds bounded Composer-script provenance and PHP no-record fallbacks, with staged regressions for the four reproduced classes.

---

_Verified: 2026-07-23T15:06:18Z_
_Verifier: the agent (gsd-verifier)_
