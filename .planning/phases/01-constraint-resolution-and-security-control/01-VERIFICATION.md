---
phase: 01-constraint-resolution-and-security-control
verified: 2026-07-22T17:08:11Z
status: gaps_found
score: 2/3 must-haves verified
behavior_unverified: 0
overrides_applied: 0
re_verification:
  previous_status: gaps_found
  previous_score: 2/3
  gaps_closed:
    - "Composer fails closed for unreachable policy sources during update and install."
  gaps_remaining:
    - "The Composer 2.10+ policy engine is not required before operator or CI installation routes run."
  regressions: []
gaps:
  - truth: "Dependency resolution no longer fails because of the Roave/Laravel conflict, while Composer platform checks and a dependency-security safeguard remain enabled."
    status: failed
    reason: "The native replacement is implemented only by Composer 2.10+, but neither composer.json nor any supported local, CI, deployment, or documented install route rejects Composer 2.9.x before resolution. Composer 2.9.5 validates and reads the manifest's policy values but has no policy command, so it cannot enforce the replacement safeguard after Roave was removed."
    artifacts:
      - path: "composer.json"
        issue: "Contains a Composer 2.10 policy block but no enforceable Composer toolchain prerequisite or preflight."
      - path: ".github/workflows/ci.yml"
        issue: "Runs composer install at line 43 without asserting Composer >= 2.10.0."
    missing:
      - "Require and verify Composer >= 2.10.0 before every supported install/update route, including CI, deployment, and documented operator instructions."
      - "Make the failure occur before dependency resolution, then test the guard with Composer 2.9.x."
deferred:
  - truth: "The three temporary advisory exceptions cannot survive their stated Phase 2 expiry without explicit review or re-approval."
    addressed_in: "Phase 2"
    evidence: "The exception reasons name Phase 2 lockfile review as their expiry point, and the Phase 1 handoff requires the lockfile review to remove or reassess them."
---

# Phase 1: Constraint Resolution and Security Control Verification Report

**Phase Goal:** Operators can resolve a secure, accurately declared PHP 8.2–8.4 dependency graph on real PHP 8.4 without bypassing Composer safeguards.
**Verified:** 2026-07-22T17:08:11Z
**Status:** gaps_found
**Re-verification:** Yes — after gap closure

## Goal Achievement

### Observable Truths

| # | Truth | Status | Evidence |
| --- | --- | --- | --- |
| 1 | An operator on PHP 8.4 can resolve the application's dependencies through standard Composer commands without platform-emulation or ignore flags. | ✓ VERIFIED | Prior independent verification established a fresh-home script-enabled PHP 8.4 install. Regression check reran Herd Composer 2.10.2 under PHP 8.4.23 with all policy/platform override variables unset: `update --dry-run` resolved 144 packages, including tagged `laravel/framework v11.55.0` and `mettle/sendportal-core v3.0.2`; no repository lockfile was created. |
| 2 | The published Composer metadata declares PHP 8.2, 8.3, and 8.4 as the supported runtime contract. | ✓ VERIFIED | `composer.json` declares `require.php: ^8.2`; strict validation with Composer 2.10.2 passed. The constraint includes PHP 8.2, 8.3, and 8.4. |
| 3 | Dependency resolution no longer fails because of the Roave/Laravel conflict, while Composer platform checks and a dependency-security safeguard remain enabled. | ✗ FAILED | Roave is absent; platform emulation is absent; the narrow policy and `ignore-unreachable: false` are structurally correct and recognized by Composer 2.10.2. However, the normal PATH Composer 2.9.5 also accepts this manifest and lists its arbitrary `policy.*` keys, while `composer policy --help` exits nonzero with `Command "policy" is not defined.` No enforced project contract requires 2.10+ before install/update. |

**Score:** 2/3 truths verified (0 present, behavior-unverified)

### Required Artifacts

