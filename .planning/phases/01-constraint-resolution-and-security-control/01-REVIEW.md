---
phase: 01-constraint-resolution-and-security-control
reviewed: 2026-07-23T02:52:43Z
depth: deep
files_reviewed: 7
files_reviewed_list:
  - composer.json
  - bin/composer-policy
  - tools/composer/composer-2.10.2.phar
  - tools/composer/composer-2.10.2.phar.sha256
  - tests/Composer/ComposerPolicyGuardTest.php
  - .github/workflows/ci.yml
  - README.md
findings:
  critical: 2
  warning: 0
  info: 0
  total: 2
status: issues_found
---

# Phase 01: Code Review Report

**Reviewed:** 2026-07-23T02:52:43Z
**Depth:** deep
**Files Reviewed:** 7
**Status:** issues_found

## Summary

The previous PATH trust failure (CR-01) is resolved: the guard derives its root from its canonical script location, accepts only the fixed repository PHAR, strictly checks the four-line provenance record and SHA-256 before any PHP subprocess, and invokes every probe/delegation through `PHP_BINARY`. The former CR-02 global-option, PHAR, absolute-path, and `&&` mixed-chain cases are also covered with isolated Git fixtures. The final security-control claim still fails because the guard permits operations against an arbitrary external manifest, and the route audit has another shell-chain bypass.

The PHAR checksum matches the checked-in record, its 0644 mode is appropriate because it is passed to `PHP_BINARY`, the guard remains executable (0755), CI and README use the guard, and the dependency-free guard suite plus production route audit pass. Those passing tests do not cover the two cases below. Phase 2 advisory-exception expiry review is out of scope.

## Narrative Findings (AI reviewer)

## Critical Issues

### CR-01: Guard accepts an alternate project and therefore bypasses the repository policy

**File:** `/Users/meigire/Work/idai-jatim/sendportal/bin/composer-policy:122-128,161`
**Classification:** BLOCKER

**Issue:** The guard forwards every non-explicitly-blocked argument and never changes or validates Composer's working directory. Composer's global `--working-dir`/`-d` option therefore selects an arbitrary directory and its `composer.json` after the trusted-PHAR/version/policy probes. A disposable external manifest was accepted by `php bin/composer-policy --working-dir=<temp> validate --no-check-publish` with exit status 0. An `install` or `update` can consequently run with an external manifest that omits this repository's blocking advisory policy, despite the guard's purpose and the plan's requirement to reject alternate-manifest selection.

**Fix:** Resolve the canonical repository root once, require the invocation's effective working directory to equal that root, and reject `--working-dir`, `--working-dir=...`, `-d <dir>`, `-d=<dir>`, and compact `-d<dir>` forms before the PHAR probe. Run the delegated command with that root as its process working directory. Add dependency-free regressions proving each form fails before the trusted distribution's version probe and that an external manifest cannot be validated or installed through the guard.

### CR-02: Route audit overlooks direct Composer calls after a background separator

**File:** `/Users/meigire/Work/idai-jatim/sendportal/tests/Composer/ComposerPolicyGuardTest.php:235-240,254-324,570-579`
**Classification:** BLOCKER

**Issue:** `commandChainSegments()` splits `&&`, `||`, `;`, and `|`, but not the shell background operator `&`. For example, a supported route containing `php bin/composer-policy install & composer install` remains one segment. `parseInvocation()` classifies the initial guard invocation as supported and ignores the remaining tokens, so the later direct `composer install` is neither recorded nor rejected. The fixture coverage tests only the `&&` forms. Thus the audit can still report a clean production inventory while a supported CI/operator route contains an unguarded dependency mutation.

**Fix:** Parse shell command lists with a tokenizer that treats standalone `&` as a chain separator (while respecting quoted/escaped text), then require every resulting invocation segment to be classified independently. Add isolated-fixture failures for guarded-then-direct and direct-then-guarded background chains, plus representative supported wrappers such as `command composer` and `env -i composer`, which the current token stripping also does not inspect.

---

_Reviewed: 2026-07-23T02:52:43Z_
_Reviewer: the agent (gsd-code-reviewer)_
_Depth: deep_

## REVIEW COMPLETE
