---
phase: 01-constraint-resolution-and-security-control
verified: 2026-07-23T12:05:09Z
status: gaps_found
score: 6/7 must-haves verified
behavior_unverified: 0
overrides_applied: 0
re_verification:
  previous_status: gaps_found
  previous_score: 6/7
  gaps_closed:
    - "Bare literal bash/sh/zsh -c and eval payloads are recursively classified by the new bounded evaluator path."
  gaps_remaining:
    - "All supported Composer-bearing execution surfaces fail closed: brace groups, shell function bodies, and workflow php -r programs can still hide direct Composer mutations with zero audit records and zero failures."
  regressions: []
gaps:
  - truth: "All supported Composer-bearing execution surfaces are detected and either use the complete guard contract or fail closed."
    status: failed
    reason: "The route audit silently drops direct Composer mutations nested in shell brace groups and function bodies. Independent staged workflow fixtures produced records=0 and failures=0 for both forms."
    artifacts:
      - path: "tests/Composer/ComposerPolicyGuardTest.php"
        issue: "commandChainSegments() splits on parentheses and semicolons but has no brace/function-body grammar; parseInvocation() then sees no evaluator at executable position and classifyShellRouteSegment() returns an empty record list."
    missing:
      - "Recursively classify or emit one explicit unclassified failure for Composer-bearing brace groups and shell function bodies."
      - "Add staged Git regression fixtures that require a nonempty failure for each form."
  - truth: "All supported Composer-bearing execution surfaces are detected and either use the complete guard contract or fail closed."
    status: failed
    reason: "A staged workflow scalar containing php -r 'system(\"composer install\");' produced records=0 and failures=0."
    artifacts:
      - path: "tests/Composer/ComposerPolicyGuardTest.php"
        issue: "parseInvocation() returns null immediately for PHP -r, while the shell fallback only detects Composer executable text; it does not inspect the inline PHP program."
    missing:
      - "Treat PHP -r as an executable-code boundary: bounded-parse literal process-launch calls or emit an explicit unclassified failure whenever its literal program is Composer-bearing."
      - "Add direct and guarded php -r workflow fixtures and require nonempty audit evidence."
deferred:
  - truth: "The three temporary advisory exceptions are removed or explicitly re-approved at their expiry."
    addressed_in: "Phase 2"
    evidence: "Phase 2 success criteria require a reviewed lockfile and security check; each current exception explicitly expires at the Phase 2 lockfile review or a compatible stable Core/Laravel upgrade."
---

# Phase 1: Constraint Resolution and Security Control Verification Report

**Phase Goal:** Operators can resolve a secure, accurately declared PHP 8.2–8.4 dependency graph on real PHP 8.4 without bypassing Composer safeguards.
**Verified:** 2026-07-23T12:05:09Z
**Status:** gaps_found
**Re-verification:** Yes — after Plan 01-08.

## Goal Achievement

### Observable Truths

| # | Truth | Status | Evidence |
| --- | --- | --- | --- |
| 1 | A fresh real-PHP-8.4 environment resolves and installs the graph through the final guard without platform emulation or ignore flags. | ✓ VERIFIED | Re-verification quick regression: current PHP is 8.4.23, the pinned Composer PHAR is 2.10.2 with matching digest, exact manifest validation passes through `bin/composer-policy`, and Plan 08 changed only the audit test. The prior verifier independently ran the unchanged two-checkout live gate successfully. |
| 2 | Published Composer metadata declares PHP 8.2, 8.3, and 8.4 support without platform emulation. | ✓ VERIFIED | Current JSON assertion passed: `require.php` is `^8.2`, `config.platform` and legacy audit config are absent, and `php bin/composer-policy validate --strict --no-check-publish` passed. |
| 3 | Roave is removed while platform checks and a narrow blocking dependency-security safeguard remain enabled. | ✓ VERIFIED | `composer.json` has no Roave, sets `ignore-unreachable: false`, `block: true`, `audit: fail`, and only the three documented IDs; the guard reasserts these exact values before install/update delegation. |
| 4 | The guard accepts only canonical commands and binds them to the integrity-checked repository Composer 2.10.2 PHAR under `PHP_BINARY`. | ✓ VERIFIED | Current full dependency-free suite passed; source verifies the PHAR digest/version/policy probe and delegates `[PHP_BINARY, $composerPath, ...$arguments]`. |
| 5 | All supported Composer-bearing workflow, shell, wrapper, alias, and PHP execution surfaces are detected and fail closed unless guarded. | ✗ FAILED | Independent staged workflow probes for a brace group, a function body, and inline `php -r` each returned `records=0 failures=0`; a direct dependency mutation can therefore evade the supported-route security control. |
| 6 | Delegated Composer stdout/stderr/status behavior remains safe and exact. | ✓ VERIFIED | The current full dependency-free suite passed; Plan 08 did not modify the guard or process-I/O implementation. |
| 7 | The caller cannot replace the pinned Composer executable or project through PATH, `COMPOSER_BIN`, or working-directory selectors. | ✓ VERIFIED | Guard source uses repository-derived PHAR/provenance paths, rejects `COMPOSER_BIN` and selectors, and the full suite passed. |

