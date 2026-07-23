---
phase: 01-constraint-resolution-and-security-control
verified: 2026-07-23T14:14:32Z
status: gaps_found
score: 6/7 must-haves verified
behavior_unverified: 0
overrides_applied: 0
re_verification:
  previous_status: gaps_found
  previous_score: 6/7
  gaps_closed:
    - "Literal direct Composer mutations in exact shell brace groups and empty-argument function bodies now reach the route contract and fail."
    - "Exact php -r literal system/exec launches now reach the route contract."
    - "The dependency-free parser suite and production route audit run before Composer install in every existing CI matrix job."
  gaps_remaining:
    - "Valid PHP CLI option and wrapper forms around php -r, and Composer-bearing variables passed to inline-PHP process-launch calls, still produce zero audit records and zero failures."
  regressions: []
gaps:
  - truth: "All supported Composer-bearing execution surfaces are detected and either use the complete guard contract or fail closed."
    status: failed
    reason: "The inline-PHP boundary recognizes only an exact php -r form and loses valid option/wrapper routes; staged workflow probes for php -n/-d, env -i, sudo -n, and timeout produced no record."
    artifacts:
      - path: "tests/Composer/ComposerPolicyGuardTest.php"
        issue: "inlinePhpProgram() requires php and -r in token positions 0 and 1; parseInvocation() returns null for PHP -r before the generic shell fallback can emit an unclassified record."
    missing:
      - "Normalize the accepted wrapper grammar and PHP CLI options before locating -r/--run, then recursively classify a literal program or emit unclassified-php evidence."
      - "Add disposable staged regression fixtures for php -n -r, php -d key=value -r, env -i php -r, sudo -n php -r, and timeout 30 php -r in both direct and guarded forms."
  - truth: "Dynamic, malformed, and over-bound Composer-bearing inline-PHP forms fail closed with source evidence."
    status: failed
    reason: "A literal program that assigns a Composer command to a variable and invokes system($command) or exec($command) is Composer-bearing, but the individual dynamic launch expression has no literal Composer text, so no record is emitted."
    artifacts:
      - path: "tests/Composer/ComposerPolicyGuardTest.php"
        issue: "classifyInlinePhpRouteSegment() only records a dynamic launch when launch.composer_bearing is true, not when the enclosing php -r program is Composer/evaluator-bearing."
    missing:
      - "When a literal inline program is Composer/evaluator-bearing, emit one unclassified-php record for every dynamic/unclassifiable process launch and as a final no-record safety net."
      - "Add staged system($command), exec($command), and evaluator-variable fixtures that require nonempty records and routeAuditFailures()."
deferred:
  - truth: "The three temporary advisory exceptions are removed or explicitly re-approved at their expiry."
    addressed_in: "Phase 2"
    evidence: "Phase 2 success criteria require a reviewed lockfile and non-bypassed security check; every exception names that lockfile review as its expiry."
---

# Phase 01: Constraint Resolution and Security Control Verification Report

**Phase Goal:** Operators can resolve a secure, accurately declared PHP 8.2–8.4 dependency graph on real PHP 8.4 without bypassing Composer safeguards.
**Verified:** 2026-07-23T14:14:32Z
**Status:** gaps_found
**Re-verification:** Yes — after Plan 01-09.

## Goal Achievement

### Observable Truths

| # | Truth | Status | Evidence |
| --- | --- | --- | --- |
| 1 | A fresh real-PHP-8.4 environment resolves and installs the graph through the final guard without platform emulation or ignore flags. | ✓ VERIFIED | Fresh approved live proof passed on PHP 8.4.23: resolver/install metadata markers 807/807, Laravel v11.55.0, Core v3.0.2, configured audit passed, and both caller homes/caches remained empty. |
| 2 | Published Composer metadata declares PHP 8.2, 8.3, and 8.4 support without platform emulation. | ✓ VERIFIED | Current assertion confirms `require.php: ^8.2`; `config.platform` and legacy audit config are absent; `php bin/composer-policy validate --strict --no-check-publish` passes. |
| 3 | Roave is removed while platform checks and a narrow blocking dependency-security safeguard remain enabled. | ✓ VERIFIED | `composer.json` has no Roave; native policy is `ignore-unreachable: false`, `block: true`, `audit: fail`, with exactly the three documented IDs. |
| 4 | The guard accepts only canonical commands and binds them to the integrity-checked repository Composer 2.10.2 PHAR under `PHP_BINARY`. | ✓ VERIFIED | Full dependency-free suite passes; guard source derives repository paths, checks the exact four-line SHA-256 record/version/policy capability, and delegates `[PHP_BINARY, $composerPath, ...$arguments]`. |
| 5 | All supported Composer-bearing workflow, shell, wrapper, alias, and PHP execution surfaces are detected and fail closed unless guarded. | ✗ FAILED | A disposable staged supported workflow containing php -n/-d, env -i, sudo -n, timeout, and variable-fed PHP process launches returned no route records for any inline-PHP fixture and no route-audit failures. |
| 6 | Delegated Composer stdout/stderr/status behavior remains safe and exact. | ✓ VERIFIED | `php tests/Composer/ComposerPolicyGuardTest.php` passes, including the guard subprocess/process-I/O regressions. |
| 7 | The caller cannot replace the pinned Composer executable or project through PATH, `COMPOSER_BIN`, or working-directory selectors. | ✓ VERIFIED | The full suite passes; the guard rejects selectors/overrides before PHAR verification and supplies the canonical repository cwd to probes and delegation. |

