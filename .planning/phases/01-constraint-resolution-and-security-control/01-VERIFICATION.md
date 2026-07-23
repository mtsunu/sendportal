---
phase: 01-constraint-resolution-and-security-control
verified: 2026-07-23T02:58:59Z
status: gaps_found
score: 1/3 must-haves verified
behavior_unverified: 1
overrides_applied: 0
re_verification:
  previous_status: gaps_found
  previous_score: 2/3
  gaps_closed:
    - "Caller PATH and COMPOSER_BIN no longer select the Composer executable; the guard binds to the repository PHAR after a checksum check."
    - "The audit now handles global-option, PHAR, absolute-path, and && mixed-chain fixtures."
  gaps_remaining:
    - "The guard accepts --working-dir/-d forms and delegates Composer against an arbitrary external manifest."
    - "The route audit does not split a shell background (&) chain, allowing a following direct Composer mutation to escape classification."
  regressions: []
gaps:
  - truth: "Dependency resolution keeps Composer security policy enabled for every supported operator and CI route."
    status: failed
    reason: "bin/composer-policy forwards Composer working-directory options without validating the effective project root. A caller can make the trusted PHAR process an arbitrary manifest that omits this repository's native blocking policy."
    artifacts:
      - path: "bin/composer-policy"
        issue: "rejectOverrides() does not reject --working-dir, --working-dir=..., -d <dir>, or compact -d<dir>; runComposer() also supplies no repository-root working directory."
      - path: "tests/Composer/ComposerPolicyGuardTest.php"
        issue: "No regression proves alternate-manifest selection is rejected before Composer probes or delegation."
    missing:
      - "Reject every Composer working-directory option before distribution probes and delegate with the canonical repository root as cwd."
      - "Add dependency-free negative regressions for long, separated short, equals, and compact short working-directory forms."
  - truth: "All supported Composer mutation routes are detected and required to use the policy guard."
    status: failed
    reason: "The audit's shell-chain splitter omits the background separator &, so a line containing a guarded mutation followed by a direct Composer mutation is approved based only on the first invocation."
    artifacts:
      - path: "tests/Composer/ComposerPolicyGuardTest.php"
        issue: "commandChainSegments() splits &&, ||, ;, and | but not standalone &, while parseInvocation() consumes only the leading invocation."
    missing:
      - "Tokenize standalone background separators while preserving quoted and escaped text, then classify every resulting invocation segment."
      - "Add guarded-then-direct and direct-then-guarded background-chain fixtures, plus wrapper coverage for command composer and env -i composer."
deferred:
  - truth: "The three temporary advisory exceptions are removed or explicitly re-approved at their Phase 2 expiry."
    addressed_in: "Phase 2"
    evidence: "Phase 2 freezes and security-checks the reviewed lockfile; it is the planned reassessment point for the time-bounded D-02 exceptions."
behavior_unverified_items:
  - truth: "A clean PHP 8.4 environment resolves the application's dependencies through the guarded standard command without platform-emulation or ignore flags."
    test: "From a clean temporary checkout and empty COMPOSER_HOME on PHP 8.4, run php bin/composer-policy update --dry-run --prefer-dist --no-interaction --no-scripts --no-progress."
    expected: "The trusted guard resolves the PHP ^8.2, Laravel ^11.0, SendPortal Core ^3.0 graph without ignore/platform-emulation flags and without creating repository artifacts."
    why_human: "The independent attempt was blocked by the sandbox DNS restriction (Packagist curl error 6); the requested network rerun was rejected because it would egress the dependency manifest. Existing SUMMARY assertions are not accepted as runtime evidence."
---

# Phase 1: Constraint Resolution and Security Control Verification Report

**Phase Goal:** Operators can resolve a secure, accurately declared PHP 8.2–8.4 dependency graph on real PHP 8.4 without bypassing Composer safeguards.
**Verified:** 2026-07-23T02:58:59Z
**Status:** gaps_found
**Re-verification:** Yes — Phase 01 plan 05 closed the prior PATH/provenance and several chain-parsing gaps, but two separate guard/audit bypasses remain.

## Goal Achievement

### Observable Truths

| # | Truth | Status | Evidence |
| --- | --- | --- | --- |
| 1 | An operator on PHP 8.4 can resolve the graph through standard Composer commands without platform emulation or ignore flags. | ⚠️ PRESENT_BEHAVIOR_UNVERIFIED | The repository is on PHP 8.4.23; `php bin/composer-policy validate --strict --no-check-publish` and the manifest assertion pass. An independent fresh-home PHP 8.4 resolver dry run could not reach Packagist (curl error 6), and the requested egress retry was rejected. This report does not count SUMMARY claims as resolver evidence. |
| 2 | Published Composer metadata declares PHP 8.2, 8.3, and 8.4 support. | ✓ VERIFIED | `composer.json` requires `php: ^8.2`; guarded strict validation passes on PHP 8.4.23; `config.platform` is absent. |
| 3 | The Roave/Laravel conflict is removed while platform checks and a dependency-security safeguard remain enabled. | ✗ FAILED | Roave is absent and the native policy is narrowly configured, but the guard permits an external policy-free manifest and the audit can miss a direct dependency mutation after `&`. Thus the safeguard is not enforced on every claimed route. |

