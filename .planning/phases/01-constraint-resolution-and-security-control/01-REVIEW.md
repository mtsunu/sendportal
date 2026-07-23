---
phase: 01-constraint-resolution-and-security-control
reviewed: 2026-07-23T14:04:00Z
depth: standard
files_reviewed: 2
files_reviewed_list:
  - .github/workflows/ci.yml
  - tests/Composer/ComposerPolicyGuardTest.php
findings:
  critical: 2
  warning: 1
  info: 0
  total: 3
status: issues_found
---

# Phase 01: Code Review Report

**Reviewed:** 2026-07-23T14:04:00Z
**Depth:** standard
**Files Reviewed:** 2
**Status:** issues_found

## Summary

Plan 01-09 correctly adds a dependency-free CI gate before the guarded install and closes the exact bare brace/function and bare `php -r` cases in its fixtures. The bounded route audit remains fail-open for valid wrapper/option forms of inline PHP and for Composer-bearing dynamic launch variables. In both cases a supported workflow can execute a direct Composer mutation while `auditRoutes()` returns no record and `routeAuditFailures()` remains empty.

## Narrative Findings (AI reviewer)

## Critical Issues

### CR-01: PHP CLI options and supported wrappers bypass the inline `-r` audit

**File:** `tests/Composer/ComposerPolicyGuardTest.php:1568-1589, 1684-1692, 1747-1752`
**Classification:** BLOCKER

**Issue:** `inlinePhpProgram()` recognizes only a segment whose first two tokens are exactly `php` and `-r`. Valid PHP invocations such as `php -n -r 'system("composer install");'` and supported existing wrappers such as `env -i php -r 'system("composer install");'`, `command php -r ...`, `sudo -n php -r ...`, and `timeout 30 php -r ...` therefore bypass the inline parser. `parseInvocation()` then deliberately returns `null` for `-r`, while the generic Composer-text detector sees only the outer wrapper/PHP executable. A staged supported workflow probe produced `records: []` and `failures: []` for every listed form, although `php -n -r` and `env -i php -r` execute inline code.

**Fix:** Normalize the same accepted wrapper and PHP option grammar before deciding whether a segment is inline PHP. Once a `-r`/`--run` boundary is found, classify its single literal program; for every unsupported or malformed argv shape that still reaches `-r`, emit one `unclassified-php` record whenever it is Composer/evaluator-bearing. Add staged fixtures for at least `php -n -r`, `php -d key=value -r`, and `env -i php -r`, requiring nonempty failure evidence for direct Composer.

### CR-02: Composer-bearing variables feeding an inline process launch disappear

**File:** `tests/Composer/ComposerPolicyGuardTest.php:1600-1601, 1631-1638, 1668-1670`
**Classification:** BLOCKER

**Issue:** The complete inline program is marked Composer-bearing at line 1600, but a dynamic launch is reported only when that launch expression itself contains Composer text. Consequently a valid program such as `php -r '$command = "composer install"; system($command);'` has a Composer-bearing program and a dynamic `system($command)` launch, yet line 1633 does not record it because `$launch['composer_bearing']` is false for `$command`. The loop finishes with no records, so the route audit passes while the workflow executes direct Composer. The same zero-record result occurs with `exec($command)` and with a variable containing `bash -c 'composer install'`.

**Fix:** When a literal inline program is Composer/evaluator-bearing, a dynamic or unclassifiable process-launch expression must fail closed even if that individual expression lacks the literal string. For example, return one `unclassified-php` record when `$programBearing && $launch['tokens'] === null`, and add a final `$programBearing && $records === []` safety net. Add staged variable-assignment fixtures for `system($command)` and `exec($command)` and assert that each produces a failure.

## Warnings

### WR-01: Inline launch-limit regression uses the evaluator limit constant

**File:** `tests/Composer/ComposerPolicyGuardTest.php:2700`
**Classification:** WARNING

**Issue:** The inline-PHP launch-count fixture is built with `MAX_ROUTE_EVALUATOR_PAYLOADS + 1` instead of `MAX_ROUTE_INLINE_PHP_LAUNCHES + 1`. Both are currently 32, so the test passes, but a later independent limit adjustment can leave this regression test below the actual inline limit and stop exercising the intended fail-closed path.

**Fix:** Build the fixture with `MAX_ROUTE_INLINE_PHP_LAUNCHES + 1`.

---

_Reviewed: 2026-07-23T14:04:00Z_
_Reviewer: the agent (gsd-code-reviewer)_
_Depth: standard_
