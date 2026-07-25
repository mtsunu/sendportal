---
phase: 01-constraint-resolution-and-security-control
plan: 02
subsystem: dependency-resolution
tags: [composer, php-8.4, laravel-11, sendportal-core, security-policy, audit]
requires:
  - phase: 01-01
    provides: "Isolated PHP 8.4 solver evidence for the exact D-02 advisory exception."
provides:
  - "PHP ^8.2 manifest contract covering PHP 8.2 through 8.4 without platform emulation."
  - "Composer 2.10 native blocking/audit policy with only the three owner-approved, time-bounded advisory exceptions."
  - "Fresh-home PHP 8.4 install and configured/ignore-free audit evidence for a stable Laravel 11 graph."
affects:
  - "Phase 2 lockfile review"
  - "Phase 3 PHP 8.4 CI and runtime validation"
tech-stack:
  added: []
  patterns:
    - "Use a fresh COMPOSER_HOME and explicitly scrub policy/platform override variables for dependency-resolution evidence."
    - "Prove residual-risk exceptions with both a passing configured audit and an ignore-free JSON audit parsed to an exact approved ID set."
key-files:
  created:
    - ".planning/phases/01-constraint-resolution-and-security-control/01-02-SUMMARY.md"
  modified:
    - "composer.json"
key-decisions:
  - "Declared the PHP runtime contract as ^8.2 while retaining Laravel ^11.0 and SendPortal Core ^3.0."
  - "Replaced Roave with Composer 2.10 native advisory blocking/audit policy and exactly the three D-02 residual-risk IDs."
  - "Recorded reporting-audit exit status 1 as expected evidence when unsuppressed advisories are found, after exact-ID JSON validation."
patterns-established:
  - "Do not use config.platform, legacy audit configuration, platform-ignore flags, or policy-bypass flags to establish PHP compatibility."
requirements-completed: [COMP-01, COMP-02, COMP-03]
coverage:
  - id: D1
    description: "The root manifest accurately declares PHP 8.2–8.4 support and preserves the approved narrow security-policy boundary."
    requirement: COMP-02
    verification:
      - kind: other
        ref: "Herd Composer 2.10.2 validate --strict plus exact JSON policy assertion"
        status: pass
    human_judgment: false
  - id: D2
    description: "An isolated PHP 8.4 install resolves a tagged stable Laravel 11 graph without inherited configuration or bypasses."
    requirement: COMP-01
    verification:
      - kind: integration
        ref: "fresh-COMPOSER_HOME Herd Composer install --prefer-dist --no-interaction"
        status: pass
    human_judgment: false
  - id: D3
    description: "Configured and ignore-free locked audits establish that only the three D-02 advisory IDs are excepted."
    requirement: COMP-03
    verification:
      - kind: integration
        ref: "configured audit --locked plus ignore-free audit --locked --format=json exact-ID parser"
        status: pass
    human_judgment: false
duration: 7min
completed: 2026-07-22
status: complete
---

# Phase 01 Plan 02: PHP Support and Native Advisory Policy Summary

**PHP ^8.2 support metadata and a three-ID Composer-native advisory exception are proven by a clean PHP 8.4.23 installation of Laravel v11.55.0 with SendPortal Core v3.0.2.**

## Performance

- **Duration:** 7 min
- **Started:** 2026-07-22T16:03:00Z
- **Completed:** 2026-07-22T16:09:34Z
- **Tasks:** 2
- **Files modified:** 2

## Accomplishments

- Replaced the redundant PHP expression with `^8.2`, retained `laravel/framework: ^11.0` and `mettle/sendportal-core: ^3.0`, and removed `roave/security-advisories`.
- Added Composer 2.10 native `config.policy.advisories` with `block: true`, `audit: fail`, and exactly the three D-02 IDs, each documented as internal-only owner-accepted residual risk that expires at Phase 2 review or an eligible stable Core/Laravel upgrade.
- In a copied application with a fresh empty `COMPOSER_HOME` and all policy/platform override variables removed, completed a normal PHP 8.4.23 installation, a passing configured locked audit, and an ignore-free JSON audit reporting exactly the approved IDs.

## Isolated PHP 8.4 Evidence

