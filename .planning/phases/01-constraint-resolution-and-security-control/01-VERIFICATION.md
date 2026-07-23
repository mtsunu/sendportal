---
phase: 01-constraint-resolution-and-security-control
verified: 2026-07-23T07:19:01Z
status: gaps_found
score: 6/7 must-haves verified
behavior_unverified: 0
overrides_applied: 0
re_verification:
  previous_status: gaps_found
  previous_score: 2/3
  gaps_closed:
    - "Caller policy environment variables and machine-global Composer configuration are isolated from guarded children."
    - "The guarded command surface is restricted to canonical validate, audit, install, and update commands, with exact-policy reassertion before resolver delegation."
    - "Delegated Composer stdout/stderr channels, live streaming, bounded preflight capture, exact exit status, and deadlock-safe test capture are implemented and behaviorally exercised."
    - "The previously reproduced workflow scalar, control-structure, alias, wrapper, and PHP process-launch forms are covered."
    - "A fresh explicitly approved PHP 8.4 Packagist resolver/install/audit run passed through the final hardened guard."
  gaps_remaining:
    - "Supported workflow and shell routes can still hide direct Composer mutations inside nested shell-evaluation wrappers such as bash -c, sh -c, and eval."
  regressions: []
gaps:
  - truth: "All supported Composer-bearing execution surfaces are detected and either use the complete guard contract or fail closed."
    status: failed
    reason: "The route audit only recognizes Composer at the outer executable position. In disposable tracked workflow fixtures, bash -c 'composer install', sh -c 'composer update', and eval 'composer install' each produced zero records and zero failures."
    artifacts:
      - path: "tests/Composer/ComposerPolicyGuardTest.php"
        issue: "containsComposerExecutableText() and parseInvocation() do not inspect or conservatively reject nested shell-evaluation payloads, so auditRoutes() silently drops these supported workflow commands."
    missing:
      - "Recognize bounded literal bash/sh/zsh -c and eval payloads and recursively classify their command text, or emit an explicit unclassified failure."
      - "Treat dynamic shell-evaluation payloads in supported routes as unclassified and failing."
      - "Add disposable Git fixtures for nested shell evaluation and require a non-empty failure record."
deferred:
  - truth: "The three temporary advisory exceptions are removed or explicitly re-approved at their expiry."
    addressed_in: "Phase 2"
    evidence: "Each exception explicitly expires at the Phase 2 lockfile review or when a compatible stable SendPortal Core release permits a Laravel upgrade."
---

# Phase 1: Constraint Resolution and Security Control Verification Report

**Phase Goal:** Operators can resolve a secure, accurately declared PHP 8.2–8.4 dependency graph on real PHP 8.4 without bypassing Composer safeguards.
**Verified:** 2026-07-23T07:19:01Z
**Status:** gaps_found
**Re-verification:** Yes — after Plan 01-07.

## Goal Achievement

### Observable Truths

| # | Truth | Status | Evidence |
|---|---|---|---|
| 1 | A fresh real-PHP-8.4 environment resolves and installs the graph from live Packagist through the final guard without platform emulation, ignore flags, cache, or offline fallback. | ✓ VERIFIED | The explicitly approved PHP 8.4 live gate exited 0 with 807 resolver markers, 807 install markers, Laravel `v11.55.0`, Core `v3.0.2`, configured audit pass, and two isolated empty caller home/cache sets. |
| 2 | Published Composer metadata declares PHP 8.2, 8.3, and 8.4 support without platform emulation. | ✓ VERIFIED | `composer.json` requires `php: ^8.2`, has no `config.platform`, and guarded strict validation passed on PHP 8.4.23. |
| 3 | Caller policy variables and machine-global Composer configuration cannot broaden or disable the exact D-02 policy. | ✓ VERIFIED | The full dependency-free suite passed hostile `COMPOSER_HOME`, all pinned policy-variable rejection, private mode-0700 home, no copied config/auth files, and explicit `COMPOSER_AUTH` preservation. |
| 4 | Only canonical validate, audit, install, and update commands can pass the guard, and install/update reassert the exact manifest immediately before delegation. | ✓ VERIFIED | `ComposerPolicyCommandContract` exposes exactly four allowed commands; the suite rejects aliases, config/global/create-project/self-update/require/remove/selectors and exercises manifest mutation during the policy probe. |
| 5 | All supported Composer-bearing workflow, shell, wrapper, alias, and PHP execution surfaces are detected and fail closed unless they use the guard contract. | ✗ FAILED | Existing route fixtures pass, but independent disposable workflow probes for `bash -c 'composer install'`, `sh -c 'composer update'`, and `eval 'composer install'` each returned `records=0 failures=0`. |
| 6 | Composer delegation preserves live, separate stdout/stderr channels and exact status without deadlocking or buffering unbounded delegated output. | ✓ VERIFIED | The full suite passed live-before-exit, channel identity, exact status 37, 1 MiB per-channel hashes, bounded concurrent preflight capture, and both pipe-order helper cases. Production delegation uses matching parent descriptors. |
| 7 | The pinned Composer executable and canonical checkout boundary cannot be replaced by PATH, COMPOSER_BIN, caller cwd, or Composer working-directory selectors. | ✓ VERIFIED | The suite passed PHAR provenance/digest/version/capability, PATH-shadow non-execution, canonical cwd, and all long/short working-directory rejection cases. |

