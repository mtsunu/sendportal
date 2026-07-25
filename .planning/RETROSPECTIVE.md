# Project Retrospective

*A living document updated after each milestone. Lessons feed forward into future planning.*

## Milestone: v1.0 — PHP 8.4 Compatibility

**Shipped:** 2026-07-25
**Phases:** 3 | **Plans:** 15 | **Tasks:** 27

### What Was Built
- Resolved the Composer graph so a standard `composer install` succeeds on real PHP 8.4.23 with no `--ignore-platform-req(s)` or platform emulation; `require.php` declares `^8.2` honestly.
- Replaced `roave/security-advisories` with a Composer-native blocking/audit policy fronted by `bin/composer-policy`, gating every tracked dependency mutation with per-segment fail-closed route auditing and three owner-approved, time-bounded advisory IDs.
- Froze the approved graph into a reviewed, drift-free `composer.lock` (content-hash `41abd56c5581800607cc9d3c28862a76`) resolving Laravel v11.55.0 + SendPortal Core v3.0.2, and made it the standard local/CI/deploy install contract.
- Added a live `:8.4` CI matrix job: script-enabled install, five independently-attributable gate steps (metadata validation, platform requirements, dependency audit, Laravel/SendPortal-Core boot + route-registration proof), and both MySQL and PostgreSQL PHPUnit suites.

### What Worked
- Sequencing constraint-resolution → lockfile freeze → runtime/CI proof kept each phase's evidence self-contained and verifiable against real PHP 8.4, not emulation.
- Adversarial re-verification paid off: Phase 1 went `gaps_found (6/7)` → `passed (7/7)` after Plan 01-12 closed the `app/`/`tools/` indirect-dispatch route-audit blind spot with a disposable-clone probe rather than the artifact's own fixtures.
- Guarded `update --lock` (freeze-only) caught that a full `update` would drift `aws/aws-sdk-php`, preserving a byte-stable lock.

### What Was Inefficient
- Phase 1 sprawled to 12 plans — much of it iteratively hardening the route-audit guard against successively more obscure Composer-bypass shapes (nested shells, `php -r`, Docker instructions, indirect PHP dispatch). Valuable, but the scope crept well beyond the headline "resolve constraints" goal.
- REQUIREMENTS.md traceability drifted: COMP-01/02/03 stayed `Gaps Found`/unchecked after Phase 1 re-verification flipped them to satisfied, and had to be reconciled at milestone close.

### Patterns Established
- All Composer mutations route through `bin/composer-policy` bound to a repository-pinned Composer 2.10.2 PHAR under `PHP_BINARY`; canonical commands only, fail-closed on unclassifiable execution text.
- Verification records an explicit, owner-accepted *bound* (e.g. T-01-12-03 literal-concatenation) rather than silently claiming total coverage — honest scoping over false completeness.

### Key Lessons
1. Keep the requirements traceability table in lockstep with re-verification outcomes — a phase flipping `gaps_found → passed` must update the linked REQ rows in the same cycle, or milestone close inherits stale gaps.
2. Narrowing a lockfile to satisfy a transitive floor (Symfony 8.1 → `php >=8.4.1`) is a real trade-off: it made the matrix `:8.4`-only while `require.php` still advertises `^8.2`. Documented as tech debt (⚠️ Revisit) rather than hidden.
3. Prove guard/security claims against a disposable clone, not the artifact's own fixtures — self-referential tests mask fail-open blind spots.

### Cost Observations
- Model mix: adaptive profile (Opus main loop; Sonnet/Haiku delegation for mechanical reads).
- Notable: Phase 1's 12-plan guard-hardening dominated effort; Phases 2-3 were tight (2 and 1 plan).

---

## Milestone: v1.1 — SES Sending Reliability

**Shipped:** 2026-07-25
**Phases:** 1 | **Plans:** 1