**Score:** 6/7 truths verified (0 present, behavior-unverified)

### Required Artifacts

| Artifact | Expected | Status | Details |
| --- | --- | --- | --- |
| `composer.json` | Honest PHP contract and exact native advisory policy without Roave. | ✓ VERIFIED | Substantive current JSON assertion and guarded strict validation pass. |
| `tools/composer/composer-2.10.2.phar` and `.sha256` | Fixed Composer distribution and strict provenance. | ✓ VERIFIED | Exact four-line provenance record exists and the current SHA-256 matches it. |
| `bin/composer-policy` | Isolated, allowlisted, canonical-root policy guard. | ✓ VERIFIED | 426-line substantive guard uses the repository PHAR and canonical cwd; full dependency-free regressions pass. |
| `tools/composer/ComposerPolicyCommandContract.php` | Shared canonical command contract. | ✓ VERIFIED | Required by the guard and used by route classification; guarded compound fixtures resolve as supported. |
| `tests/Composer/ComposerPolicyGuardTest.php` | Fail-closed route audit including compound and inline-PHP boundaries. | ✗ HOLLOW / BLOCKER | The 2,889-line artifact is substantive, wired into CI, and handles exact Plan 09 forms; its `inlinePhpProgram()` and dynamic-launch path leave demonstrated valid forms as zero records. |
| `.github/workflows/ci.yml` | Pre-install dependency-free policy-route gate. | ✓ VERIFIED | One `phpunit` job has the 8.2/8.3 matrix; the parser suite and production audit run after checkout at lines 42–45, before guarded install at line 47 for every matrix entry. |

### Key Link Verification

| From | To | Via | Status | Details |
| --- | --- | --- | --- | --- |
| Workflow brace group/function body | `classifyShellRouteSegment()` | Bounded compound extraction then nested evaluator/contract classification. | ✓ WIRED | Staged direct brace/function commands produce unsupported records; guarded equivalents produce supported guard records. |
| Workflow `php -r` code | `phpProcessLaunches()` and `classifyShellRouteSegment()` | Inline program extraction and recursive shell/guard classification. | ✗ PARTIAL / BLOCKER | The exact `php -r` path works, but accepted PHP options/wrappers and dynamic variable launches never reach this link. |
| `.github/workflows/ci.yml` | `tests/Composer/ComposerPolicyGuardTest.php` | Full dependency-free suite and explicit production audit before install. | ✓ WIRED | Both commands are sequential in the only matrix job and precede the guarded install step. |

### Data-Flow Trace (Level 4)

| Artifact | Data / Input | Source | Produces Real Security Evidence | Status |
| --- | --- | --- | --- | --- |
| `composer.json` | PHP/dependency/advisory policy | Manifest → guard exact-policy assertion → Composer | Yes | ✓ FLOWING |
| `bin/composer-policy` | Caller argv/environment | Override rejection → pinned PHAR → checked delegation | Yes | ✓ FLOWING |
| Route audit | Tracked workflow/shell/PHP command text | `git ls-files` → scalar/parser → route records | No for valid PHP wrapper/variable edges | ✗ DISCONNECTED EDGE |

### Behavioral Spot-Checks