**Score:** 6/7 truths verified (0 present, behavior-unverified)

### Required Artifacts

| Artifact | Expected | Status | Details |
| --- | --- | --- | --- |
| `composer.json` | Honest PHP contract and exact native advisory policy without Roave. | ✓ VERIFIED | Substantive and consumed by guard policy reassertion; exact current assertion passed. |
| `tools/composer/composer-2.10.2.phar` and `.sha256` | Fixed Composer distribution and strict provenance. | ✓ VERIFIED | PHAR exists, reports 2.10.2 on PHP 8.4.23, and SHA-256 matches the four-line record. |
| `bin/composer-policy` | Isolated, allowlisted, canonical-root policy guard. | ✓ VERIFIED | 426-line substantive guard is wired from CI/README and passed direct strict validation plus the full regression suite. |
| `tools/composer/ComposerPolicyCommandContract.php` | Shared canonical command contract. | ✓ VERIFIED | Guard and audit both require/use the four-command decision. |
| `tests/Composer/ComposerPolicyGuardTest.php` | Fail-closed route audit including nested evaluators. | ⚠️ PARTIAL / BLOCKER | 2,471-line substantive test/audit artifact is wired and its supplied suite passes, but its parser has observable zero-record routes for brace groups/functions and inline PHP. |
| `.github/workflows/ci.yml`, `README.md` | Supported install/update routes invoke the guard. | ✓ VERIFIED | Current production audit emitted exactly three guarded CI/README records. |

### Key Link Verification

| From | To | Via | Status | Details |
| --- | --- | --- | --- | --- |
| CI and README routes | `bin/composer-policy` | `php bin/composer-policy` install/update commands | ✓ WIRED | Production audit emitted CI install plus README install/update as supported. |
| Guard | checked-in PHAR/provenance | root-relative files, SHA-256, exact-version and policy probes through `PHP_BINARY` | ✓ WIRED | Static inspection and full regression suite pass. |
| Guard install/update | exact manifest policy | reassertion before and after probes | ✓ WIRED | Source has the two reassertions before final delegation. |
| Supported workflow/shell scalar | nested command/evaluator payload | command segmentation, token parsing, bounded recursive evaluator classification | ✗ NOT WIRED / BLOCKER | Only an outer `bash`/`sh`/`zsh`/`eval` invocation is handled. Compound groups and functions hide the evaluator. |
| Supported workflow scalar | inline PHP process launch | `php -r` parsing to a route-audit record | ✗ NOT WIRED / BLOCKER | `parseInvocation()` returns `null` for `-r`; the fallback does not inspect the program. |

### Data-Flow Trace (Level 4)

| Artifact | Data / Input | Source | Produces Real Security Evidence | Status |
| --- | --- | --- | --- | --- |
| `composer.json` | PHP/dependency/advisory policy | committed manifest → guard exact-policy assertion → Composer | Yes | ✓ FLOWING |
| `bin/composer-policy` | caller argv/environment | override rejection → pinned PHAR → checked delegation | Yes | ✓ FLOWING |
| Route audit | tracked workflow/shell/PHP command text | `git ls-files` → scalar/parser → route records | No for compound shell/inline-PHP edges | ✗ DISCONNECTED EDGE |

### Behavioral Spot-Checks

