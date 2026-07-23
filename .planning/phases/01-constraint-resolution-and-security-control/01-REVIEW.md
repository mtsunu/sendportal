---
phase: 01-constraint-resolution-and-security-control
reviewed: 2026-07-23T04:27:22Z
depth: deep
files_reviewed: 6
files_reviewed_list:
  - bin/composer-policy
  - tests/Composer/ComposerPolicyGuardTest.php
  - composer.json
  - tools/composer/composer-2.10.2.phar.sha256
  - .github/workflows/ci.yml
  - README.md
findings:
  critical: 5
  warning: 2
  info: 0
  total: 7
status: issues_found
---

# Phase 01: Code Review Report

**Reviewed:** 2026-07-23T04:27:22Z
**Depth:** deep
**Files Reviewed:** 6
**Status:** issues_found

## Summary

The pinned PHAR provenance, checksum comparison, exact-version check, policy-capability probe, canonical initial cwd, and explicit working-directory-option rejection are correctly implemented. The manifest also retains the intended PHP `^8.2`, Laravel `^11.0`, Core `^3.0`, fail-closed unreachable-policy setting, and exact three documented advisory exceptions.

The security boundary is still bypassable. Composer 2.10's actual advisory-block override is not rejected; inherited global configuration can add broad policy exceptions; and unrestricted Composer subcommands can mutate the policy or switch to an external project after the canonical process cwd is established. The tracked-route audit is fail-open for common valid shell/YAML forms, while output forwarding now changes Composer's stdout/stderr contract. The dependency-free security tests themselves are neither deadlock-safe nor run in CI, and no committed CI job exercises the target PHP 8.4 runtime.

Review evidence included the complete Phase 01 plan/summary and commit sequence, the dependency-free suite, the production route audit, local Composer 2.10.2 source inspection, and adversarial disposable-repository probes. Existing tests and the route audit pass, but the bypass probes below are not covered.

## Narrative Findings (AI reviewer)

## Critical Issues

### CR-01: Composer's real advisory-block environment override bypasses the guard

**File:** `/Users/meigire/Work/idai-jatim/sendportal/bin/composer-policy:120-125`; `/Users/meigire/Work/idai-jatim/sendportal/tests/Composer/ComposerPolicyGuardTest.php:1034-1040`
**Classification:** BLOCKER

**Issue:** The rejection list omits `COMPOSER_POLICY_ADVISORIES_BLOCK`. The pinned Composer 2.10.2 implementation reads this variable after parsing configuration and replaces the effective `policy.advisories.block` value. With `COMPOSER_POLICY_ADVISORIES_BLOCK=0`, the current guard completed all three synthetic-distribution invocations and exited successfully instead of rejecting before Composer started. The regression matrix repeats the same incomplete environment list, so the native blocking safeguard can be disabled without a failing test.

**Fix:** Reject any nonempty `COMPOSER_POLICY_ADVISORIES_BLOCK` before PHAR verification or execution and add a marker-based regression proving zero Composer invocations. Audit the pinned release's remaining `COMPOSER_POLICY_*`/security-control environment variables whenever the PHAR version changes rather than maintaining an assumed legacy list.

### CR-02: Inherited global Composer configuration broadens the exact-three exception

**File:** `/Users/meigire/Work/idai-jatim/sendportal/bin/composer-policy:23-30,167-179`; `/Users/meigire/Work/idai-jatim/sendportal/tests/Composer/ComposerPolicyGuardTest.php:140-167`
**Classification:** BLOCKER

**Issue:** Every Composer subprocess inherits the caller's `COMPOSER_HOME` and global configuration. Composer deep-merges global policy maps with the project manifest. A disposable global config containing `policy.advisories.ignore-severity: [high]` remained present alongside the three project `ignore-id` entries when invoked through the guard. This violates the phase's exact-exception requirement and lets machine-local state silently suppress additional advisories. The test environment inherits the host home and never supplies hostile global policy.

**Fix:** Run guarded Composer processes with a repository-controlled empty/dedicated `COMPOSER_HOME`; carry credentials through an explicit mechanism such as `COMPOSER_AUTH`. Add hostile-home tests for extra `ignore`, `ignore-id`, `ignore-severity`, platform, repository, and transport configuration, and assert none reaches the effective guarded configuration.

### CR-03: Unrestricted subcommands can disable policy or leave the canonical project

**File:** `/Users/meigire/Work/idai-jatim/sendportal/bin/composer-policy:156-179`; `/Users/meigire/Work/idai-jatim/sendportal/tests/Composer/ComposerPolicyGuardTest.php:732-746,771-775`
**Classification:** BLOCKER

