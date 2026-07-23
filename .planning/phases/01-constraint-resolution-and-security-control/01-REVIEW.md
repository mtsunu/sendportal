---
phase: 01-constraint-resolution-and-security-control
reviewed: 2026-07-23T00:39:33Z
depth: deep
files_reviewed: 5
files_reviewed_list:
  - composer.json
  - bin/composer-policy
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

**Reviewed:** 2026-07-23T00:39:33Z
**Depth:** deep
**Files Reviewed:** 5
**Status:** issues_found

## Summary

The manifest policy, Composer 2.10 preflight, CI/README routing, syntax checks, strict manifest validation, and dependency-free regression suite all pass. However, the guard still trusts an arbitrary `PATH`-selected program, and its tracked-route audit does not actually prove that all mutation routes are guarded. These defects defeat the Phase 01 security-control claim and must be fixed before acceptance.

## Narrative Findings (AI reviewer)

## Critical Issues

### CR-01: PATH can replace the Composer policy engine with an arbitrary program

**File:** `/Users/meigire/Work/idai-jatim/sendportal/bin/composer-policy:40-62`
**Issue:** `resolveComposer()` selects the first readable executable named `composer` from the caller-controlled `PATH`. A malicious PHP file can print `Composer version 2.10.2` for the version probe, return success for `policy --help`, and then run arbitrary code or ignore the manifest policy during delegation. Running that file with `PHP_BINARY` only avoids shebang interpreter selection; it does not authenticate that the file is Composer. The existing regression itself proves such a PATH-selected fake Composer is accepted (`ComposerPolicyGuardTest.php:298-324`). This contradicts the required rejection of environment-selected executable bypasses before dependency resolution.

**Fix:** Establish a trusted Composer binary boundary: for example, install/pin a repository-owned Composer PHAR (with a verified checksum) and invoke that absolute path, or have CI provide and verify a fixed absolute Composer path/hash before calling the guard. Reject a relative/empty `PATH` entry and do not treat a successful self-reported version/help response as executable provenance. Add a regression where a PATH shadow program reports 2.10.2 and succeeds at `policy --help`; the guard must reject it without executing it.

### CR-02: The tracked-route audit is bypassable and can falsely approve an unguarded command

**File:** `/Users/meigire/Work/idai-jatim/sendportal/tests/Composer/ComposerPolicyGuardTest.php:147-165,201-215`
**Issue:** The matcher only recognizes `composer` when the mutation verb immediately follows it, so valid direct forms such as `composer --no-interaction install`, `composer.phar install`, and `/opt/composer install` are absent from the records. Further, classification receives the entire logical line and approves it whenever that line merely contains `bin/composer-policy` (`str_contains($form, ...)`). A supported route such as `composer install && php bin/composer-policy install` would therefore record the direct `composer install` match as `supported`, even though the first command bypasses the guard. The route audit can pass while CI or operator documentation contains an unguarded mutation, invalidating its claimed proof that every supported route reaches the guard.

**Fix:** Parse each command invocation (including prefix/global-option/path/PHAR forms) and classify the matched command chain, not the entire line. A direct Composer invocation must fail the audit unless that invocation itself is the guard; a later guarded command on the same line must not satisfy it. Add negative fixtures for `composer --no-interaction install`, an absolute/PHAR Composer path, and `composer install && php bin/composer-policy install`, and require each to fail the audit.

---

_Reviewed: 2026-07-23T00:39:33Z_
_Reviewer: the agent (gsd-code-reviewer)_
_Depth: deep_

## REVIEW COMPLETE
