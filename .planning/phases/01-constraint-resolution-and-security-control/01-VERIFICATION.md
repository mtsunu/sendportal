---
phase: 01-constraint-resolution-and-security-control
verified: 2026-07-23T04:36:00Z
status: gaps_found
score: 2/3 must-haves verified
behavior_unverified: 0
overrides_applied: 0
re_verification:
  previous_status: gaps_found
  previous_score: 1/3
  gaps_closed:
    - "Every Composer working-directory selector is rejected before the trusted PHAR runs, and all Composer subprocesses use the canonical checkout root."
    - "Standalone background operators and the Plan 06 wrapper cases are segmented and regression-tested."
    - "Fresh independent PHP 8.4 Packagist-backed install and audit evidence is now available."
  gaps_remaining:
    - "Caller environment and global Composer configuration can weaken the effective advisory policy."
    - "Unrestricted guarded subcommands can mutate the repository policy or leave the repository project."
    - "The tracked-route audit silently misses valid YAML, shell, alias, wrapper, and PHP execution forms."
    - "The guard merges and buffers Composer stderr into stdout, breaking the delegated process contract."
  regressions: []
gaps:
  - truth: "The replacement dependency-security safeguard remains enabled and cannot be broadened or disabled through a supported guarded route."
    status: failed
    reason: "The guard omits Composer 2.10.2's COMPOSER_POLICY_ADVISORIES_BLOCK override and inherits COMPOSER_HOME. Composer merges hostile global policy with the project policy, so the exact three-ID exception is not the effective boundary."
    artifacts:
      - path: "bin/composer-policy"
        issue: "rejectOverrides() does not reject COMPOSER_POLICY_ADVISORIES_BLOCK, and runComposer() inherits caller global configuration."
      - path: "tests/Composer/ComposerPolicyGuardTest.php"
        issue: "The environment matrix repeats the incomplete denylist and has no hostile-COMPOSER_HOME effective-policy regressions."
    missing:
      - "Reject every security-relevant Composer policy environment override discovered from the pinned release, including COMPOSER_POLICY_ADVISORIES_BLOCK."
      - "Run guarded Composer with a repository-controlled empty/dedicated COMPOSER_HOME while carrying credentials through an explicit reviewed mechanism."
      - "Add black-box effective-policy tests for hostile global ignore, ignore-id, ignore-severity, platform, repository, and transport configuration."
  - truth: "The guarded command surface cannot mutate or bypass the repository security policy."
    status: failed
    reason: "The guard forwards every nonempty subcommand. In a disposable checkout, guarded `config policy.advisories.block false` exited 0 and rewrote the project policy from true to false; guarded global configuration also succeeded."
    artifacts:
      - path: "bin/composer-policy"
        issue: "Lines 156-179 have no parsed command allowlist and delegate config, global, create-project, self-update, and other unreviewed commands."
      - path: "tests/Composer/ComposerPolicyGuardTest.php"
        issue: "No regression requires policy-mutating or project-escaping guarded commands to fail before Composer starts."
    missing:
      - "Parse Composer global options and enforce a minimal explicit command allowlist."
      - "Reject config/policy mutations, global, create-project, self-update, command-local file/project selectors, and every unreviewed subcommand."
      - "Reassert the repository manifest's exact policy immediately before resolver/install operations."
  - truth: "All supported Composer mutation routes are detected and required to use the complete policy guard contract."
    status: failed
    reason: "The audit is line-oriented and recognizes a narrow prefix/verb grammar. Folded YAML, list-item run syntax, shell control structures, subshells, timeout wrappers, and Composer aliases all produced zero records; every tracked PHP file is skipped."
    artifacts:
      - path: "tests/Composer/ComposerPolicyGuardTest.php"
        issue: "normalizedLogicalLines() does not assemble YAML block scalars; parseInvocation() omits common wrappers/control syntax and aliases; auditRoutes() skips all PHP files at lines 793-800."
    missing:
      - "Parse workflow YAML scalars with a YAML parser and executable shell with a real or fail-closed bounded shell parser."
      - "Recognize mutation aliases and recursively inspect supported wrappers/control structures."
      - "Inspect production PHP/deployment execution surfaces instead of skipping the entire language."
      - "Make the audit reject guarded commands outside the same command allowlist as bin/composer-policy."
  - truth: "The guarded entry point preserves Composer stdout, stderr, streaming, and bounded-memory behavior."
    status: failed
    reason: "runComposer() redirects child stderr to stdout and buffers the merged stream. Direct `composer --version -vvv` produced 44 stdout bytes and 219 stderr bytes; the guarded command produced 263 stdout bytes and 0 stderr bytes."
    artifacts:
      - path: "bin/composer-policy"
        issue: "Lines 23-39 and 179-181 merge stderr into stdout, delay output until process exit, and retain unbounded command output in memory."
      - path: "tests/Composer/ComposerPolicyGuardTest.php"
        issue: "No channel-specific, streaming, or large-output regression covers delegated Composer behavior."
    missing:
      - "Use a bounded concurrent capture path only for preflight probes."
      - "For delegation, stream child stdout and stderr to their matching parent channels while preserving the exact exit status."
      - "Add separate-channel and output-larger-than-pipe-capacity regressions."
