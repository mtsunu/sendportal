---
phase: 01-constraint-resolution-and-security-control
verified: 2026-07-23T00:42:55Z
status: gaps_found
score: 2/3 must-haves verified
behavior_unverified: 0
overrides_applied: 0
re_verification:
  previous_status: gaps_found
  previous_score: 2/3
  gaps_closed:
    - "Composer >=2.10.0 and native-policy capability are now checked before documented and CI routes delegate resolution."
  gaps_remaining:
    - "The policy guard accepts an arbitrary PATH-selected program that can impersonate Composer 2.10+ and its policy capability."
    - "The route audit omits valid direct Composer forms and falsely approves a direct command when a later command on the same line uses the guard."
  regressions:
    - "CR-01 trusted-executable boundary"
    - "CR-02 route-audit coverage and classification"
gaps:
  - truth: "Dependency resolution keeps Composer security policy enabled for every supported operator and CI route."
    status: failed
    reason: "bin/composer-policy resolves the first executable named composer from caller-controlled PATH and trusts self-reported version/help output; that program can replace the native policy engine before resolution."
    artifacts:
      - path: "bin/composer-policy"
        issue: "resolveComposer() returns a PATH-selected executable without provenance validation."
      - path: "tests/Composer/ComposerPolicyGuardTest.php"
        issue: "The passing regression deliberately accepts a fake PATH composer that prints 2.10.2 and returns success for policy --help."
    missing:
      - "Establish a trusted Composer binary boundary and reject PATH shadow programs before executing them."
      - "Add a negative regression proving a compliant-looking PATH shadow program is rejected without execution."
  - truth: "All supported Composer mutation routes are detected and required to use the policy guard."
    status: failed
    reason: "The audit regex misses global-option, PHAR, and absolute Composer invocation forms, and classification checks the whole logical line instead of the matched command chain."
    artifacts:
      - path: "tests/Composer/ComposerPolicyGuardTest.php"
        issue: "The matcher and whole-line classifier can pass an unguarded command."
    missing:
      - "Parse each invocation and its command chain, covering prefix/global options, composer.phar, and absolute paths."
      - "Add negative fixtures for global-option, PHAR/absolute-path, and mixed direct-plus-guarded commands."
deferred:
  - truth: "The three temporary advisory exceptions are removed or explicitly re-approved at their Phase 2 expiry."
    addressed_in: "Phase 2"
    evidence: "The exception reasons require Phase 2 lockfile review, and Phase 2 lockfile/security-audit criteria are the reassessment point."
---

# Phase 1: Constraint Resolution and Security Control Verification Report

**Phase Goal:** Operators can resolve a secure, accurately declared PHP 8.2–8.4 dependency graph on real PHP 8.4 without bypassing Composer safeguards.
**Verified:** 2026-07-23T00:42:55Z
**Status:** gaps_found
**Re-verification:** Yes — the former Composer-version-floor gap is closed, but two critical final-review flaws remain.

## Goal Achievement

### Observable Truths

| # | Truth | Status | Evidence |
| --- | --- | --- | --- |
| 1 | PHP 8.4 operators can resolve dependencies without platform emulation or ignore flags. | ✓ VERIFIED | A fresh-COMPOSER_HOME disposable-copy guarded install passed under PHP 8.4.23 / Composer 2.10.2. It selected Laravel v11.55.0 and SendPortal Core v3.0.2; repository composer.lock and vendor remained absent. |
| 2 | Composer metadata declares PHP 8.2, 8.3, and 8.4 support. | ✓ VERIFIED | composer.json requires php: ^8.2; guarded strict validation passed; no config.platform exists. |
| 3 | Roave is removed while platform checks and dependency-security safeguards remain enabled. | ✗ FAILED | Manifest policy and immediate CI/README wiring are correct, but the guard executable is untrusted and the audit that claims all mutation routes use it is incomplete and false-positive. |

**Score:** 2/3 truths verified (0 present, behavior-unverified)

### Required Artifacts

| Artifact | Expected | Status | Details |
| --- | --- | --- | --- |
| composer.json | Accurate PHP contract and native advisory policy without Roave. | ✓ VERIFIED | ^8.2; Laravel ^11.0; Core ^3.0; block true; audit fail; ignore-unreachable false; exactly three documented IDs; no platform, legacy-audit, or broad-ignore setting. |
| bin/composer-policy | Pre-resolution Composer 2.10/native-policy/bypass guard. | ✗ FAILED | Substantive and invoked, but lines 40–62 select a caller-controlled PATH program. PHP_BINARY controls its interpreter, not its provenance. |
| tests/Composer/ComposerPolicyGuardTest.php | Regression and complete route-audit proof. | ✗ FAILED | The test passes, yet lines 299–326 deliberately accept a fake PATH Composer; lines 202 and 214 match/classify too broadly. |
| CI workflow and README | CI and documented operator routes use the guard. | ⚠️ PARTIAL | CI line 43 and README lines 38–39 call the guard, but its target executable is not trusted. |