**Score:** 6/7 truths verified (0 present, behavior-unverified)

### Required Artifacts

| Artifact | Expected | Status | Details |
|---|---|---|---|
| `composer.json` | Honest PHP contract and exact native advisory policy without Roave. | ✓ VERIFIED | `^8.2`, Laravel `^11.0`, Core `^3.0`, `ignore-unreachable: false`, block=true, audit=fail, exactly three documented IDs, no Roave/platform/legacy audit keys. |
| `tools/composer/composer-2.10.2.phar` | Fixed policy-capable Composer distribution. | ✓ VERIFIED | Exists, exact version, matching SHA-256, invoked through `PHP_BINARY`. |
| `tools/composer/composer-2.10.2.phar.sha256` | Strict release provenance. | ✓ VERIFIED | Exactly four lines; digest matches the PHAR. |
| `bin/composer-policy` | Isolated, allowlisted, canonical-root policy guard with correct process I/O. | ✓ VERIFIED | Substantive and behaviorally exercised by the full dependency-free suite. |
| `tools/composer/ComposerPolicyCommandContract.php` | Shared bounded command contract. | ✓ VERIFIED | Loaded by both the guard and route audit; exactly four canonical commands allowed. |
| `tests/Composer/ComposerPolicyGuardTest.php` | Adversarial enforcement and complete fail-closed route regressions. | ✗ PARTIAL / BLOCKER | Enforcement and listed route tests pass, but nested shell-evaluation wrappers silently disappear. |
| `tests/Composer/ComposerPolicyLivePackagistTest.php` | Repeatable fresh PHP 8.4 Packagist proof. | ✓ VERIFIED | Explicitly approved PHP 8.4 execution passed both independent no-cache Packagist paths and configured audit. |
| `.github/workflows/ci.yml`, `README.md` | Current supported dependency routes use the guard. | ✓ WIRED | Production route audit reports exactly CI install and README install/update as guarded. |

### Key Link Verification

| From | To | Via | Status | Details |
|---|---|---|---|---|
| Caller environment/global home | Composer 2.10.2 policy engine | pinned override rejection and private guard-created Composer home | ✓ WIRED | Hostile home/config/auth regressions pass. |
| Guard argv | Shared command contract | `ComposerPolicyCommandContract::decide()` before PHAR execution | ✓ WIRED | Denied commands leave no Composer marker. |
| Install/update | Exact repository policy | manifest assertion before probes and immediately before delegation | ✓ WIRED | Mutation-during-probe regression stops after the policy probe. |
| Route audit | Supported workflow/shell/PHP execution | bounded scalar/token/process parser plus fail-closed fallback | ✗ PARTIAL / BLOCKER | Direct listed forms are caught; nested shell-evaluation payloads produce no record. |
| Delegated Composer | Caller stdout/stderr | direct matching process descriptors | ✓ WIRED | Live streaming, channel identity, large output, and status regressions pass. |
| Live PHP 8.4 checkout | Packagist graph/audit | isolated no-cache guarded resolver and install | ✓ FLOWING | 807 direct resolver markers and 807 direct install markers; tagged Laravel 11/Core 3; configured audit passed. |

### Data-Flow Trace

