---
phase: 01-constraint-resolution-and-security-control
reviewed: 2026-07-22T17:03:14Z
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

**Reviewed:** 2026-07-22T17:03:14Z
**Depth:** standard
**Files Reviewed:** 1
**Status:** issues_found

## Summary

The manifest correctly declares the PHP 8.2–8.4 range with `^8.2`, retains the Laravel 11 and SendPortal Core constraints, removes Roave, and keeps the advisory exception surface to the exact three approved IDs. `config.policy.ignore-unreachable` is correctly set to the boolean `false`, matching Plan 01-03's fail-closed requirement. However, this safeguard is not enforced when operators use Composer earlier than 2.10: the locally available Composer 2.9.5 accepts the manifest but does not implement the `policy` command. The expiry of the three accepted advisories also remains explanatory text rather than an enforceable control.

## Narrative Findings (AI reviewer)

## Critical Issues

### CR-01: Composer versions before 2.10 can install without the replacement security policy

**Classification:** BLOCKER
**File:** `/Users/meigire/Work/idai-jatim/sendportal/composer.json:30`
**Issue:** `config.policy` is a Composer 2.10 feature, but the manifest has no companion enforcement at the normal install boundary. The repository's PATH Composer is 2.9.5: it validates this `composer.json` and prints the arbitrary `policy.*` values from configuration, yet `composer policy --help` fails with `Command "policy" is not defined.` Consequently, an operator or CI image using Composer 2.9.x will not apply `block`, `audit`, `ignore-id`, or `ignore-unreachable`; after Roave was removed, dependency resolution can proceed without the intended advisory safeguard. Plan 01-03 proves the policy only with a separately selected Composer 2.10.2 binary, which does not protect the normal installation path.
**Fix:** Require Composer 2.10 or newer before every CI, deployment, and documented operator `composer install`/`update` entry point, and fail before resolution when the requirement is not met. For example:

```sh
composer_version="$(composer --version | sed -n 's/^Composer version \([0-9.]*\).*/\1/p')"
php -r 'exit(version_compare($argv[1], "2.10.0", ">=") ? 0 : 1);' "$composer_version" \
  || { echo "Composer >= 2.10.0 is required for dependency policy enforcement." >&2; exit 1; }
composer install --prefer-dist --no-interaction
```

Until that guard is present on every supported installation route, retain a supported blocking safeguard for older Composer or make the Composer 2.10 prerequisite explicit and enforced before shipping.

## Warnings

### WR-01: The approved advisory exceptions have no enforceable expiry

**Classification:** WARNING
**File:** `/Users/meigire/Work/idai-jatim/sendportal/composer.json:36-38`
**Issue:** Each `ignore-id` reason says the exception expires at Phase 2 lockfile review, but Composer treats the value as documentation only. The three advisory IDs will remain ignored indefinitely until someone manually changes this manifest, so the accepted risk can silently become permanent after the stated expiry point.
**Fix:** In Phase 2, add a checked policy-validation step that fails when a lockfile exists and any of these temporary IDs remain without an explicit, recorded re-approval; remove the IDs once the compatible upgrade is available.

---

_Reviewed: 2026-07-22T17:03:14Z_
_Reviewer: the agent (gsd-code-reviewer)_
_Depth: standard_