deferred:
  - truth: "The three temporary advisory exceptions are removed or explicitly re-approved at their expiry."
    addressed_in: "Phase 2"
    evidence: "Phase 2 freezes and security-checks the reviewed lockfile; each committed exception explicitly expires at the Phase 2 lockfile review or an eligible stable Core/Laravel upgrade."
---

# Phase 1: Constraint Resolution and Security Control Verification Report

**Phase Goal:** Operators can resolve a secure, accurately declared PHP 8.2–8.4 dependency graph on real PHP 8.4 without bypassing Composer safeguards.
**Verified:** 2026-07-23T04:36:00Z
**Status:** gaps_found
**Re-verification:** Yes — Plan 06 closed the previous working-directory/background-list gaps and supplied fresh live PHP 8.4 evidence, but the current effective-policy boundary remains bypassable.

## Goal Achievement

### Observable Truths

| # | Truth | Status | Evidence |
| --- | --- | --- | --- |
| 1 | An operator on PHP 8.4 can resolve the application's dependencies through standard guarded Composer commands without platform emulation or ignore flags. | ✓ VERIFIED | An independent PHP 8.4.23 run used a disposable checkout, distinct empty Composer home/cache/XDG-cache directories, global `--no-cache`, and the repository guard. Script-enabled `install --prefer-dist --no-interaction --no-progress` and `audit --locked` exited 0, resolved Laravel `v11.55.0` and Core `v3.0.2`, recorded 807 direct Packagist metadata-download markers, and recorded zero cache/offline markers. |
| 2 | Published Composer metadata declares PHP 8.2, 8.3, and 8.4 support. | ✓ VERIFIED | `composer.json` requires `php: ^8.2`; no `config.platform` exists; guarded `validate --strict --no-check-publish` passed on PHP 8.4.23. |
| 3 | The Roave/Laravel conflict is removed while Composer platform checks and an effective dependency-security safeguard remain enabled. | ✗ FAILED | Roave is absent and the manifest policy is narrow, but the effective policy can be disabled by `COMPOSER_POLICY_ADVISORIES_BLOCK`, broadened by inherited global configuration, or rewritten by guarded `config`. The route audit can also silently miss direct mutations. |

**Score:** 2/3 truths verified (0 present-but-behavior-unverified)

### Supporting Must-Have Findings

| Must-have | Status | Evidence |
| --- | --- | --- |
| Composer probes/delegation always use the canonical checkout root and reject all working-directory selectors. | ✓ VERIFIED | The dependency-free black-box suite passed; the guard passes the canonical root to every `proc_open()` and rejects all long/short selector forms before PHAR verification. |
| Standalone `&`, `&&`, `||`, `;`, `|`, quoted/escaped ampersands, and the Plan 06 wrapper cases are independently classified. | ✓ VERIFIED | The dependency-free suite passed those exact fixture cases. |
| The checked-in Composer 2.10.2 PHAR is integrity-, version-, and capability-checked before delegation. | ✓ VERIFIED | PHAR SHA-256 `5ee7125f8a30a34d246cefdc0bc85b8a783b28f2aec968994118512350d28027` matches the strict four-line record; the guard uses fixed repository paths and `PHP_BINARY`. |
| The committed manifest contains only the exact three documented advisory exceptions and no platform emulation. | ✓ VERIFIED | JSON assertion passed for block=true, audit=fail, ignore-unreachable=false, exact three IDs/reasons, no Roave, no `config.platform`, and no manifest-level broad ignore. |
| The effective runtime policy is the same exact policy as the committed manifest. | ✗ FAILED | `COMPOSER_POLICY_ADVISORIES_BLOCK=0` entered the guard; pinned `PolicyConfig.php` lines 196-204 replace the parsed block setting from this variable. A hostile temporary `COMPOSER_HOME` contributed `policy.advisories.ignore-severity: [high]` alongside the project settings. |
| Guarded commands cannot mutate the policy they are intended to enforce. | ✗ FAILED | A disposable guarded `config policy.advisories.block false` changed the copied manifest from true to false and exited 0. |
| Every supported route is exhaustively inventoried. | ✗ FAILED | Folded YAML, `- run:`, `if`, subshell, `timeout`, and alias forms all yielded an empty production-parser result; PHP files are categorically skipped. |
| Guarded Composer preserves normal process I/O semantics. | ✗ FAILED | Direct bytes were stdout=44/stderr=219; guarded bytes were stdout=263/stderr=0. |

