# Milestones

## v1.1 SES Sending Reliability (Shipped: 2026-07-25)

**Phases completed:** 1 phase, 1 plan, 7 tasks
**Delivered:** Coordinated, Redis-backed per-second SES send pacing across all Horizon workers, plus fixes for the throttle-code misclassification and `null`-return `TypeError` — as a host-level `ThrottledSesAdapter` override (no `vendor/` edits, no new dependency). All 5 requirements (SES-01..05) satisfied; 26 SES tests + 1 Unit test green against live Redis.
**Requirements:** 5/5 v1.1 requirements complete (SES-01..05).
**Git range:** `c1b35fe` → `21c7414` (11 commits, TDD red/green) · +1,492/−27 across 16 files.

**Key accomplishments:**

- Cross-process SES per-second pacing plus the two throttle-path bug fixes shipped entirely as a host-level `ThrottledSesAdapter` (Redis `DurationLimiter`, code-gated throttle classifier, named exhaustion exception) with zero `vendor/` edits and no new Composer dependency.

**Closeout type:** override_closeout — Phase 04 verification is `human_needed` (VERIFIED-WITH-CAVEATS, 5/5 criteria). Known verification overrides: 3 (all environment-only, see STATE.md Deferred Items):
1. Full DB-backed PHPUnit suite (37 pre-existing tests) — needs CI MySQL; ERRORs locally on `Access denied` only, not code.
2. php-cs-fixer PSR-12 gate — needs CI Docker image; `php -l` passes, manual PSR-12 review done.
3. Live-SES pacing under real volume (SES-01 CloudWatch check) — needs live AWS SES credentials.

---

## v1.0 PHP 8.4 Compatibility (Shipped: 2026-07-25)

**Phases completed:** 3 phases, 15 plans, 27 tasks

**Key accomplishments:**

- A fresh-COMPOSER_HOME PHP 8.4.23 / Composer 2.10.2 probe resolved stable Laravel v11.55.0 under the exact three-ID native advisory policy without changing repository dependency artifacts.
- PHP ^8.2 support metadata and a three-ID Composer-native advisory exception are proven by a clean PHP 8.4.23 installation of Laravel v11.55.0 with SendPortal Core v3.0.2.
- Composer now fails closed for unreachable policy sources while a clean PHP 8.4.23 install and locked audit continue to resolve Laravel v11.55.0 with SendPortal Core v3.0.2.
- Composer dependency resolution now fails closed unless the PATH-selected Composer runs under PHP_BINARY, reports 2.10.0 or newer, and proves native-policy command support.
- Composer 2.10.2 is pinned as a repository-verified PHAR, and every tracked dependency mutation is audited per command-chain segment.
- The checked-in Composer policy guard is bound to its checkout, audits every executable command-list segment, and completes fresh Packagist-backed PHP 8.4 resolution and installation without cache fallback.
- Composer 2.10.2 now runs behind an isolated exact-policy and four-command boundary, with per-segment fail-closed route auditing, channel-preserving delegation, and repeatable fresh PHP 8.4 Packagist proof.
- Bounded quote-aware recursion closes the nested bash/sh/zsh/eval Composer-route bypass while retaining the shared guard contract.
- The Composer route audit now follows literal brace/function shell bodies and inline `php -r` process launches through the existing guarded-command contract, while unsupported forms fail with source evidence before CI installation.
- Finite source-provenanced route auditing now closes marker-bearing Composer bypasses across workflow aliases, shell controls/wrappers, PHP launches, Docker instructions, and unknown tracked sources.
- Finite root Composer-script provenance and marker-bearing PHP no-record fallbacks now prevent direct dependency mutations from silently escaping the route audit.
- Tracked application and tool PHP can no longer represent a Composer-bearing indirect dispatch while yielding zero route-audit evidence.
- Froze the Phase-1-approved PHP 8.4 dependency graph into a git-tracked composer.lock, proven drift-free (content-hash 41abd56c5581800607cc9d3c28862a76) and json<->lock synchronized, consumed by a guarded install without re-resolution.
- Resolved the expiring Phase-1 Laravel advisory exception on the RETAIN branch — three PKSA IDs re-justified against locked laravel/framework v11.55.0 with a real forward expiry and the guard $rationale in lockstep — captured the PHP 8.4 lockfile-review evidence (validate/platform/audit all EXIT 0), and documented the committed-lock install contract, all with zero graph drift.
- Extended `.github/workflows/ci.yml` with a `:8.4` matrix job, script-enabled install, and five independently-attributable CI-02 gate steps (manifest validation, platform requirements, dependency audit, Laravel/SendPortal-Core boot proof) — all verified locally against the real committed lockfile and real PHP 8.4.23.

---
