---
phase: 01-constraint-resolution-and-security-control
reviewed: 2026-07-23T15:45:00Z
depth: deep
files_reviewed: 9
files_reviewed_list:
  - composer.json
  - bin/composer-policy
  - tools/composer/ComposerPolicyCommandContract.php
  - tools/composer/composer-2.10.2.phar
  - tools/composer/composer-2.10.2.phar.sha256
  - .github/workflows/ci.yml
  - README.md
  - tests/Composer/ComposerPolicyGuardTest.php
  - tests/Composer/ComposerPolicyLivePackagistTest.php
findings:
  critical: 1
  warning: 0
  info: 0
  total: 1
status: superseded
superseded_by: 01-12-PLAN.md
superseded_at: 2026-07-24T00:00:00Z
superseded_note: "CR-01 (the only finding) was the app/tools route-audit blind spot. Plan 01-12 closed it; 01-VERIFICATION.md (2026-07-24, 7/7 passed) confirms the exact regression probe now exits 1 with source-provenanced records. This review is retained for history only."
---

# Phase 01: Code Review Report

> **SUPERSEDED 2026-07-24** — CR-01 below was closed by Plan 01-12 and confirmed by `01-VERIFICATION.md` (7/7 truths passed). Kept for history; do not treat CR-01 as open.

**Reviewed:** 2026-07-23T15:45:00Z
**Depth:** deep
**Files Reviewed:** 9
**Status:** issues_found

## Summary

Plan 01-11 closes the prior `composer.json` script and `scripts/*.php` dispatch gaps: direct and `@composer` handlers now receive source-provenanced evidence, while unmodeled marker-bearing dispatches in supported `scripts/`/`bin/` PHP files fail closed. The CI policy gate is still before installation; the guard, manifest, PHAR digest/provenance, Laravel/Core bounds, and root no-lock/no-vendor boundary are unchanged and validate.

One fail-open remains. The new PHP source fallback is conditioned on the narrow `isSupportedProductionRoute()` list. Any marker-bearing unmodeled PHP dispatch in a normal tracked PHP source path outside that list is neither an `unknown-source` nor a supported route, so it produces no record and the production audit passes.

## Narrative Findings (AI reviewer)

## Critical Issues

### CR-01: Unmodeled Composer dispatch in ordinary tracked PHP paths bypasses the route audit

**File:** `tests/Composer/ComposerPolicyGuardTest.php:1425-1435, 2407-2416, 2476-2480`
**Classification:** BLOCKER

**Issue:** `routeSourceKind()` classifies every PHP file as `php`, so a tracked file such as `tools/indirect.php` does not take the unknown-source fallback. `phpProcessLaunches()` does not model `popen()`, indirect callables, or variable functions. The only safety-net record is then gated by `isSupportedProductionRoute($path)`, which excludes `tools/` and `app/`. Consequently a tracked `tools/indirect.php` containing `popen("composer install", "r");` executes/represents a direct Composer mutation but returns zero records and zero failures.

**Reproduction:** Create and stage a disposable repository containing the current guard test/contract, a normal guarded CI workflow and README command, and `tools/indirect.php` with the code above. Run:

```sh
php tests/Composer/ComposerPolicyGuardTest.php --route-audit
```

It exits 0 and prints only the two guarded CI/README records; no record references `tools/indirect.php`. This is a source-level bypass, not fixture execution.

**Fix:** Apply the no-record PHP fallback to every tracked production PHP file after excluding only explicit trusted policy/test/planning artifacts, or classify non-allowlisted PHP paths as `unknown-source` for this purpose. Keep the fallback conditioned on a code-token-aware Composer/evaluator/process marker to avoid comment-only false evidence. Add staged regression fixtures for at least `tools/indirect.php` and `app/IndirectComposer.php` using `popen()` and a variable/callable dispatch, requiring an `unclassified-php` record and a nonempty `routeAuditFailures()` result.

---

_Reviewed: 2026-07-23T15:45:00Z_
_Reviewer: the agent (gsd-code-reviewer)_
_Depth: deep_
