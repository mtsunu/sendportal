---
phase: 01-constraint-resolution-and-security-control
verified: 2026-07-22T16:21:23Z
status: gaps_found
score: 2/3 must-haves verified
behavior_unverified: 0
overrides_applied: 0
gaps:
  - truth: "Dependency resolution no longer fails because of the Roave/Laravel conflict, while Composer platform checks and a dependency-security safeguard remain enabled."
    status: failed
    reason: "The committed native policy leaves Composer's policy.ignore-unreachable at its documented default of [update, install], so an unreachable advisory repository or policy source is silently ignored during the two operations this phase must secure. block: true and audit: fail therefore run with incomplete advisory data rather than failing closed."
    artifacts:
      - path: "composer.json"
        issue: "config.policy at line 30 lacks ignore-unreachable: false."
    missing:
      - "Set config.policy.ignore-unreachable to false and prove an unreachable advisory/policy source makes the isolated PHP 8.4 update/install fail."
deferred:
  - truth: "The three accepted advisory exceptions cannot remain after their stated Phase 2 expiry without an explicit re-approval."
    addressed_in: "Phase 2"
    evidence: "D-02 names Phase 2 lockfile review as the expiry point; Phase 2 success criterion 3 requires the exact locked graph to pass a non-bypassed security audit."
---

# Phase 1: Constraint Resolution and Security Control Verification Report

**Phase Goal:** Operators can resolve a secure, accurately declared PHP 8.2–8.4 dependency graph on real PHP 8.4 without bypassing Composer safeguards.
**Verified:** 2026-07-22T16:21:23Z
**Status:** gaps_found
**Re-verification:** No — initial verification

## Goal Achievement

### Observable Truths

| # | Truth | Status | Evidence |
| --- | --- | --- | --- |
| 1 | An operator on PHP 8.4 can resolve dependencies through standard Composer commands without platform emulation or ignore flags. | ✓ VERIFIED | Independently ran a script-enabled `composer install --prefer-dist --no-interaction --no-progress` in a repository copy under PHP 8.4.23 / Composer 2.10.2, with a new empty `COMPOSER_HOME` and all policy/platform override variables unset. It completed 144 installs and package discovery, selecting `laravel/framework v11.55.0` and `mettle/sendportal-core v3.0.2`. |
| 2 | Published Composer metadata declares PHP 8.2, 8.3, and 8.4 as supported. | ✓ VERIFIED | `composer.json:7` is `php: ^8.2`, which includes PHP 8.2–8.x; `composer validate --strict --no-check-publish` passed under PHP 8.4.23 / Composer 2.10.2. |
| 3 | The Roave/Laravel conflict is removed while platform checks and an effective dependency-security safeguard remain enabled. | ✗ FAILED | Roave is absent and the three-ID policy is structurally narrow, but `config.policy.ignore-unreachable` is missing. Composer documents its default as `["update", "install"]`, which silently ignores unreachable policy/repository sources during resolution and install. The required security control is therefore fail-open on that observable error path. |

**Score:** 2/3 truths verified (0 present, behavior-unverified)

## Required Artifacts

| Artifact | Expected | Status | Details |
| --- | --- | --- | --- |
| `composer.json` | Accurate PHP contract and Composer-native advisory policy without Roave. | ⚠️ PARTIAL | Exists and is substantive. It declares `^8.2`, retains Laravel `^11.0` / Core `^3.0`, has `block: true`, `audit: fail`, exactly the three approved IDs and their required reasons, and contains none of the prohibited broad-ignore/platform-bypass surfaces. Its missing fail-closed `ignore-unreachable: false` setting blocks the security-control truth. |
| `01-02-SUMMARY.md` | Candidate/install/audit record and Phase 2 handoff. | ✓ VERIFIED | Exists and records concrete commands and versions. It was not accepted as proof: the install and audits below were rerun independently. |
| `composer.lock` | No committed lockfile in Phase 1. | ✓ VERIFIED | Absent from the working tree and from tracked files. The verification install created a lockfile only in `/private/tmp/sendportal-phase1-verify.KtYUOX`. |

## Key Link Verification

| From | To | Via | Status | Details |
| --- | --- | --- | --- | --- |
| `composer.json config.policy.advisories` | Composer resolver and `composer audit --locked` | `block=true`, `audit=fail`, exact `ignore-id` map | ⚠️ PARTIAL | The configured audit passed in the isolated copy and the three ignored advisories were displayed with their reasons. An ignore-free evidence copy exited 1 and parsed to exactly the approved three IDs. The upstream-source-unreachable path remains fail-open because the enclosing policy omits `ignore-unreachable: false`. |
| `composer.json require.php` | Real PHP 8.4 clean install | `^8.2`, fresh `COMPOSER_HOME` | ✓ WIRED | The independent isolated install above succeeded with no `config.platform`, `--ignore-platform-req*`, or inherited Composer policy/environment variables. |