**Issue:** The guard forwards any nonempty command after checking only a short option/environment denylist. In a disposable checkout, `php bin/composer-policy config policy.advisories.block false` exited successfully and rewrote the committed setting to `false`. The route audit ignored that first segment and approved an adjacent guarded `update`, producing no failure. The same unrestricted delegation accepts `global ...`, command-local `config/policy --file`, and `create-project`; those commands intentionally change to or create another project despite the claimed canonical-root policy boundary. The audit explicitly treats guarded `create-project` as supported.

**Fix:** Parse Composer global options and enforce an explicit command contract. Reject `config`, `policy` mutations, `global`, `create-project`, `self-update`, command-local `--file`, and every unreviewed subcommand. Allow only the repository operations actually required (for example install/update plus read-only validate/audit), then assert the exact manifest policy immediately before any resolver operation. Make the route audit fail any guarded invocation outside the same allowlist.

### CR-04: The tracked-route audit fails open for valid shell and YAML commands

**File:** `/Users/meigire/Work/idai-jatim/sendportal/tests/Composer/ComposerPolicyGuardTest.php:442-521,528-746,781-831,793-800`
**Classification:** BLOCKER

**Issue:** The custom parser recognizes only a narrow set of prefixes and line-local YAML shapes. Disposable supported workflow fixtures containing each of the following returned zero records and zero failures: a folded scalar split as `run: >` / `composer` / `install`, `- run: composer install`, `if composer install; then ...; fi`, `(composer install)`, and `timeout 30 composer install`. Composer aliases such as `i`/`u` are also outside the verb allowlist, and lines 793-800 skip every PHP file, including future production deployment scripts. Consequently, a tracked CI/operator route can invoke Composer directly while the audit still reports clean.

**Fix:** Parse YAML scalars with a YAML parser and executable shell with a real shell AST, then inspect every command node and wrapper recursively. Add Composer mutation aliases and production PHP execution surfaces. Until complete parsing exists, fail closed in supported paths whenever Composer-like executable text cannot be confidently classified; do not convert an unrecognized form into "no invocation."

### CR-05: Delegation corrupts Composer's stdout/stderr contract

**File:** `/Users/meigire/Work/idai-jatim/sendportal/bin/composer-policy:23-39,179-181`
**Classification:** BLOCKER

**Issue:** The deadlock repair redirects child stderr into stdout, buffers the entire merged stream, and finally writes it all to wrapper stdout. A direct `composer --version -vvv` emitted 44 bytes to stdout and 219 bytes to stderr; the guarded command emitted all 263 bytes to stdout and zero to stderr. This can corrupt machine-readable stdout (for example JSON audit/show output combined with verbose diagnostics), breaks callers that distinguish errors from results, suppresses live progress/prompts until exit, and retains unbounded command output in memory.

**Fix:** Use separate probe and delegation paths. For bounded preflight probes, drain stdout/stderr concurrently with `stream_select()` and enforce an output limit. For the delegated Composer command, connect child stdout and stderr directly to the parent's corresponding descriptors (or multiplex and forward each stream incrementally) and preserve the exact exit status. Add channel-specific and large-output regressions.

## Warnings

### WR-01: The test process helper still has the original two-pipe deadlock

**File:** `/Users/meigire/Work/idai-jatim/sendportal/tests/Composer/ComposerPolicyGuardTest.php:34-52`
**Classification:** WARNING

**Issue:** `runCommand()` drains stdout to EOF before reading stderr. A child that writes 1 MiB to stderr before closing stdout caused the test process to exceed a three-second timeout (exit 124). A future negative case or Git/Composer diagnostic can therefore hang the security suite instead of producing a result.

**Fix:** Drain both pipes concurrently with nonblocking streams and `stream_select()`, or merge them when channel identity is irrelevant. Add a regression whose child alternates output larger than the platform pipe capacity on both streams.

### WR-02: Security regressions and PHP 8.4 are absent from committed CI

**File:** `/Users/meigire/Work/idai-jatim/sendportal/.github/workflows/ci.yml:8-11,40-53`
**Classification:** WARNING

**Issue:** CI includes only PHP 8.2 and 8.3 containers and runs only dependency installation plus PHPUnit. The dependency-free guard suite and `--route-audit` mode are standalone scripts, so PHPUnit does not execute them. The PHP 8.4 resolver/install evidence in phase summaries is one-off evidence and cannot detect regressions after these files change.

**Fix:** Add PHP 8.4 to the CI matrix and run both `php tests/Composer/ComposerPolicyGuardTest.php` and its `--route-audit` mode before dependency installation. Keep the isolated live-Packagist integration gate separate if its network cost is unsuitable for every job, but ensure at least one required CI job exercises the guard on PHP 8.4.

---

_Reviewed: 2026-07-23T04:27:22Z_
_Reviewer: the agent (gsd-code-reviewer)_
_Depth: deep_
