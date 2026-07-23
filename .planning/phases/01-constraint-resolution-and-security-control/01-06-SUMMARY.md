---
phase: 01-constraint-resolution-and-security-control
plan: 06
subsystem: dependency-security
tags: [composer, php-8.4, packagist, policy-guard, route-audit]
requires:
  - phase: 01-05
    provides: repository-owned Composer 2.10.2 distribution and per-chain route audit
provides:
  - Canonical-checkout-only Composer subprocess execution with alternate working-directory rejection
  - Quote, escape, YAML, wrapper, and standalone-background-aware dependency-route audit
  - Fresh no-cache Packagist-backed PHP 8.4 resolver and install evidence
affects: [phase-02-lockfile, phase-03-runtime-validation, dependency-installation]
tech-stack:
  added: []
  patterns: [canonical process cwd, bounded shell-list parsing, isolated Composer homes, live Packagist evidence]
key-files:
  created: []
  modified:
    - bin/composer-policy
    - tests/Composer/ComposerPolicyGuardTest.php
key-decisions:
  - "Reject every Composer working-directory selector before the trusted PHAR's version or policy probes run."
  - "Merge Composer stdout and stderr at the guard boundary so verbose live resolver output cannot deadlock."
  - "Treat standalone unquoted ampersands and wrapper-prefixed commands as independently auditable route segments."
requirements-completed: [COMP-01, COMP-02, COMP-03]
coverage:
  - id: D1
    description: The guard always runs Composer at its canonical checkout root and rejects all alternate working-directory forms before PHAR execution.
    requirement: COMP-03
    verification:
      - kind: integration
        ref: php tests/Composer/ComposerPolicyGuardTest.php
        status: pass
    human_judgment: false
  - id: D2
    description: The route audit independently classifies standalone background lists, YAML scalars, quoting, escaping, and permitted wrapper forms.
    requirement: COMP-03
    verification:
      - kind: integration
        ref: php tests/Composer/ComposerPolicyGuardTest.php --route-audit
        status: pass
    human_judgment: false
  - id: D3
    description: Separate empty-cache PHP 8.4 checkouts resolve and install through the guard with direct Packagist metadata downloads.
    requirement: COMP-01
    verification:
      - kind: integration
        ref: Herd PHP 8.4.23 guarded update --dry-run and script-enabled install plus audit --locked
        status: pass
    human_judgment: false
metrics:
  duration: 1h 15m
  completed: 2026-07-23
status: complete
---

# Phase 01 Plan 06: Canonical Composer Boundary and Live PHP 8.4 Evidence

**The checked-in Composer policy guard is bound to its checkout, audits every executable command-list segment, and completes fresh Packagist-backed PHP 8.4 resolution and installation without cache fallback.**

## Accomplishments

- Rejected `--working-dir`, `--working-dir=…`, `-d`, `-d=…`, and compact `-d…` before the trusted PHAR can run; all probes and delegation now use the guard's canonical repository root.
- Replaced the route audit's line-level parser with bounded quote/escape/YAML-aware command-list and wrapper parsing, including standalone `&` segmentation and isolated adversarial Git fixtures.
- Proved a resolver dry-run and separate script-enabled install/audit on Herd PHP 8.4.23, Composer 2.10.2, distinct empty Composer homes/caches, and global `--no-cache`.
- Recorded direct Packagist metadata downloads in both runs (807 markers each), with no cache-read, cache-fallback, disabled-network, or offline evidence.

## Task Commits

1. **Task 1 RED: repository-bound Composer regressions** — `daad281`
2. **Task 1 GREEN: canonical repository-root guard** — `389e478`
3. **Task 2 RED: shell-list and wrapper regressions** — `223b3cf`
4. **Task 2 GREEN: bounded quote/escape/YAML route audit** — `2c874fa`
5. **Task 3 deviation: verbose Composer output deadlock fix** — `f232ca3`

## Verification

- `php -l bin/composer-policy`, `php -l tests/Composer/ComposerPolicyGuardTest.php`, and the full dependency-free suite passed.
- `php tests/Composer/ComposerPolicyGuardTest.php --route-audit` passed with 11 classified tracked records; CI and README use the guard.
- PHP 8.4.23 fresh resolver and install runs each downloaded Packagist metadata directly, then passed their required command gates. The install's configured `audit --locked` reported only the three documented, owner-approved temporary advisory exceptions.
- The temporary resolved graph uses `laravel/framework v11.55.0` and `mettle/sendportal-core v3.0.2`.
- The main checkout retains no `composer.lock` or `vendor/`; `composer.json`, provenance/PHAR, CI, and README are unchanged, and the Composer PHAR SHA-256 still matches its strict record.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Blocking runtime bug] Prevented verbose Composer output deadlock**

- **Issue:** The guard drained stdout before stderr, allowing a `-vvv` Composer resolver to fill stderr and block.
- **Fix:** Redirected stderr into the same captured stream as stdout.
- **Verification:** Guard suite and route audit passed; both verbose Packagist runs completed successfully.
- **Committed in:** `f232ca3`

## Issues Encountered

Sandbox DNS initially failed and egress required explicit approval. After approval, the two clean no-cache Packagist runs completed without using cached, offline, historical, or local-only evidence.

## Self-Check: PASSED

- Confirmed Task 1/2 commits and the deadlock fix commit exist.
- Confirmed direct Packagist download evidence and stable Laravel/Core versions in separate temporary PHP 8.4 checkouts.
- Confirmed the main checkout is still free of a lockfile and vendor tree.

---
*Phase: 01-constraint-resolution-and-security-control*
*Completed: 2026-07-23*