## Data-Flow Trace (Level 4)

| Artifact | Data Variable | Source | Produces Real Data | Status |
| --- | --- | --- | --- | --- |
| `composer.json` advisory policy | advisory IDs and ignore reasons | Packagist security-advisories endpoint during `composer audit --locked` | Configured audit displayed the three mapped IDs; ignore-free JSON audit returned exactly those IDs | ✓ FLOWING (normal reachable-source path) |

## Behavioral Spot-Checks

| Behavior | Command | Result | Status |
| --- | --- | --- | --- |
| Manifest validity and exact narrow policy | Herd Composer 2.10.2 `validate --strict --no-check-publish` plus PHP JSON assertion | Exit 0 / `MANIFEST_PASS` | ✓ PASS |
| Standard PHP 8.4 install | Fresh-home, scrubbed-env `composer install --prefer-dist --no-interaction --no-progress` in a disposable copy | Exit 0; 144 packages; Laravel `v11.55.0`, Core `v3.0.2`; package discovery completed | ✓ PASS |
| Configured audit | Fresh-home, scrubbed-env `composer audit --locked` in that copy | Exit 0; only the three documented ignored advisories shown | ✓ PASS |
| Exact residual-risk boundary | Ignore-free copy `composer audit --locked --format=json` and exact-ID parser | Audit exit 1 as expected; parser returned only `PKSA-3r5d-mb8f-1qw9`, `PKSA-m5cs-t1y6-qpcs`, and `PKSA-mdq4-51ck-6kdq` | ✓ PASS |
| Advisory-source outage | Inspect Composer policy semantics | **FAIL:** official Composer configuration documents `policy.ignore-unreachable` as defaulting to `["update", "install"]`; the committed policy does not override it. A temporary manifest with `ignore-unreachable: false` validated and Composer recognized the value. | ✗ FAIL |

## Requirements Coverage

| Requirement | Source Plan | Description | Status | Evidence |
| --- | --- | --- | --- | --- |
| COMP-01 | 01-02 | A clean PHP 8.4 environment can install without platform ignores or emulation. | ✓ SATISFIED | Independently reproduced standard isolated install on PHP 8.4.23 with override variables removed. |
| COMP-02 | 01-02 | Composer constraints declare PHP 8.2, 8.3, and 8.4 support. | ✓ SATISFIED | `require.php` is `^8.2`; strict validation passed. |
| COMP-03 | 01-01, 01-02 | Roave conflict is gone without weakening platform checks or the security safeguard. | ✗ BLOCKED | The narrow policy is present and works while Packagist is reachable, but its default unreachable-source behavior weakens the safeguard for update/install. |

No Phase 1 requirements are orphaned: both plans declare all three `COMP-*` IDs.

## Anti-Patterns Found

| File | Line | Pattern | Severity | Impact |
| --- | --- | --- | --- |
| `composer.json` | 30 | Omitted `config.policy.ignore-unreachable: false`; Composer defaults to silently ignoring unreachable policy/repository sources for update/install. | 🛑 BLOCKER | A security-control outage can permit resolution/install with incomplete advisory data, contrary to the no-bypass dependency-security objective. |

No `TBD`, `FIXME`, `XXX`, placeholder, broad-ignore, platform-emulation, platform-ignore, no-blocking, policy-disable, or development-branch Laravel marker was found in the Phase 1 source artifact.

## Review Finding Disposition

### CR-01 — confirmed Phase 1 blocker

This is not an out-of-scope hardening request. D-02 requires Composer's native policy to replace Roave while **all other advisories remain blocking and failing**; Phase 1's goal and COMP-03 require the retained safeguard to remain enabled. `config.policy.ignore-unreachable: false` is a minimal policy completion within the only permitted Phase 1 change surface (`composer.json`). Composer's official configuration reference states that the omitted setting defaults to `["update", "install"]`, silently ignoring unreachable repository or policy sources for those operations. The current manifest consequently misses a required error-path control.

### WR-01 — follow-up for Phase 2, not a Phase 1 failure

Phase 1 was explicitly approved to record a per-ID expiry reason, which each entry does. The planned expiry point is **Phase 2 lockfile review** (D-02), and Phase 2 must make that review enforceable — for example, fail a policy validation when the temporary exceptions survive without documented re-approval. It does not contradict an observable Phase 1 must-have, but must be preserved in Phase 2 planning; the current roadmap does not yet specify the enforcement command.

## Gaps Summary

One blocker prevents acceptance of the phase goal: add a fail-closed `config.policy.ignore-unreachable: false` alongside the existing advisory policy, then rerun the clean PHP 8.4 install/audit evidence with an intentional unreachable policy/advisory-source assertion. This preserves the approved narrow three-ID exception and does not require a Laravel/Core change or a lockfile.

---

_Verified: 2026-07-22T16:21:23Z_
_Verifier: the agent (gsd-verifier)_