| Artifact | Data / Input | Source | Produces Correct Data | Status |
|---|---|---|---|---|
| `composer.json` | PHP/Laravel/Core constraints and advisory policy | committed manifest → exact-policy assertion → Composer | Yes | ✓ FLOWING |
| `bin/composer-policy` | caller environment | hostile caller state → private child home + override rejection | Yes | ✓ FLOWING |
| Route audit | supported dependency command text | tracked workflow/shell/PHP → bounded parser → records | No for nested shell evaluators | ✗ DISCONNECTED EDGE |
| Composer delegation | stdout/stderr/status | child direct descriptors → parent channels | Yes | ✓ FLOWING |
| Live integration gate | Packagist metadata | direct no-cache network fetch → temporary solver/install | Yes | ✓ FLOWING |

### Behavioral Spot-Checks

| Behavior | Command / Probe | Result | Status |
|---|---|---|---|
| PHP syntax | `php -l` on guard, command contract, guard test, and live test | All four passed. | ✓ PASS |
| Guard enforcement, I/O, and deadlock safety | `php tests/Composer/ComposerPolicyGuardTest.php` | Exit 0 in 14.46s. | ✓ PASS |
| Production route inventory | `php tests/Composer/ComposerPolicyGuardTest.php --route-audit` | Exit 0; three supported guarded records. | ✓ PASS for current literals |
| Nested shell-evaluation routes | Disposable tracked workflow fixtures using bash-c, sh-c, and eval payloads | Each returned zero records and zero failures. | ✗ FAIL |
| Guarded strict metadata validation | `php bin/composer-policy validate --strict --no-check-publish` | Exit 0; `composer.json is valid`. | ✓ PASS |
| Fresh Packagist resolver/install | Herd PHP 8.4 `tests/Composer/ComposerPolicyLivePackagistTest.php` | Exit 0; resolver markers=807; install markers=807; Laravel v11.55.0; Core v3.0.2; audit pass; two isolated empty home/cache sets. | ✓ PASS |

### Probe Execution

Step 7c: SKIPPED — no `scripts/*/tests/probe-*.sh` is declared. The dependency-free guard suite, production route audit, disposable adversarial probes, and live integration entry point were executed directly.

### Requirements Coverage

| Requirement | Source Plans | Description | Status | Evidence |
|---|---|---|---|---|
| COMP-01 | 01-01, 01-02, 01-05, 01-06, 01-07 | Clean PHP 8.4 standard install without platform ignores or emulation. | ✓ SATISFIED | Explicitly approved final live gate passed both no-cache PHP 8.4 Packagist paths and configured audit. |
| COMP-02 | 01-02, 01-05, 01-06, 01-07 | Accurate PHP 8.2–8.4 declaration. | ✓ SATISFIED | Root `^8.2`, no platform emulation, guarded strict validation on PHP 8.4.23. |
| COMP-03 | 01-01 through 01-07 | Remove the Roave conflict without weakening platform checks or the replacement safeguard. | ✗ BLOCKED | Policy/global isolation, allowlist, PHAR trust, and process I/O pass, but supported nested shell routes can bypass the audit without a failure record. |

No Phase 1 requirement is orphaned. `composer.lock` and root `vendor/` remain absent as required by the Phase 1/Phase 2 boundary.

### Anti-Patterns and Finding Disposition

| File | Line | Pattern | Severity | Impact |
|---|---:|---|---|---|
| `tests/Composer/ComposerPolicyGuardTest.php` | 1155 | Fail-open Composer-text detector only inspects the outer executable position. | 🛑 BLOCKER | Nested shell-evaluation wrappers disappear from supported route evidence. |
| Phase implementation artifacts | — | `TBD`, `FIXME`, or `XXX` | None | No unreferenced debt marker found. |

### Gaps Summary

Plan 01-07 closes the prior effective-policy, command-surface, and process-I/O blockers. It also covers the specifically enumerated YAML, shell-control, alias, wrapper, and PHP fixtures. The route audit is still not fail closed for nested shell evaluators: direct Composer operations inside `bash -c`, `sh -c`, or `eval` are valid supported workflow commands but become zero records. That contradicts Plan 01-07's must-have that unclassifiable Composer-bearing supported execution text must fail closed.

The fresh explicitly approved PHP 8.4 integration passed both independent Packagist paths and the configured audit. COMP-01 and COMP-02 are satisfied; the remaining blocker is confined to COMP-03 route-audit fail-closed coverage.

**Next action:** Do not advance Phase 1 as passed. Add a bounded nested-shell route policy (literal payloads recursively classified; dynamic payloads explicitly unclassified), add the three disposable regressions, rerun the full guard suite and production route audit, then re-verify.

---

_Verified: 2026-07-23T07:19:01Z_
_Verifier: the agent (gsd-verifier)_