| Behavior | Command / Probe | Result | Status |
| --- | --- | --- | --- |
| Lint / focused Plan 09 regression group | `php -l … && php tests/Composer/ComposerPolicyGuardTest.php --group=route-audit-fail-closed` | Passed. | ✓ PASS |
| Full dependency-free guard suite | `php tests/Composer/ComposerPolicyGuardTest.php` | Passed. Its fixtures cover only exact `php -r`; they do not test valid PHP option/wrapper or variable-launch forms. | ✓ PASS, misleading coverage |
| Production route inventory | `php tests/Composer/ComposerPolicyGuardTest.php --route-audit` | Passed with exactly three guarded CI/README records. | ✓ PASS, no adversarial edge coverage |
| Exact brace/function forms | Disposable staged workflow with direct and guarded brace/function bodies. | Direct calls yielded unsupported failures; guarded calls yielded supported records. | ✓ PASS |
| Valid inline-PHP CLI/wrapper forms | Disposable staged workflow with `php -n`, `php -d`, `env -i`, `sudo -n`, and `timeout` wrapping literal direct/guarded `php -r`. | No `inline-php-wrapper-audit.yml` record appeared; before compound fixtures, the audit passed with only the three production records. | ✗ FAIL |
| Variable-fed inline-PHP launches | Staged `php -r '$command = "composer install"; system($command);'`, analogous `exec($command)`, and an evaluator variable. | No record or route failure. | ✗ FAIL |
| Current metadata/guard boundary | Exact JSON assertion; `php bin/composer-policy validate --strict --no-check-publish`; digest/no-lockfile/vendor checks. | All passed on PHP 8.4.23. | ✓ PASS |
| Isolated live PHP 8.4 resolver/install/audit | `php tests/Composer/ComposerPolicyLivePackagistTest.php` | Fresh approved run passed: PHP 8.4.23; resolver/install markers 807/807; Laravel v11.55.0; Core v3.0.2; audit passed; both isolation homes/caches empty. | ✓ PASS |

### Probe Execution

Step 7c: SKIPPED — no declared `scripts/*/tests/probe-*.sh` probes. The dependency-free suite, staged workflow fixtures, and live integration test were invoked directly.

### Requirements Coverage

| Requirement | Source Plans | Description | Status | Evidence |
| --- | --- | --- | --- | --- |
| COMP-01 | 01-01, 01-02, 01-05 through 01-08 | Clean PHP 8.4 install without platform ignores or emulation. | ✓ SATISFIED | Fresh approved PHP 8.4 live proof passed using isolated resolver/install checkouts, direct Packagist metadata, configured audit, and empty caller homes/caches. |
| COMP-02 | 01-02, 01-05 through 01-08 | Accurate PHP 8.2–8.4 declaration. | ✓ SATISFIED | `^8.2`, no `config.platform`, and guarded strict validation pass. |
| COMP-03 | 01-01 through 01-09 | Roave conflict is removed without weakening platform checks or the replacement safeguard. | ✗ BLOCKED | Supported workflow execution can still conceal direct Composer behind valid inline-PHP wrappers and variable process launches. |

No Phase 1 requirement is orphaned. Root `composer.lock` and `vendor/` remain absent at the intentional Phase 1/Phase 2 boundary.

### Anti-Patterns Found

| File | Line | Pattern | Severity | Impact |
| --- | ---:| --- | --- | --- |
| `tests/Composer/ComposerPolicyGuardTest.php` | 1568–1589 | Exact-position-only `php -r` recognition. | 🛑 BLOCKER | Valid PHP CLI options and accepted wrappers fall through with zero evidence. |
| `tests/Composer/ComposerPolicyGuardTest.php` | 1631–1638 | Dynamic launch is recorded only when that expression itself contains Composer text. | 🛑 BLOCKER | Literal Composer variables passed to `system`/`exec` disappear. |
| `tests/Composer/ComposerPolicyGuardTest.php` | 2700 | Launch-limit fixture uses `MAX_ROUTE_EVALUATOR_PAYLOADS` instead of `MAX_ROUTE_INLINE_PHP_LAUNCHES`. | ⚠️ WARNING | Both are currently 32; a future independent limit change could weaken the intended test. |

No unreferenced `TBD`, `FIXME`, or `XXX` debt markers were found in Phase 01 implementation files.

### Deferred Items

| # | Item | Addressed In | Evidence |
| --- | --- | --- | --- |
| 1 | Reassess/remove temporary advisory exceptions. | Phase 2 | The reviewed lockfile/security snapshot owns the explicit expiry. |

### Gaps Summary

Plan 09 closes the original three bypasses only at the exact parser shapes asserted by its fixtures. It does not meet the broader COMP-03 contract: all demonstrated valid `php -r` wrappers and the demonstrated Composer-bearing variable process-launch programs return zero guarded/direct/unclassified evidence. The current full suite passing is not proof of this boundary, because it never stages those forms. This is a **BLOCKER**; do not advance Phase 1 as complete.

**Next action:** create a focused gap-closure plan for normalized PHP wrapper/option handling and program-level dynamic launch fail-closed records, then run the staged fixtures, full dependency-free suite, and production route audit.

---

_Verified: 2026-07-23T14:14:32Z_
_Verifier: the agent (gsd-verifier)_