| Artifact | Expected | Status | Details |
| --- | --- | --- | --- |
| `composer.json` | PHP 8.2–8.4 declaration and a safe native advisory policy without Roave. | ⚠️ PARTIAL | Substantive and valid: `^8.2`, Laravel `^11.0`, Core `^3.0`, `block: true`, `audit: fail`, boolean `ignore-unreachable: false`, and exactly the three documented advisory IDs. It cannot make that policy effective for the unguarded Composer 2.9 installation route. |
| `.planning/phases/01-constraint-resolution-and-security-control/01-01-SUMMARY.md` | Solver-evidence record. | ✓ PRESENT | Exists and is substantive; treated only as execution history, not as proof for this report. |
| `.planning/phases/01-constraint-resolution-and-security-control/01-02-SUMMARY.md` | Install/audit evidence record. | ✓ PRESENT | Exists and is substantive; independent dry-run regression was performed instead of accepting its claims. |
| `.planning/phases/01-constraint-resolution-and-security-control/01-03-SUMMARY.md` | Fail-closed outage-evidence record. | ✓ PRESENT | Exists and records the gap closure, but it does not wire a minimum Composer version into any supported installation path. |
| `.github/workflows/ci.yml` | CI-side Composer toolchain enforcement. | ✗ MISSING | Existing CI invokes `composer install` directly at line 43 and has no Composer 2.10+ preflight. |

### Key Link Verification

| From | To | Via | Status | Details |
| --- | --- | --- | --- | --- |
| `composer.json config.policy.ignore-unreachable` | Composer update/install policy-source handling | `false` in native policy configuration | ✓ WIRED for Composer 2.10+ | The committed boolean is present. Composer's configuration reference specifies that `false` ignores unreachable policy sources for no operations; Herd Composer 2.10.2 recognizes the `policy` command. |
| `composer.json config.policy.advisories` | Composer dependency resolver and audit | `block=true`, `audit=fail`, exact three-ID map | ⚠️ PARTIAL | Correctly parsed by Composer 2.10.2. PATH Composer 2.9.5 validates the same manifest but has no policy implementation, so the security link is version-conditional without an entry-point guard. |
| Supported `composer install` routes | Composer 2.10+ policy engine | Toolchain preflight before resolution | ✗ NOT_WIRED | No guard was found in `composer.json`, README, CI, or another repository installation wrapper. |
| `composer.json require.php` | Real PHP 8.4 solver | `^8.2`, no `config.platform` or ignore flags | ✓ WIRED | The fresh-home PHP 8.4.23 Composer 2.10.2 dry-run resolution selected the expected tagged Laravel/Core graph. |

### Data-Flow Trace (Level 4)

| Artifact | Data Variable | Source | Produces Real Data | Status |
| --- | --- | --- | --- | --- |
| `composer.json` policy configuration | `config.policy.*` | Composer configuration loader | `composer config --list --source` reads values from `./composer.json`; Herd 2.10.2 exposes a policy engine | ✓ FLOWING for Composer 2.10+ |
| `composer.json` policy configuration | `config.policy.*` | PATH Composer 2.9.5 | Values are listed but no policy engine exists (`policy` command undefined) | ✗ HOLLOW for Composer <2.10 |

### Behavioral Spot-Checks

| Behavior | Command | Result | Status |
| --- | --- | --- | --- |
| Manifest validity and exact narrow policy | PHP JSON assertion; Herd Composer `validate --strict --no-check-publish` | Passed; policy has the exact three IDs, documented reasons, no Roave, no platform emulation, and `ignore-unreachable: false`. | ✓ PASS |
| Real PHP 8.4 resolution | Fresh-home, scrubbed-env Herd Composer 2.10.2 `update --dry-run --prefer-dist --no-interaction --no-scripts --no-progress` | Exit 0; 144 package operations; Laravel `v11.55.0`, Core `v3.0.2`; repository `composer.lock` remained absent. | ✓ PASS |
| Composer 2.10 policy capability | Herd Composer 2.10.2 `policy --help` | Exit 0; command describes custom dependency-policy management. | ✓ PASS |
| Minimum toolchain enforcement | PATH Composer 2.9.5 `policy --help`; search all supported entry points for a `>=2.10.0` guard | Exit nonzero: `Command "policy" is not defined.` No guard found in manifest, CI, README, or installation wrapper. | ✗ FAIL |

