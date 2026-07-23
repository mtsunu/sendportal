---
phase: 01-constraint-resolution-and-security-control
plan: 07
subsystem: dependency-security
tags: [composer, php-8.4, policy-guard, packagist, process-io, route-audit]
requires:
  - phase: 01-06
    provides: canonical checkout guard, bounded initial route audit, and prior live PHP 8.4 evidence
provides:
  - Caller-isolated exact Composer policy enforcement with a four-command allowlist
  - Fail-closed workflow, shell, alias, wrapper, and PHP process-route audit
  - Channel-preserving Composer delegation with bounded concurrent preflight capture
  - Repeatable two-checkout no-cache PHP 8.4 live Packagist verification
affects: [phase-02-lockfile, phase-03-runtime-validation, dependency-installation, supply-chain-security]
tech-stack:
  added: []
  patterns: [private Composer home, shared command contract, per-segment fail-closed audit, direct process descriptors, isolated live dependency proof]
key-files:
  created:
    - tools/composer/ComposerPolicyCommandContract.php
    - tests/Composer/ComposerPolicyLivePackagistTest.php
  modified:
    - bin/composer-policy
    - tests/Composer/ComposerPolicyGuardTest.php
key-decisions:
  - "Allow only canonical validate, audit, install, and update commands through the guard; aliases, selectors, and all unreviewed commands fail before the PHAR starts."
  - "Replace caller COMPOSER_HOME with a private mode-0700 home for every Composer child while preserving credentials only through COMPOSER_AUTH."
  - "Capture only preflight probes with concurrent bounded pipes; delegate Composer through direct matching stdin/stdout/stderr descriptors."
  - "Treat every supported route segment independently and fail closed when bounded workflow, shell, or PHP parsing cannot classify Composer-bearing execution text."
patterns-established:
  - "Exact-policy reassertion: validate D-01 through D-03 before probes and again immediately before install/update delegation."
  - "Fresh compatibility gate: two independent empty home/cache checkouts, global --no-cache, direct Packagist markers, and no root dependency artifacts."
requirements-completed: [COMP-01, COMP-02, COMP-03]
coverage:
  - id: D1
    description: Hostile environment, global Composer configuration, commands, selectors, and manifest drift cannot weaken the exact advisory policy.
    requirement: COMP-03
    verification:
      - kind: integration
        ref: php tests/Composer/ComposerPolicyGuardTest.php --group=effective-policy-command-contract
        status: pass
    human_judgment: false
  - id: D2
    description: Supported workflow, shell, wrapper, alias, and PHP process routes are classified per invocation and fail closed outside the bounded grammar.
    requirement: COMP-03
    verification:
      - kind: integration
        ref: php tests/Composer/ComposerPolicyGuardTest.php --group=route-audit-fail-closed
        status: pass
      - kind: integration
        ref: php tests/Composer/ComposerPolicyGuardTest.php --route-audit
        status: pass
    human_judgment: false
  - id: D3
    description: Composer delegation streams separate channels with exact status while probes and the test harness remain bounded and deadlock-safe.
    requirement: COMP-03
    verification:
      - kind: integration
        ref: php tests/Composer/ComposerPolicyGuardTest.php --group=process-io
        status: pass
    human_judgment: false
  - id: D4
    description: Fresh PHP 8.4 resolution and script-enabled installation succeed through the hardened guard with configured audit and no cache fallback.
    requirement: COMP-01
    verification:
      - kind: integration
        ref: Herd PHP 8.4 tests/Composer/ComposerPolicyLivePackagistTest.php
        status: pass
    human_judgment: false
metrics:
  duration: 1h 42m
  completed: 2026-07-23
status: complete
---

# Phase 01 Plan 07: Hardened Composer Policy Boundary Summary

**Composer 2.10.2 now runs behind an isolated exact-policy and four-command boundary, with per-segment fail-closed route auditing, channel-preserving delegation, and repeatable fresh PHP 8.4 Packagist proof.**

## Performance

- **Duration:** 1h 42m
- **Started:** 2026-07-23T05:22:03Z
- **Completed:** 2026-07-23T07:03:53Z
- **Tasks:** 3
- **Files modified:** 4

## Accomplishments

- Isolated every guarded Composer child from hostile caller/global configuration, preserved only `COMPOSER_AUTH`, rejected all pinned policy overrides, and enforced exact D-01 through D-03 before resolver execution.
- Shared one dependency-free command contract between the guard and route audit; only `validate`, `audit`, `install`, and `update` are allowed.
- Closed workflow scalar, shell control/subshell/timeout, alias, wrapper, literal PHP, dynamic PHP, README, and sibling-segment audit gaps without claiming a generic YAML/shell/PHP parser.
- Split bounded concurrent preflight capture from direct delegated descriptors; live-before-exit, separate-channel, 1 MiB digest, exact status 37, timeout, cap, and pipe-order regressions pass.
- Re-proved the final hardened path twice under PHP 8.4.23 using fresh Packagist metadata. The final run recorded 807 direct metadata markers in each independent checkout, Laravel `v11.55.0`, Core `v3.0.2`, and a passing configured audit.

