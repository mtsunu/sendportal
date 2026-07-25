# SendPortal PHP 8.4 Compatibility

## What This Is

SendPortal is a self-hosted email-marketing application built as a Laravel 11 host around the `mettle/sendportal-core` package. As of v1.0 (shipped 2026-07-25) the existing application is installable and operational on PHP 8.4: Composer constraints are resolved, a reviewed lockfile is committed, dependency-security controls are preserved, and the runtime plus automated checks are verified by a live `:8.4` CI job.

## Core Value

Operators can install and run SendPortal reliably on PHP 8.4 without bypassing dependency or platform requirements.

## Requirements

### Validated

- ✓ SendPortal provides tenant-scoped newsletter, subscriber, campaign, reporting, webhook, and public/API capabilities through `mettle/sendportal-core` — existing
- ✓ The host application provides authentication, workspace membership, invitation, API-token, and setup flows — existing
- ✓ The application runs on Laravel 11 with Livewire, Horizon, PHPUnit, and Composer-managed PHP dependencies — existing
- ✓ Standard `composer install` resolves and installs on PHP 8.4 without platform ignore/emulation flags — v1.0
- ✓ Composer metadata declares PHP 8.2/8.3/8.4 support without suppressing platform checks (`require.php ^8.2`) — v1.0
- ✓ Dependency-security controls preserved — Roave replaced by a Composer-native blocking/audit policy with three owner-approved, time-bounded advisory IDs — v1.0
- ✓ A reviewed, drift-free `composer.lock` is committed and is the standard install contract for local/CI/deploy — v1.0
- ✓ PHP 8.4 runtime, SendPortal Core integration, and both DB PHPUnit paths verified by a live `:8.4` CI job — v1.0

### Active

_None — v1.0 shipped. Next milestone candidates staged from v2 requirements:_

- [ ] HARD-01: CI records a concise dependency-upgrade evidence summary (PHP/Composer versions, audit result, DB-matrix outcomes).
- [ ] HARD-02: A minimal tenant-safe SendPortal Core behavior smoke test covers one representative package flow under PHP 8.4.
- [ ] HARD-03: Repair static analysis and application coverage configuration in a separately scoped quality milestone.

### Out of Scope

- New product features or UI redesign — the milestone is limited to runtime compatibility and installation reliability.
- Unrelated architecture, authorization, or setup-flow refactors — these should not be bundled into a dependency upgrade.
- Bypassing platform requirements with Composer ignore flags — this would conceal compatibility defects instead of resolving them.

## Context

- **Shipped v1.0 (2026-07-25):** 3 phases, 15 plans, 27 tasks. All 13 v1 requirements satisfied.
- The manifest declares PHP `^8.2` (permissive floor), Laravel `^11.0`, and `mettle/sendportal-core` `^3.0`; Roave is removed in favor of a Composer-native blocking/audit policy fronted by `bin/composer-policy` with three owner-approved, time-bounded advisory IDs.
- A reviewed `composer.lock` (content-hash `41abd56c5581800607cc9d3c28862a76`) is committed and drift-free; it resolves Laravel v11.55.0 with SendPortal Core v3.0.2 under real PHP 8.4.23.
- CI adds a live `:8.4` matrix job with script-enabled install, five independently-attributable gate steps (metadata/platform/audit/boot/Core-route), and both MySQL and PostgreSQL PHPUnit suites.
- **Known caveat / tech debt:** the committed lock is effectively PHP 8.4-only (Symfony 8.1 components require `php >=8.4.1`), so the matrix is scoped to `:8.4`; `require.php` is left `^8.2` to keep the guard manifest and lockfile byte-unchanged. Original CI `:8.2`/`:8.3` jobs will not install against this lock.
- The application's primary behavior depends on `mettle/sendportal-core`; dependency changes require integration-aware verification.

## Constraints

- **Runtime**: PHP 8.4 must be a supported installation target — this is the stated project outcome.
- **Dependency safety**: Do not disable Composer platform checks or silently drop vulnerability protection — installation must remain trustworthy.
- **Compatibility**: Preserve existing application behavior and Laravel 11/SendPortal Core integration — this is a focused compatibility milestone.
- **Reproducibility**: Commit `composer.lock` once a valid graph is resolved — unpinned fresh resolution currently creates machine-to-machine drift.

## Key Decisions

| Decision | Rationale | Outcome |
|----------|-----------|---------|
| Target PHP 8.4 as a first-class supported runtime | The application currently cannot install under the requested runtime. | ✓ Good — live `:8.4` CI green (v1.0) |
| Resolve Composer constraints rather than ignore platform requirements | A successful install must represent a genuinely compatible dependency graph. | ✓ Good — install passes with no ignore/emulation flags (v1.0) |
| Retain or replace the security-advisory safeguard | The current `roave/security-advisories` constraint blocks Laravel 11 resolution, but dependency security coverage remains necessary. | ✓ Good — Roave replaced by Composer-native blocking/audit policy + 3 owner-approved advisory IDs (v1.0) |
| Commit a lockfile | The absent lockfile makes installation results non-reproducible. | ✓ Good — drift-free `composer.lock` committed and used by local/CI/deploy (v1.0) |
| Scope the matrix to `:8.4`, keep `require.php ^8.2` | Symfony 8.1 components require `php >=8.4.1`; narrowing the lock kept the guard manifest and lockfile byte-unchanged. | ⚠️ Revisit — `:8.2`/`:8.3` no longer install against this lock (documented tech debt) |

## Evolution

This document evolves at phase transitions and milestone boundaries.

**After each phase transition** (via `$gsd-transition`):
1. Requirements invalidated? → Move to Out of Scope with reason
2. Requirements validated? → Move to Validated with phase reference
3. New requirements emerged? → Add to Active
4. Decisions to log? → Add to Key Decisions
5. "What This Is" still accurate? → Update if drifted

**After each milestone** (via `$gsd-complete-milestone`):
1. Full review of all sections
2. Core Value check — still the right priority?
3. Audit Out of Scope — reasons still valid?
4. Update Context with current state

---
*Last updated: 2026-07-25 after v1.0 milestone*