**Score:** 1/3 truths verified (1 present, behavior-unverified)

### Supporting Must-Have Findings

| Must-have | Status | Evidence |
| --- | --- | --- |
| Exact three D-02 advisory IDs with owner-accepted-risk/expiry reasons; no broad ignore or platform emulation. | ✓ VERIFIED | Current JSON assertion confirms the three named IDs only, `block: true`, `audit: fail`, `ignore-unreachable: false`, no Roave, and no `config.platform`. |
| Repository-tracked Composer 2.10.2 PHAR is the only executable selected; PATH and `COMPOSER_BIN` cannot select one. | ✓ VERIFIED | Guard derives root from `realpath(__FILE__)`, verifies four-line provenance and SHA-256, and invokes `[PHP_BINARY, fixed PHAR, ...]`. The digest is `5ee712…d28027`; dependency-free regression passed, including a PATH shadow that did not execute. |
| Missing, unreadable, changed, and wrong-version distributions fail before delegation. | ✓ VERIFIED | The dependency-free guard suite passes its missing/tampered/malformed/unreadable/wrong-version cases. |
| Each tracked mutation is independently detected and classified across supported shell chains. | ✗ FAILED | `commandChainSegments()` omits `&`; its passing fixtures cover only `&&` mixed chains. |
| Every guard invocation remains bound to this checkout's security policy. | ✗ FAILED | The guard forwards working-directory options and does not set a repository cwd; external-manifest validation succeeds. |

### Required Artifacts

| Artifact | Expected | Status | Details |
| --- | --- | --- | --- |
| `composer.json` | PHP/runtime contract and native advisory policy without Roave. | ✓ VERIFIED | Substantive, guarded strict validation and exact policy assertion pass. |
| `tools/composer/composer-2.10.2.phar` | Pinned Composer distribution. | ✓ VERIFIED | Exists; SHA-256 matches the checked-in record; used by guard preflights and delegation. |
| `tools/composer/composer-2.10.2.phar.sha256` | Strict release-provenance/digest record. | ✓ VERIFIED | Four expected lines match the guard contract and the PHAR digest. |
| `bin/composer-policy` | Fail-closed policy guard for the repository manifest. | ✗ PARTIAL / BLOCKER | Substantive and wired to the trusted PHAR, but accepts working-directory arguments that select an external manifest. |
| `tests/Composer/ComposerPolicyGuardTest.php` | Regression and complete route-audit proof. | ✗ PARTIAL / BLOCKER | The suite is substantive and passes, but has neither alternate-manifest tests nor `&`-chain tests; its parser demonstrably omits `&`. |
| `.github/workflows/ci.yml`, `README.md` | CI and documented mutation paths use the guard. | ⚠️ PARTIAL | All three current routes call the guard, but the guard's working-directory bypass means that call is not a complete policy boundary. |

### Key Link Verification

| From | To | Via | Status | Details |
| --- | --- | --- | --- | --- |
| `bin/composer-policy` | repository PHAR and provenance record | canonical paths, strict record/hash, `PHP_BINARY` probes/delegation | ✓ WIRED | No PATH lookup remains; tests prove a PATH shadow does not execute. |
| guard arguments | repository `composer.json` policy | reject untrusted project-selection options and use repository cwd | ✗ NOT_WIRED | Lines 122–128 only reject bypass flags; line 161 forwards all remaining arguments and `proc_open` receives no cwd. |
| route audit | each supported command-chain segment | segmentation then invocation parsing/classification | ✗ NOT_WIRED | Lines 235–240 omit `&`; the direct command after a background separator is never independently parsed. |
| CI/README mutation commands | `bin/composer-policy` | `php bin/composer-policy install/update` | ✓ WIRED | CI line 43 and README lines 38–39 use the guard. |

### Data-Flow Trace (Level 4)

| Artifact | Data Variable / Input | Source | Produces Correct Data | Status |
| --- | --- | --- | --- | --- |
| Guard | fixed PHAR path | canonical script root → `tools/composer/` | Yes | ✓ FLOWING |
| Guard | effective Composer project | forwarded CLI arguments | No — `--working-dir`/`-d` redirects Composer to an external manifest | ✗ HOLLOW TRUST BOUNDARY |
| Route audit | mutation segments | tracked logical line → regex splitter | No — standalone `&` remains in the first segment | ✗ DISCONNECTED INVOCATION |