### Required Artifacts

| Artifact | Expected | Status | Details |
| --- | --- | --- | --- |
| `composer.json` | Honest PHP contract and narrow native policy without Roave. | ✓ VERIFIED | Substantive, strictly valid, and proven by a fresh PHP 8.4 graph. The file is correct, but the guard does not isolate its effective configuration. |
| `tools/composer/composer-2.10.2.phar` | Pinned native-policy-capable Composer distribution. | ✓ VERIFIED | Exists, substantive, exact version, matching digest, used by all guard subprocesses. |
| `tools/composer/composer-2.10.2.phar.sha256` | Strict release provenance. | ✓ VERIFIED | Exact four-line contract and matching SHA-256. |
| `bin/composer-policy` | Non-bypassable repository policy entry point. | ✗ PARTIAL / BLOCKER | Trusted executable/project-root boundary is implemented, but effective configuration, command surface, and I/O boundaries are incomplete. |
| `tests/Composer/ComposerPolicyGuardTest.php` | Dependency-free enforcement and route-inventory regressions. | ✗ PARTIAL / BLOCKER | Existing tests pass but omit the confirmed policy/global/command/I/O bypasses and contain a fail-open route grammar. |
| `.github/workflows/ci.yml`, `README.md` | Supported dependency routes invoke the guard. | ✓ WIRED / BLOCKED DOWNSTREAM | Current literal commands call the guard, but inherit all guard weaknesses. PHP 8.4 CI itself remains Phase 3 scope. |

### Key Link Verification

| From | To | Via | Status | Details |
| --- | --- | --- | --- | --- |
| Guard | pinned PHAR/provenance | canonical paths, digest/version/capability probes, `PHP_BINARY` | ✓ WIRED | PATH and `COMPOSER_BIN` do not select the executable. |
| Guard | repository project root | rejected working-directory args plus canonical `proc_open` cwd | ✓ WIRED | Previous external-manifest gap is closed. |
| Guard | exact effective manifest policy | environment/global isolation plus command restriction | ✗ NOT WIRED | Policy env override, hostile Composer home, and guarded policy mutation are all accepted. |
| Route audit | every supported executable mutation | YAML/shell parsing and per-invocation classification | ✗ NOT WIRED | Multiple common valid execution forms produce no record; tracked PHP is skipped. |
| Guard | caller process channels | separate streaming stdout/stderr | ✗ NOT WIRED | All delegated output is merged, buffered, and emitted to stdout. |
| Fresh PHP 8.4 checkout | Packagist graph and audit | isolated no-cache guarded install/audit | ✓ FLOWING | Live Packagist data produced stable Laravel 11/Core 3 and the configured audit passed. |

### Data-Flow Trace (Level 4)

| Artifact | Data / Input | Source | Produces Correct Data | Status |
| --- | --- | --- | --- | --- |
| `composer.json` | PHP/Laravel/Core constraints | committed manifest → Composer solver | Yes | ✓ FLOWING |
| `composer.json` | advisory policy | project config + inherited global config + policy environment | No — caller state changes the effective boundary | ✗ HOLLOW TRUST BOUNDARY |
| Route audit | supported dependency invocations | tracked YAML/shell/PHP text → custom parser | No — valid forms disappear as zero records | ✗ DISCONNECTED ROUTES |
| Guard | Composer stdout/stderr | child pipes → merged buffer → wrapper stdout | No — channel identity and streaming are lost | ✗ CORRUPTED FLOW |
| Live install | dependency metadata | direct Packagist requests → solver → temporary lock/vendor | Yes | ✓ FLOWING |

### Behavioral Spot-Checks