### What Was Built
- Cross-process, Redis-backed per-second SES send pacing coordinated across all Horizon `sendportal-message-dispatch` workers (≤20), plus fixes for the two latent throttle-path bugs (error-code misclassification; `null`-return `TypeError` on retry exhaustion) — shipped entirely as a host-level `ThrottledSesAdapter` rebound via `MailAdapterFactory::$adapterMap` in `AppServiceProvider::boot()`, with zero `vendor/` edits and no new Composer dependency.
- 26-test SES suite (`tests/Feature/Ses/`) covering cross-process pacing, rate-vs-daily-quota branch, named exhaustion exception, and no-double-send fault injection.

### What Worked
- Tracer-first TDD (red/green per requirement, SES-01..05) kept each requirement independently proven against **live Redis** rather than a mock, so the cross-process claim is real.
- Reusing Laravel's bundled `Redis::throttle()` `DurationLimiter` honored the "no new dependency / no vendor edits" constraint while still delivering genuine cross-process coordination via a shared key.
- The block-before-send invariant with a bounded `block(15) << timeout 60` made the no-double-send property provable by fault injection instead of argued.

### What Was Inefficient
- The SES-01 cross-process assertion was first written as a flaky per-timestamp `<=R` count and had to be reworked into a stable pigeonhole assertion (M sends occupy `>= ceil(M/R)` aligned integer-second windows) — a test-design lesson that cost a revision.
- Three of the phase's verification items are structurally un-runnable locally (CI MySQL, php-cs-fixer Docker image, live AWS SES), so the milestone closed as an override rather than a clean verified close — expected for a single-machine environment, but worth provisioning CI-equivalents earlier.

### Patterns Established
- **Host-level vendor override**: rebind a Core adapter via `MailAdapterFactory::$adapterMap` in `AppServiceProvider::boot()` to change dependency behavior without touching `vendor/` — keeps the package upgradable and the v1.0 install contract intact.
- **Prove concurrency claims against live Redis**, not a mock, and assert on stable structural invariants (window occupancy) rather than exact timestamps.

### Key Lessons
1. Test *properties*, not incidental timing: a pigeonhole "≥ N windows occupied" assertion is stable where a "≤ R per recorded timestamp" count is flaky.
2. Ship the simple correct primitive (fixed-window `DurationLimiter`) and explicitly defer the fancier one (Redis-Lua token bucket, SES-06) behind an observable trigger, rather than pre-building for a burst that may never occur.
3. Environment-only verification gaps are legitimate override-closeout items — record them as deferred (STATE.md) with the reason they can't run locally, don't let them block a code-complete milestone.

### Cost Observations
- Model mix: Opus main loop; Sonnet/Haiku delegation for mechanical reads.
- Sessions: 1 primary execution session (~17 min execution for the single plan).
- Notable: tight single-phase milestone — most effort was in test hardening, not implementation.

---

## Cross-Milestone Trends

### Process Evolution

| Milestone | Phases | Plans | Key Change |
|-----------|--------|-------|------------|
| v1.0 | 3 | 15 | Established Composer-policy guard + committed-lock install contract + live `:8.4` CI gate |
| v1.1 | 1 | 1 | Established host-level vendor-adapter override pattern; cross-process pacing proven against live Redis |

### Cumulative Quality

| Milestone | Requirements | Verified | Tech Debt Logged |
|-----------|--------------|----------|------------------|
| v1.0 | 13/13 v1 | All phases passed | `:8.4`-only lock; static-analysis/coverage repair deferred to v2 |
| v1.1 | 5/5 v1.1 | VERIFIED-WITH-CAVEATS (override close) | 3 env-only checks deferred to CI/prod; fixed-window edge burst (SES-06) & idempotency (SES-07) deferred |

### Top Lessons (Verified Across Milestones)

1. Honest scoping — record accepted bounds and tech debt explicitly instead of claiming total coverage.
2. Verify against independent probes/clones, not the artifact's own fixtures.
3. Test stable properties/invariants, not incidental timing or self-referential fixtures — and prove concurrency claims against the real backend (live Redis), not mocks.