### Probe Execution

Step 7c: SKIPPED — this phase contains no committed `scripts/*/tests/probe-*.sh` probe. Its earlier disposable probe narration was not used as verification evidence.

### Requirements Coverage

| Requirement | Source Plan | Description | Status | Evidence |
| --- | --- | --- | --- | --- |
| COMP-01 | 01-01, 01-02 | A clean PHP 8.4 environment can install without platform ignores or emulation. | ✓ SATISFIED | Previous independent full-install verification plus this re-verification's clean, real-PHP-8.4 solver regression; no platform-emulation or ignore surface exists. |
| COMP-02 | 01-02 | Composer constraints declare PHP 8.2, 8.3, and 8.4 support. | ✓ SATISFIED | `require.php` is exactly `^8.2`; strict validation passes. |
| COMP-03 | 01-01, 01-02, 01-03 | Roave conflict is gone without weakening platform checks or the security safeguard. | ✗ BLOCKED | The PHP/platform side is intact and native policy is sound under 2.10+, but the replacement safeguard is absent on accepted Composer <2.10 routes. |

No Phase 1 requirements are orphaned: the plans declare COMP-01, COMP-02, and COMP-03.

### Anti-Patterns Found

| File | Line | Pattern | Severity | Impact |
| --- | --- | --- | --- | --- |
| `composer.json` | 30 | Composer-2.10-only policy without an enforceable Composer version prerequisite. | 🛑 BLOCKER | Composer 2.9.5 accepts the manifest but cannot enforce the replacement safeguard. |
| `.github/workflows/ci.yml` | 43 | Direct install with no Composer version preflight. | 🛑 BLOCKER | A CI image with an older Composer can run an installation without native policy enforcement. |

No `TBD`, `FIXME`, `XXX`, `TODO`, placeholder, broad advisory-ignore, platform-emulation, or platform-ignore marker was found in Phase 1's changed manifest.

### Review Finding Disposition

**CR-01 — confirmed blocker.** The review's concern is not excluded by an enforced project contract. Composer's official [2.10 release note](https://getcomposer.org/changelog/2.10.0-RC2) says the `policy` block was added in 2.10; the current official [configuration reference](https://getcomposer.org/doc/06-config.md#policy) documents the policy enforcement semantics. The current repository has an actual Composer 2.9.5 route that validates the manifest but lacks the policy command, and no minimum version preflight in any supported entry point. The Phase 01 goal's "without bypassing Composer safeguards" wording and the project constraint against silently dropping vulnerability protection make this a goal/COMP-03 gap.

**WR-01 — deferred to Phase 2.** The three exception strings specify their Phase 2 lockfile-review expiry. Phase 2 must remove or expressly re-approve them with an enforceable check. This is not the present blocker.

### Deferred Items

| # | Item | Addressed In | Evidence |
| --- | --- | --- | --- |
| 1 | Enforce expiry/re-approval of the three temporary advisory exceptions. | Phase 2 | Phase 1 exception wording and handoff identify the Phase 2 lockfile review as the reassessment point. |

### Gaps Summary

The initial fail-open outage gap is closed: `ignore-unreachable: false` is now committed. Phase 01 still cannot be accepted because its security replacement depends on a Composer 2.10 feature while pre-2.10 Composer remains an accepted, unguarded operator and CI toolchain. Add a Composer `>=2.10.0` preflight that runs before every supported install/update, document that prerequisite, and demonstrate that Composer 2.9.x fails before resolution. No roadmap phase specifically covers the operator/deployment toolchain floor, so this item is not deferred.

---

_Verified: 2026-07-22T17:08:11Z_
_Verifier: the agent (gsd-verifier)_
