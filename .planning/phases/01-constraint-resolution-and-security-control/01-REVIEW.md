---
phase: 01-constraint-resolution-and-security-control
reviewed: 2026-07-22T16:15:25Z
depth: standard
files_reviewed: 1
files_reviewed_list:
  - composer.json
findings:
  critical: 1
  warning: 1
  info: 0
  total: 2
status: issues_found
---

# Phase 01: Code Review Report

**Reviewed:** 2026-07-22T16:15:25Z
**Depth:** standard
**Files Reviewed:** 1
**Status:** issues_found

## Summary

The committed diff correctly changes the PHP contract to `^8.2`, preserves the Laravel `^11.0` and SendPortal Core `^3.0` bounds, removes Roave, and limits `ignore-id` to the three approved PKSA identifiers. However, the new Composer-native policy still inherits Composer 2.10's fail-open handling of unreachable advisory repositories during install and update. The documented residual-risk expiry is also prose only, so the approved exceptions can persist after their Phase 2 review point with no repository-enforced check.

## Narrative Findings (AI reviewer)

## Critical Issues

### CR-01: Advisory blocking fails open when advisory data is unreachable

**Classification:** BLOCKER
**File:** `/Users/meigire/Work/idai-jatim/sendportal/composer.json:30`
**Issue:** The `policy` object does not set `ignore-unreachable`. Composer 2.10 defaults this setting to `["update", "install"]`, meaning an unreachable repository/advisory source is ignored during the exact operations intended to prevent installing vulnerable versions. `block: true` then evaluates incomplete advisory data and can permit a vulnerable package, contradicting the phase's no-bypass and blocking-security-control requirements.
**Fix:** Make dependency resolution fail closed by adding the explicit policy setting alongside `advisories`:

```json
"policy": {
    "ignore-unreachable": false,
    "advisories": {
        "block": true,
        "audit": "fail"
    }
}
```

Re-run the clean PHP 8.4 resolution with an intentionally unreachable advisory repository/source assertion to confirm it exits non-zero rather than continuing with incomplete policy data.

## Warnings

### WR-01: The approved advisory exceptions have no enforceable expiry

**Classification:** WARNING
**File:** `/Users/meigire/Work/idai-jatim/sendportal/composer.json:35`
**Issue:** Each exception says it expires at Phase 2 lockfile review, but `ignore-id` accepts the string as explanation only; Composer will continue suppressing the IDs indefinitely until someone manually removes them. Nothing in the repository makes a post-Phase-2 lockfile or CI run fail while these exceptions remain, so the accepted risk can silently become permanent.
**Fix:** In Phase 2, add a checked policy-validation command that fails when `composer.lock` exists and these temporary IDs have not been removed or explicitly re-approved. Keep the three IDs as the only allowed temporary entries and remove them once the compatible upgrade is available.

---

_Reviewed: 2026-07-22T16:15:25Z_
_Reviewer: the agent (gsd-code-reviewer)_
_Depth: standard_