| Behavior | Command / Probe | Result | Status |
| --- | --- | --- | --- |
| Dependency-free guard suite | `php tests/Composer/ComposerPolicyGuardTest.php` | Exit 0. | ✓ PASS, incomplete coverage |
| Production route audit | `php tests/Composer/ComposerPolicyGuardTest.php --route-audit` | Exit 0 with 11 records. | ✗ FALSE CONFIDENCE — parser omissions reproduced |
| Fresh clean PHP 8.4 install/audit | isolated PHP 8.4.23 guarded no-cache install plus `audit --locked` | Exit 0; Laravel v11.55.0/Core v3.0.2; 807 Packagist markers; no cache/offline evidence. | ✓ PASS |
| Real advisory-block override | `COMPOSER_POLICY_ADVISORIES_BLOCK=0 php bin/composer-policy config policy.advisories.block` plus pinned source inspection | Guard exited 0; pinned `PolicyConfig.php` reads and applies the variable after config parsing. | ✗ FAIL |
| Hostile global policy | temporary COMPOSER_HOME with `policy.advisories.ignore-severity: high`, then guarded `config --list --source` | Effective config retained `[high]` from the global file alongside the project policy. | ✗ FAIL |
| Guarded policy mutation | disposable guarded `config policy.advisories.block false` | Exit 0; copied manifest changed from true to false. | ✗ FAIL |
| Route parser adversarial forms | production parser evaluated against folded YAML, list-item run, if, subshell, timeout, and alias forms | All six produced `[]`. | ✗ FAIL |
| Composer channel preservation | direct and guarded `--version -vvv` channel byte counts | Direct `[0,44,219]`; guarded `[0,263,0]`. | ✗ FAIL |

### Probe Execution

Step 7c: SKIPPED — no committed `scripts/*/tests/probe-*.sh` probe is declared. The dependency-free suite, production route audit, adversarial disposable probes, and live Packagist integration were executed directly.

### Requirements Coverage

| Requirement | Source Plans | Description | Status | Evidence |
| --- | --- | --- | --- | --- |
| COMP-01 | 01-01, 01-02, 01-05, 01-06 | Clean PHP 8.4 standard dependency installation without platform ignores or emulation. | ✓ SATISFIED | Independent no-cache PHP 8.4.23 install/audit passed against live Packagist with no bypass flags. |
| COMP-02 | 01-02, 01-05, 01-06 | Accurate PHP 8.2–8.4 declaration. | ✓ SATISFIED | Root `^8.2`, no `config.platform`, strict validation passed. |
| COMP-03 | 01-01 through 01-06 | Remove the Roave conflict without weakening platform checks or the replacement safeguard. | ✗ BLOCKED | Effective policy can be disabled/broadened/mutated, and direct routes can evade the audit. |

No Phase 1 requirement is orphaned. `composer.lock` remains intentionally absent because reproducible snapshot ownership belongs to Phase 2.

### Anti-Patterns and Review Finding Disposition

| Finding | Status | Evidence / Impact |
| --- | --- | --- |
| CR-01 advisory-block environment override | 🛑 CONFIRMED BLOCKER | Guard denylist omits the real Composer 2.10.2 variable; pinned source applies it at `PolicyConfig.php:196-204`. |
| CR-02 inherited global policy broadening | 🛑 CONFIRMED BLOCKER | Hostile `COMPOSER_HOME` added severity-wide suppression to the effective configuration. |
| CR-03 unrestricted subcommands | 🛑 CONFIRMED BLOCKER | Guarded project/global config mutations exit successfully; no allowlist exists. |
| CR-04 route-audit fail-open forms | 🛑 CONFIRMED BLOCKER | Six representative valid forms returned no record; all PHP files are skipped. |
| CR-05 stdout/stderr corruption | 🛑 CONFIRMED BLOCKER | Direct and guarded channel counts differ exactly as reviewed; buffering is unbounded. |
| WR-01 test helper two-pipe deadlock | ⚠️ CONFIRMED WARNING | `runCommand()` drains stdout to EOF before stderr at lines 48-49; large stderr can fill the pipe and hang the suite. |
| WR-02 guard regressions/PHP 8.4 absent from CI | ⚠️ CONFIRMED WARNING | CI has only PHP 8.2/8.3 and runs neither guard mode. PHP 8.4 CI is explicitly Phase 3 scope; guard-regression coverage should be added with that work or earlier. |

No unreferenced `TBD`, `FIXME`, or `XXX` marker was found in the Phase 1 implementation artifacts.

### Gaps Summary

Phase 01 proves that the declared dependency graph is compatible with real PHP 8.4 and that the committed manifest itself expresses the intended narrow policy. It does **not** prove that operators must use that policy. Caller environment/global state can alter the effective policy, the guard can rewrite its own policy, and the route audit can miss direct Composer execution. The wrapper also breaks Composer's output-channel contract.

These are observable security-control failures, not human-verification items and not work clearly assigned to a later phase. The phase goal is therefore not achieved.

**Escalation gate / next action:** Do not proceed to Phase 2 on the security-control claim. Run `/gsd:plan-phase 01 --gaps` and close the four structured gaps above, then rerun the dependency-free/adversarial probes and one fresh isolated PHP 8.4 Packagist install/audit.

---

_Verified: 2026-07-23T04:36:00Z_
_Verifier: the agent (gsd-verifier)_

## VERIFICATION COMPLETE