| Behavior | Command / Probe | Result | Status |
| --- | --- | --- | --- |
| Audit test syntax | `php -l tests/Composer/ComposerPolicyGuardTest.php` | No syntax errors. | ✓ PASS |
| Existing evaluator regressions | `php tests/Composer/ComposerPolicyGuardTest.php --group=route-audit-fail-closed` | Passed. | ✓ PASS |
| Full dependency-free guard suite | `php tests/Composer/ComposerPolicyGuardTest.php` | Passed. | ✓ PASS |
| Current production route inventory | `php tests/Composer/ComposerPolicyGuardTest.php --route-audit` | Passed with exactly three guarded CI/README records. | ✓ PASS, incomplete coverage |
| Brace-group direct evaluator | Disposable staged workflow: `{ bash -c 'composer install'; }` | `records=0 failures=0`. | ✗ FAIL |
| Function-body direct evaluator | Disposable staged workflow function containing `bash -c 'composer install'` | `records=0 failures=0`. | ✗ FAIL |
| Inline-PHP direct evaluator | Disposable staged workflow: `php -r 'system("composer install");'` | `records=0 failures=0`. | ✗ FAIL |
| Current metadata/guard boundary | Exact JSON policy assertion; `php bin/composer-policy validate --strict --no-check-publish` | Both passed; no root lockfile/vendor. | ✓ PASS |

### Probe Execution

Step 7c: SKIPPED — no declared `scripts/*/tests/probe-*.sh` probes. The dependency-free audit suite and isolated staged workflow probes were executed directly.

### Requirements Coverage

| Requirement | Source Plans | Description | Status | Evidence |
| --- | --- | --- | --- | --- |
| COMP-01 | 01-01, 01-02, 01-05 through 01-08 | Clean PHP 8.4 install without platform ignores or emulation. | ✓ SATISFIED | Prior independent live evidence remains unchanged; current PHP/PHAR/manifest/guard regression sanity checks pass. |
| COMP-02 | 01-02, 01-05 through 01-08 | Accurate PHP 8.2–8.4 declaration. | ✓ SATISFIED | Current `^8.2` plus no platform emulation and strict guarded validation. |
| COMP-03 | 01-01 through 01-08 | Roave conflict removed without weakening platform checks or the replacement safeguard. | ✗ BLOCKED | A direct Composer mutation in supported compound/inline code routes disappears rather than failing audit. |

No Phase 1 requirement is orphaned. `composer.lock` and root `vendor/` remain absent at the intentional Phase 1/Phase 2 boundary.

### Anti-Patterns Found

| File | Line | Pattern | Severity | Impact |
| --- | ---:| --- | --- | --- |
| `tests/Composer/ComposerPolicyGuardTest.php` | 514 | Segmenter lacks brace/function structure while splitting shell separators. | 🛑 BLOCKER | Direct nested evaluator is not presented to classifier. |
| `tests/Composer/ComposerPolicyGuardTest.php` | 1071 | `php -r` is immediately treated as non-executing. | 🛑 BLOCKER | Inline `system("composer install")` disappears from the route audit. |
| `tests/Composer/ComposerPolicyGuardTest.php` | 1425 | `null`/empty records are valid parser flow-control elsewhere. | ℹ️ Info | Not classified as a stub; the fixtures above prove the specific harmful paths. |

No unreferenced `TBD`, `FIXME`, or `XXX` debt markers were found in the Plan 01-08 implementation file.

### Deferred Items

| # | Item | Addressed In | Evidence |
| --- | --- | --- | --- |
| 1 | Reassess/remove temporary advisory exceptions. | Phase 2 | Phase 2 is the reviewed lockfile/security snapshot phase; the current exception text names that review as its expiry. |

### Gaps Summary

Plan 01-08 closes the previously demonstrated plain `bash`/`sh`/`zsh`/`eval` payload bypasses, but it does not achieve the broader Phase 01 requirement that every supported Composer-bearing execution route is detected or fails closed. The supplied suite is misleading here: it covers only evaluator-at-executable-position and PHP source files, not evaluator calls inside compound shell syntax or a workflow's inline PHP program. Because all three independent direct-mutation probes returned an empty record list and no failure, COMP-03 and the phase goal remain blocked.

**Next action:** add a focused gap-closure plan for compound shell and inline-PHP route audit coverage; do not proceed to Phase 2 as though Phase 1 passed.

---

_Verified: 2026-07-23T12:05:09Z_
_Verifier: the agent (gsd-verifier)_
