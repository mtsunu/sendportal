# SendPortal PHP 8.4 Compatibility

## What This Is

SendPortal is a self-hosted email-marketing application built as a Laravel 11 host around the `mettle/sendportal-core` package. As of v1.0 (shipped 2026-07-25) the existing application is installable and operational on PHP 8.4: Composer constraints are resolved, a reviewed lockfile is committed, dependency-security controls are preserved, and the runtime plus automated checks are verified by a live `:8.4` CI job. As of v1.1 (shipped 2026-07-25) campaign delivery via Amazon SES is proactively paced to the account's per-second `MaxSendRate` — coordinated cross-process across all Horizon workers via a host-level adapter override — with the two latent throttle-path bugs fixed.

## Core Value

Operators can install and run SendPortal reliably on PHP 8.4 without bypassing dependency or platform requirements.

## Current State

**Shipped v1.1 SES Sending Reliability (2026-07-25).** No active milestone — ready to plan the next one via `/gsd-new-milestone`.

Last two milestones both shipped 2026-07-25:
- **v1.0 PHP 8.4 Compatibility** — installable/operational on PHP 8.4; committed lock; live `:8.4` CI gate.
- **v1.1 SES Sending Reliability** — coordinated per-second SES pacing + 2 throttle-path bug fixes via host-level `ThrottledSesAdapter` override; 5/5 requirements satisfied.

**v1.1 verification carried 3 environment-only overrides** (deferred to CI/prod, not code gaps): CI MySQL DB suite, php-cs-fixer PSR-12 gate, and live-SES pacing observation. See `.planning/STATE.md` → Deferred Items.

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
- ✓ SES-01: SES campaign sending is proactively paced to the account's per-second `MaxSendRate`, coordinated cross-process (shared Redis key) across all Horizon workers — v1.1 (live-volume CloudWatch confirmation deferred to prod)
- ✓ SES-02: The per-second rate is sourced from SES `getSendQuota()['MaxSendRate']`, cached ~5 min with safe edge-value handling and single-flight refresh — v1.1
- ✓ SES-03: SES throttling detected by AWS error code (`Throttling`) and disambiguated — rate-exceeded retried, daily-quota-exceeded fails fast — v1.1
- ✓ SES-04: Retry exhaustion surfaces a named `SesSendThrottledException` (no `null`-return `TypeError`); Horizon `tries=3` is the single retry owner — v1.1
- ✓ SES-05: Pacing introduces no duplicate sends — all waiting happens before the SES call, block bounded `< 60s`, proven by fault-injection — v1.1

### Active

_No active milestone — plan the next one via `/gsd-new-milestone`._

_Deferred to a future quality/reliability milestone:_

- [ ] SES-06: Custom Redis-Lua token-bucket limiter to eliminate fixed-window edge bursts — only if the bundled `DurationLimiter` actually trips SES throttling under production load.
- [ ] SES-07: App-level idempotency marker beyond `sent_at` to dedupe a crash in the `send()`→`markSent()` gap.
- [ ] HARD-01: CI records a concise dependency-upgrade evidence summary (PHP/Composer versions, audit result, DB-matrix outcomes).
- [ ] HARD-02: A minimal tenant-safe SendPortal Core behavior smoke test covers one representative package flow under PHP 8.4.
- [ ] HARD-03: Repair static analysis and application coverage configuration in a separately scoped quality milestone.

### Out of Scope

- New product features or UI redesign — the milestone is limited to runtime compatibility and installation reliability.
- Unrelated architecture, authorization, or setup-flow refactors — these should not be bundled into a dependency upgrade.
- Bypassing platform requirements with Composer ignore flags — this would conceal compatibility defects instead of resolving them.

## Context

- **Shipped v1.1 (2026-07-25):** 1 phase, 1 plan, 7 tasks (+1,492/−27 LOC). All 5 SES requirements satisfied. New host code: `app/Mail/ThrottledSesAdapter.php`, `app/Mail/SesSendThrottledException.php`, `config/sendportal-throttle.php`, a 7-line `AppServiceProvider::boot()` rebind, and a 26-test SES suite under `tests/Feature/Ses/`. Zero `vendor/` edits; no new Composer dependency (uses Laravel's bundled `Redis::throttle()` `DurationLimiter`).
- **v1.1 verification is VERIFIED-WITH-CAVEATS:** 26 SES tests + 1 Unit test pass locally against live Redis; 3 checks are environment-only and deferred to CI/prod (full DB-backed PHPUnit suite needs CI MySQL, php-cs-fixer PSR-12 needs the CI Docker image, live-SES pacing needs AWS credentials).
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
| Ship SES pacing + bug fixes as a host-level adapter override (`ThrottledSesAdapter` rebound via `MailAdapterFactory::$adapterMap`) | Never edit `vendor/mettle/sendportal-core`; keep the dependency upgradable and the v1.0 install contract intact. | ✓ Good — v1.1 shipped with zero vendor edits |
| Use Laravel's bundled `Redis::throttle()` `DurationLimiter` over the shared connection as the cross-process pacing primitive | Zero new Composer dependency; genuinely cross-process via a shared Redis key. | ✓ Good — proven cross-process by a ≥2-worker test (v1.1); ⚠️ fixed-window edge burst possible → token-bucket (SES-06) deferred unless SES throttling is observed |
| Do all waiting BEFORE the SES call; bounded block `max_block_seconds=15 << timeout 60` | Prevents double-send between `send()` and `markSent()`; Horizon `tries=3` is the single retry owner. | ✓ Good — no-double-send proven by fault injection (SES-05, v1.1) |

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
*Last updated: 2026-07-25 after v1.1 milestone*