## Task Commits

Each task was committed with its TDD RED/GREEN boundary:

1. **Task 1 RED: effective-policy and command-contract regressions** — `f0f5fbb`
2. **Task 1 GREEN: exact isolated Composer policy boundary** — `be020c6`
3. **Task 2 RED: fail-closed route-form regressions** — `6e259cd`
4. **Task 2 GREEN: workflow, shell, alias, wrapper, and PHP route audit** — `9b681fe`
5. **Task 3 RED: streaming, channel, preflight, and deadlock regressions** — `45c30d4`
6. **Task 3 GREEN: process I/O contract and live PHP 8.4 integration gate** — `50cf7dc`
7. **Independent-review fixes: per-segment fallback and immediate policy reassertion** — `7b0f92e`

## Files Created/Modified

- `bin/composer-policy` — Isolated child environment, exact manifest preflight, bounded concurrent probes, and direct channel-preserving delegation.
- `tools/composer/ComposerPolicyCommandContract.php` — Shared canonical command, alias, selector, and bounded global-option contract.
- `tests/Composer/ComposerPolicyGuardTest.php` — Dependency-free adversarial coverage for policy, routes, process channels, status, bounds, and deadlock safety.
- `tests/Composer/ComposerPolicyLivePackagistTest.php` — Mandatory two-checkout PHP 8.4 live resolver/install/audit gate.

## Decisions Made

- Canonical Composer commands are deliberately limited to `validate`, `audit`, `install`, and `update`; no generic Composer CLI parser is exposed.
- The guard owns a fresh private `COMPOSER_HOME` per invocation and never copies machine-global `config.json` or `auth.json`.
- Resolver policy is checked both before Composer probes and immediately after probes before delegation to prevent a mutation race.
- Production route evidence is per executable segment; any Composer-bearing supported segment outside the bounded grammar is a failing unclassified record.
- Phase 2 retains root lockfile and advisory-expiry ownership, and Phase 3 retains the PHP 8.4 CI matrix.

## Validation Evidence

- All four changed/created PHP files pass `php -l`.
- All named groups pass:
  - `effective-policy-command-contract`
  - `route-audit-fail-closed`
  - `process-io`
- The complete dependency-free suite passes.
- Production route audit passes with exactly three supported records: CI install and README install/update.
- Final live gate: PHP `8.4.23`, Composer `2.10.2`, 807 resolver markers, 807 install markers, Laravel `v11.55.0`, Core `v3.0.2`, configured audit pass, and two empty caller home/cache sets.
- Main checkout has no `composer.lock` or `vendor/`; `composer.json`, Composer PHAR/provenance, CI, and README remain unchanged.
- Independent `sol-specialist` review returned PASS after the review-driven fixes.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Security bug] Closed independent-review route and manifest-race gaps**

- **Found during:** Independent review after Task 3
- **Issue:** Literal workflow newlines could hide a direct sibling command; unknown Composer commands could disappear on supported README/shell routes; the manifest was not reasserted after probes.
- **Fix:** Added newline command boundaries, per-segment fail-closed fallback through one shared supported-route predicate including README, and a second exact-policy assertion immediately before resolver delegation.
- **Files modified:** `bin/composer-policy`, `tests/Composer/ComposerPolicyGuardTest.php`
- **Verification:** New literal/sibling/README and mutation-during-probe regressions pass; full suite and production route audit pass; specialist re-review returned PASS.
- **Committed in:** `7b0f92e`

---

**Total deviations:** 1 auto-fixed security issue (Rule 1).
**Impact on plan:** The fixes strengthen the planned trust boundaries without expanding into Phase 2 lockfile or Phase 3 CI work.

## Issues Encountered

- Sandbox DNS initially blocked the Packagist reachability probe. The authorized egress path succeeded; both mandatory live runs then completed against fresh Packagist metadata. No cache, offline, or historical evidence was substituted.
- The named groups execute with shared security prerequisites rather than isolated fixtures; arguments are validated and each named group reports its scope. The complete suite is also run separately.

## Known Stubs

None.

## User Setup Required

None.

## Next Phase Readiness

- COMP-01 through COMP-03 now have current final-guard evidence and are ready for phase verification.
- Phase 2 may create and review the root lockfile and resolve the three temporary advisory exceptions at their declared expiry gate.
- Phase 3 still owns PHP 8.4 CI/runtime-matrix coverage; no CI matrix or application behavior changed here.

## Self-Check: PASSED

- Confirmed all four owned artifacts exist.
- Confirmed all seven implementation/test commits exist.
- Confirmed fresh live PHP 8.4 evidence and stable Laravel/Core versions.
- Confirmed protected manifest/PHAR/provenance/CI/README files are unchanged.
- Confirmed the main checkout contains no `composer.lock` or `vendor/`.

---
*Phase: 01-constraint-resolution-and-security-control*
*Completed: 2026-07-23*