### Key Link Verification

| From | To | Via | Status | Details |
| --- | --- | --- | --- | --- |
| composer.json policy | Composer 2.10 native policy | policy block plus guarded PHP 8.4 install | ✓ WIRED | Guarded validation and disposable PHP 8.4 install succeeded with the committed policy and no bypass flags. |
| README/CI routes | bin/composer-policy | PHP wrapper command | ✓ WIRED | Direct calls exist at README 38–39 and CI 43. |
| bin/composer-policy | trusted Composer 2.10 policy engine | preflight then delegation | ✗ NOT_WIRED | First readable/executable composer on PATH is invoked; version/help output is forgeable. |
| route audit | every supported mutation command | matcher and per-command classification | ✗ NOT_WIRED | Global-option, PHAR, and absolute forms do not match. A later guarded command makes a direct command appear supported. |

### Data-Flow Trace

| Artifact | Data Source | Status |
| --- | --- | --- |
| composer.json | Composer 2.10.2 through guard | ✓ FLOWING — disposable PHP 8.4 install resolved tagged Laravel/Core packages. |
| bin/composer-policy | Caller PATH | ✗ HOLLOW TRUST BOUNDARY — fake program can supply expected probes then receive delegation. |
| route audit | tracked-file logical-line regex | ✗ INCOMPLETE — valid forms omitted and chains classified as a whole. |

### Behavioral Spot-Checks

| Behavior | Command | Result | Status |
| --- | --- | --- | --- |
| Guarded PHP 8.4 isolated install | Fresh-home guarded install | Exit 0; Laravel v11.55.0 and Core v3.0.2; repository untouched. | ✓ PASS |
| Manifest validity | Guarded strict Composer validation | composer.json valid. | ✓ PASS |
| Guard regression | PHP guard-test script | Exit 0, but its accepted fake PATH Composer is CR-01 evidence. | ✗ FAILS TO PROVE CLAIM |
| Route audit | PHP guard-test route-audit mode | Exit 0 with 93 records, but direct probes demonstrated omissions and false-positive classification. | ✗ FAILS TO PROVE CLAIM |

### Probe Execution

Step 7c: SKIPPED — no committed scripts probe exists for this phase.

### Requirements Coverage

| Requirement | Source Plan | Status | Evidence |
| --- | --- | --- | --- |
| COMP-01 | 01-01, 01-02, 01-04 | ✓ SATISFIED | Independent disposable full install under PHP 8.4.23 succeeded through the guarded command with no platform emulation/ignore flags or repository artifacts. |
| COMP-02 | 01-02 | ✓ SATISFIED | require.php is ^8.2 and strict validation passes. |
| COMP-03 | 01-01 through 01-04 | ✗ BLOCKED | The native policy is narrowly configured, but enforcement can be replaced through PATH and the claimed complete route audit cannot detect/certify all bypasses. |

No Phase 1 requirement is orphaned. composer.lock is intentionally absent; reproducible lockfile work belongs to Phase 2.

### Anti-Patterns Found

| File | Line | Pattern | Severity | Impact |
| --- | --- | --- | --- | --- |
| bin/composer-policy | 40–62 | Caller-controlled PATH trusted as Composer provenance. | 🛑 BLOCKER | A program that imitates version/policy help can become the policy engine and receive the dependency operation. |
| tests/Composer/ComposerPolicyGuardTest.php | 147–165, 201–215 | Whole-line route classification and incomplete invocation matcher. | 🛑 BLOCKER | Test can pass while a supported direct Composer command bypasses the guard. |

No Phase-1 changed source artifact contains an untracked TBD, FIXME, or XXX debt marker.

### Review Finding Disposition

**CR-01 — confirmed blocker.** resolveComposer() returns the first usable composer from PATH, calls it three times, and only parses its output. The included regression requires the fake PATH program to succeed. Rejecting COMPOSER_BIN does not fix this route.

**CR-02 — confirmed blocker.** Direct regex evaluation returned no matches for global-option, PHAR, and absolute forms. For a direct command followed by a guarded command, both operations are found then approved because the whole line contains the guard. The current README/CI commands happen to be guarded, but the promised all-route proof is false.

The time-bounded advisory-exception review is intentionally deferred to Phase 2 and is not a Phase 1 gap.

### Gaps Summary

The resolver, PHP declaration, narrow exception policy, fail-closed unreachable-policy setting, and immediate CI/README routing are real. The security-control outcome is not achieved: the guard does not bind to a trusted Composer implementation, and its audit does not establish that every supported mutation route reaches the guard.

**Next action:** Repair both guard boundaries in Phase 1, add negative regressions, then rerun the disposable PHP 8.4 install and corrected route audit. Do not proceed to Phase 2 on the current policy-control claim.

---

_Verified: 2026-07-23T00:42:55Z_
_Verifier: the agent (gsd-verifier)_
