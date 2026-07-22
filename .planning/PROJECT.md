# SendPortal PHP 8.4 Compatibility

## What This Is

SendPortal is a self-hosted email-marketing application built as a Laravel 11 host around the `mettle/sendportal-core` package. This project makes the existing application installable and operational on PHP 8.4 by resolving Composer constraints, updating compatible dependency bounds, and verifying the runtime and automated checks.

## Core Value

Operators can install and run SendPortal reliably on PHP 8.4 without bypassing dependency or platform requirements.

## Requirements

### Validated

- ✓ SendPortal provides tenant-scoped newsletter, subscriber, campaign, reporting, webhook, and public/API capabilities through `mettle/sendportal-core` — existing
- ✓ The host application provides authentication, workspace membership, invitation, API-token, and setup flows — existing
- ✓ The application runs on Laravel 11 with Livewire, Horizon, PHPUnit, and Composer-managed PHP dependencies — existing

### Active

- [ ] Resolve the Composer dependency graph so a standard `composer install` succeeds on PHP 8.4.
- [ ] Update PHP and package constraints to express and preserve PHP 8.4 support without suppressing Composer platform checks.
- [ ] Keep dependency security checks or replace them with an equivalent compatible safeguard.
- [ ] Verify the application and automated test suite operate successfully on PHP 8.4, and update continuous integration/runtime configuration as needed.
- [ ] Commit a reproducible dependency lockfile for consistent installations.

### Out of Scope

- New product features or UI redesign — the milestone is limited to runtime compatibility and installation reliability.
- Unrelated architecture, authorization, or setup-flow refactors — these should not be bundled into a dependency upgrade.
- Bypassing platform requirements with Composer ignore flags — this would conceal compatibility defects instead of resolving them.

## Context

- The current manifest declares PHP `^8.2|^8.3`, Laravel 11, and `mettle/sendportal-core` `^3.0` in `composer.json`.
- `composer.lock` is absent, so each installation resolves a fresh dependency graph.
- On PHP 8.4.23, Composer reaches Packagist but cannot resolve the graph because `roave/security-advisories` `dev-master` conflicts with `illuminate/mail` versions required by Laravel 11.
- Existing CI tests PHP 8.2 and 8.3 against MySQL and PostgreSQL in `.github/workflows/ci.yml`.
- The application’s primary behavior depends on `mettle/sendportal-core`; dependency changes require integration-aware verification.

## Constraints

- **Runtime**: PHP 8.4 must be a supported installation target — this is the stated project outcome.
- **Dependency safety**: Do not disable Composer platform checks or silently drop vulnerability protection — installation must remain trustworthy.
- **Compatibility**: Preserve existing application behavior and Laravel 11/SendPortal Core integration — this is a focused compatibility milestone.
- **Reproducibility**: Commit `composer.lock` once a valid graph is resolved — unpinned fresh resolution currently creates machine-to-machine drift.

## Key Decisions

| Decision | Rationale | Outcome |
|----------|-----------|---------|
| Target PHP 8.4 as a first-class supported runtime | The application currently cannot install under the requested runtime. | — Pending |
| Resolve Composer constraints rather than ignore platform requirements | A successful install must represent a genuinely compatible dependency graph. | — Pending |
| Retain or replace the security-advisory safeguard | The current `roave/security-advisories` constraint blocks Laravel 11 resolution, but dependency security coverage remains necessary. | — Pending |
| Commit a lockfile | The absent lockfile makes installation results non-reproducible. | — Pending |

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
*Last updated: 2026-07-22 after initialization*