### Behavioral Spot-Checks

| Behavior | Command | Result | Status |
| --- | --- | --- | --- |
| Guard syntax/regression suite | `php -l … && php tests/Composer/ComposerPolicyGuardTest.php` | Exit 0. | ✓ PASS, but insufficient coverage |
| Current tracked route audit | `php tests/Composer/ComposerPolicyGuardTest.php --route-audit` | Exit 0 with 12 records. | ✗ FAILS TO PROVE CLAIM — fixtures omit `&`. |
| External manifest rejection | `php bin/composer-policy --working-dir=/Users/meigire/Work/idai-jatim/siemas-apex validate --no-check-publish` | Exit 0; it validated that separate `nunomaduro/laravel-starter-kit` manifest, which has no `config.policy`. Separated `--working-dir`, `-d <dir>`, and compact `-d<dir>` also exited 0. | ✗ FAIL |
| Background chain segmentation | PHP evaluation of the production splitter against `php bin/composer-policy install & composer install` | One unsplit segment returned; the leading guard is recognized while the later direct command is not a separate record. | ✗ FAIL |
| Fresh PHP 8.4 dependency resolution | isolated temporary guarded `update --dry-run` | Blocked by sandbox Packagist DNS; egress retry rejected. | ? SKIP — human verification needed |

### Probe Execution

Step 7c: SKIPPED — Phase 01 declares no committed `scripts/*/tests/probe-*.sh` probe. The dependency-free guard suite and the production route audit were executed directly above.

### Requirements Coverage

| Requirement | Source Plan | Description | Status | Evidence |
| --- | --- | --- | --- | --- |
| COMP-01 | 01-01, 01-02, 01-04, 01-05 | Clean PHP 8.4 standard Composer resolution without bypasses. | ? NEEDS HUMAN | PHP 8.4.23 and guarded strict validation were observed, but a new isolated resolver run was prevented by DNS/egress policy. |
| COMP-02 | 01-02, 01-05 | Accurate PHP 8.2–8.4 declaration. | ✓ SATISFIED | Root PHP constraint is `^8.2`; strict validation passes; no platform emulation is configured. |
| COMP-03 | 01-01 through 01-05 | Remove Roave conflict while retaining effective, non-bypassed safeguards. | ✗ BLOCKED | Native policy is present, but alternate-manifest forwarding and background-chain audit evasion leave two observable bypasses. |

No Phase 1 requirement is orphaned. `composer.lock` remains intentionally absent in Phase 1; its reviewed lockfile/audit lifecycle belongs to Phase 2.

### Anti-Patterns Found

| File | Line | Pattern | Severity | Impact |
| --- | --- | --- | --- | --- |
| `bin/composer-policy` | 122–128, 161 | Missing effective-working-directory boundary. | 🛑 BLOCKER | Trusted Composer can evaluate and mutate an arbitrary project without the repository's policy. |
| `tests/Composer/ComposerPolicyGuardTest.php` | 235–240, 570–579 | Incomplete shell-chain tokenizer and fixtures. | 🛑 BLOCKER | A supported route may contain an unguarded Composer mutation after `&` yet receive a clean audit. |

No `TBD`, `FIXME`, or `XXX` marker was found in Phase-01 implementation artifacts.

### Review Finding Disposition

**Working-directory bypass — confirmed blocker.** The current guard's trusted executable boundary is real, but it is not a trusted project boundary. It does not reject `--working-dir`/`-d`, and `proc_open()` is not given the canonical root. Current execution validated a distinct external manifest without a policy block.

**Background-chain bypass — confirmed blocker.** The current splitter uses `/(?:&&|\|\||;|\|)/`, which cannot produce an independent segment for `&`. The passing suite covers neither background-chain order. A code-level spot check returned one segment for a guarded command followed by `& composer install`.

The temporary-advisory expiry remains deliberately deferred to Phase 2 and is not an actionable Phase 1 gap.

### Gaps Summary

Phase 01 now has a genuine repository-owned Composer binary and an accurate root policy declaration. However, the security-control outcome is still not achieved: a guarded command can target an arbitrary external manifest, and the claimed complete audit can omit a direct dependency mutation after a background operator. Both defeat the promise that Composer safeguards apply to every supported resolution route.

**Escalation gate:** Do not proceed to Phase 2 on the Phase 01 security-control claim. Repair both input/tokenization boundaries, add negative regressions, then rerun the guarded fresh-PHP-8.4 resolver with authorized Packagist access.

---

_Verified: 2026-07-23T02:58:59Z_
_Verifier: the agent (gsd-verifier)_

## VERIFICATION COMPLETE