The evidence used `/Users/meigire/Library/Application Support/Herd/bin/composer` (Composer 2.10.2) under PHP 8.4.23. The temporary app excluded `.git`, `vendor`, and `composer.lock`; no repository lockfile or vendor directory was created. Every Composer invocation unset `COMPOSER`, `COMPOSER_HOME`, `COMPOSER_POLICY`, `COMPOSER_NO_BLOCKING`, `COMPOSER_NO_SECURITY_BLOCKING`, `COMPOSER_IGNORE_PLATFORM_REQ`, and `COMPOSER_IGNORE_PLATFORM_REQS`, then set only the empty temporary `COMPOSER_HOME`.

| Check | Result |
| --- | --- |
| `composer validate --strict --no-check-publish` | Passed for the repository and copied manifest. |
| `composer install --prefer-dist --no-interaction` | Passed in the copied app on actual PHP 8.4.23. |
| Resolved framework | `laravel/framework v11.55.0`; tagged stable 11.x, not `11.x-dev`. |
| Resolved Core | `mettle/sendportal-core v3.0.2`. |
| Configured `composer audit --locked` | Passed with `block: true` / `audit: fail`; Composer listed the three documented exceptions as ignored. |
| Ignore-free `composer audit --locked --format=json` | JSON parser found exactly `PKSA-3r5d-mb8f-1qw9`, `PKSA-m5cs-t1y6-qpcs`, and `PKSA-mdq4-51ck-6kdq`; it returned exit status 1 because those advisories were intentionally made reportable. |

The manifest assertions confirmed both repository and copied manifests have no `config.platform`, legacy `config.audit.advisories`/`config.audit.ignore`, `policy.advisories.ignore`, package-wide ignore, or severity-wide ignore surface. No platform-ignore flags, policy-bypass flags, broad ignore settings, or development-branch Laravel constraint were used.

## Task Commits

1. **Task 1: Commit the exact approved Composer support and advisory-policy contract** — `3bb36b5` (`chore`)
2. **Task 2: Prove the isolated PHP 8.4 graph and its exact advisory boundary** — evidence-only; its recorded output is captured in this plan metadata commit because it intentionally leaves repository source files unchanged.

## Files Created/Modified

- `composer.json` — PHP contract and Composer-native advisory policy.
- `.planning/phases/01-constraint-resolution-and-security-control/01-02-SUMMARY.md` — isolated installation and audit evidence for Phase 2 review.

## Decisions Made

- Kept all unrelated bounds, scripts, stability configuration, and host source unchanged; the secure PHP 8.4 candidate did not require a Laravel major upgrade or Core fork.
- Used a reporting audit with its expected nonzero exit status only for the separate ignore-free evidence copy; the committed manifest remains blocking and audit-failing for every advisory outside the exact three-ID exception.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Verification bug] Treated the ignore-free reporting audit's nonzero status as expected evidence**
- **Found during:** Task 2
- **Issue:** After removing the three exceptions and changing the temporary-only policy to `audit: report`, Composer emitted valid JSON with the approved advisories but exited 1 because advisories existed. The plan's `zsh -e` command would stop before its required exact-ID parser.
- **Fix:** Captured the reporting audit exit status, required it to be `1`, and ran the exact-ID JSON parser. The configured committed-policy audit still had to pass normally.
- **Files modified:** None; this correction existed only in the removed evidence directory.
- **Verification:** The parser returned exactly the three approved IDs and the reporting audit exit status was `1`.
- **Committed in:** Plan metadata commit.

---

**Total deviations:** 1 auto-fixed (1 Rule 1 verification bug).
**Impact on plan:** The correction made the negative security-evidence control executable without weakening policy, ignores, or platform checks.

## Issues Encountered

- The sandbox initially could not resolve Packagist. The user-authorized network-enabled isolated run completed successfully; no fallback or offline mode was used.

## Known Stubs

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Phase 2 can intentionally create and review the first committed `composer.lock` from the proven stable Laravel 11/Core graph.
- The lockfile review must retain the exact policy boundary, confirm the selected graph, and remove or reassess the time-bounded exceptions if a compatible upgrade path becomes available.

## Self-Check: PASSED

- `composer.json` exists, validates strictly, and has the exact approved policy assertion.
- Task commit `3bb36b5` exists.
- The repository still has no `composer.lock`.

---
*Phase: 01-constraint-resolution-and-security-control*
*Completed: 2026-07-22*
