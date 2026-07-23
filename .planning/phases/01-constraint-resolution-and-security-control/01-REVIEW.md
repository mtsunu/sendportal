---
phase: 01-constraint-resolution-and-security-control
reviewed: 2026-07-23T11:59:58Z
depth: standard
files_reviewed: 9
files_reviewed_list:
  - .github/workflows/ci.yml
  - README.md
  - bin/composer-policy
  - composer.json
  - tests/Composer/ComposerPolicyGuardTest.php
  - tests/Composer/ComposerPolicyLivePackagistTest.php
  - tools/composer/ComposerPolicyCommandContract.php
  - tools/composer/composer-2.10.2.phar
  - tools/composer/composer-2.10.2.phar.sha256
findings:
  critical: 2
  warning: 1
  info: 0
  total: 3
status: issues_found
---

# Phase 01: Code Review Report

**Reviewed:** 2026-07-23T11:59:58Z
**Depth:** standard
**Files Reviewed:** 9
**Status:** issues_found

## Summary

The pinned Composer distribution's recorded SHA-256 matches the checked-in PHAR, the guard retains the exact policy and isolated Composer home, and the declared lint, focused, full-suite, and production route-audit commands pass. The new bounded evaluator parser is nevertheless fail-open for valid shell compound forms: a direct Composer command nested in a function or brace group produces no audit record or failure. Inline PHP launched by a workflow has the same fail-open path. These defeat the Phase 01 supported-route security control and must be fixed before shipping.

## Narrative Findings (AI reviewer)

## Critical Issues

### CR-01: Compound shell syntax hides nested evaluators from the route audit

**File:** `/Users/meigire/Work/idai-jatim/sendportal/tests/Composer/ComposerPolicyGuardTest.php:464-555, 898-1169, 1425-1523`
**Classification:** BLOCKER

**Issue:** `commandChainSegments()` splits parentheses but not brace groups, and `parseInvocation()` only recognizes an evaluator when it is the executable after its small wrapper set. Therefore valid workflow shell such as:

```sh
function runner() { bash -c 'composer install'; }
runner
```

or `{ bash -c 'composer install'; }` reaches neither evaluator recursion nor the unclassified fallback. A disposable tracked workflow fixture returned `records: []` and `failures: []`, even though it executes a direct Composer install. This directly violates the plan's requirement that nested literal evaluator payloads cannot disappear.

**Fix:** Before treating an invocation as absent, recursively handle supported compound forms (at minimum `{ ...; }` and function bodies) or emit an `unclassified` record whenever a supported production scalar contains Composer/evaluator text inside an unparsed compound construct. Add regression fixtures for direct and guarded commands in brace groups and function bodies, requiring a nonempty failure for the direct form.

### CR-02: Workflow `php -r` can execute direct Composer with zero audit evidence

**File:** `/Users/meigire/Work/idai-jatim/sendportal/tests/Composer/ComposerPolicyGuardTest.php:1067-1122, 1207-1257, 1425-1447`
**Classification:** BLOCKER

**Issue:** `parseInvocation()` returns `null` immediately for PHP's `-r` option, treating it as non-executing, while `containsComposerExecutableText()` only inspects the outer executable and not the inline PHP program. Consequently a tracked workflow scalar such as `php -r 'system("composer install");'` yields `records: []` and `failures: []`. The command runs a direct dependency mutation but bypasses the documented fail-closed route audit.

**Fix:** Treat `php -r` as an executable code boundary. Parse a bounded literal program for the same process-launch calls already covered in PHP files, or fail closed with an `unclassified` record whenever its literal program contains Composer/guard text; dynamic code must also be unclassified. Add direct and guarded `php -r` workflow fixtures.

## Warnings

### WR-01: CI never executes the standalone security-route regression suite

**File:** `/Users/meigire/Work/idai-jatim/sendportal/.github/workflows/ci.yml:40-53`
**Classification:** WARNING

**Issue:** The workflow installs dependencies and runs PHPUnit, but neither `ComposerPolicyGuardTest.php` nor its `--route-audit` mode is a PHPUnit test. The regressions that should have caught CR-01 and CR-02 can therefore remain green locally while every CI run passes.

**Fix:** Add a dependency-free step before installation, for example:

```yaml
- name: Verify Composer policy routes
  run: |
    php tests/Composer/ComposerPolicyGuardTest.php
    php tests/Composer/ComposerPolicyGuardTest.php --route-audit
```

---

_Reviewed: 2026-07-23T11:59:58Z_
_Reviewer: the agent (gsd-code-reviewer)_
_Depth: standard_
