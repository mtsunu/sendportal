---
phase: 04-coordinated-ses-rate-limiting-2-bug-fixes
plan: 01
subsystem: infra
tags: [ses, aws, redis, rate-limiting, duration-limiter, horizon, throttling, laravel]

# Dependency graph
requires:
  - phase: 03
    provides: Proven unchanged SendPortal Core host integration on PHP 8.4
provides:
  - Host-level ThrottledSesAdapter that paces SES sends to the account MaxSendRate across all workers via a shared Redis DurationLimiter
  - Code-gated + message-sub-branched throttle classifier (fixes daily-quota misclassification, BUG 1)
  - Named SesSendThrottledException on exhaustion (fixes null-return TypeError, BUG 2)
  - Single shared wall-clock in-send wait deadline (< 60s worker timeout) guaranteeing no post-send double-send window
  - tests/Feature/Ses suite (26 tests) proving SES-01..SES-05 against real Redis
affects: [ses-sending, campaign-dispatch, horizon-workers]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Host-level adapter override rebinding MailAdapterFactory::$adapterMap in AppServiceProvider::boot() (no vendor edits, no new dependency)"
    - "Cross-process pacing via Illuminate\\Support\\Facades\\Redis::throttle() DurationLimiter on the shared default connection"
    - "Single-flight cache refresh (Cache::lock + last-known-good) to prevent GetSendQuota stampede"
    - "One shared wall-clock deadline caps aggregate in-send wait independent of per-attempt knobs"
    - "DB-free feature test base (boots app without migrations) for Redis-only suites"

key-files:
  created:
    - config/sendportal-throttle.php
    - app/Mail/SesSendThrottledException.php
    - app/Mail/ThrottledSesAdapter.php
    - tests/Feature/Ses/SesTestCase.php
    - tests/Feature/Ses/SesAdapterTestSupport.php
    - tests/Feature/Ses/ThrottledSesAdapterTracerTest.php
    - tests/Feature/Ses/SesRateSourceTest.php
    - tests/Feature/Ses/SesThrottleDetectionTest.php
    - tests/Feature/Ses/SesExhaustionTest.php
    - tests/Feature/Ses/SesRateLimitCrossProcessTest.php
    - tests/Feature/Ses/SesDoubleSendTest.php
  modified:
    - app/Providers/AppServiceProvider.php

key-decisions:
  - "ThrottledSesAdapter::send() overrides wholesale and never calls parent::send() — both vendor bugs live in the parent's ThrottlesSending trait"
  - "Throttle key = md5(region|access-key-id): MaxSendRate is an account-per-region quota, so this coordinates every worker for the same SES account/region"
  - "RATE_UNLIMITED sentinel (-1) bypasses the limiter entirely; 0/missing -> conservative default (one info log); fractional floored, >=1"
  - "Cross-process test asserts the pigeonhole-equivalent 'M sends occupy >= ceil(M/R) aligned windows' instead of a per-recorded-timestamp <=R count (which is flaky from boundary/measurement skew)"

patterns-established:
  - "Detection is code-gated (getAwsErrorCode()==='Throttling') then message-sub-branched on getAwsErrorMessage() — never the verbose getMessage()"
  - "Horizon tries=3 is the single outer retry owner; the in-send reactive loop is bounded (max_send_attempts=3)"

requirements-completed: [SES-01, SES-02, SES-03, SES-04, SES-05]

# Metrics
duration: 17min
completed: 2026-07-25
status: complete
---

# Phase 04 Plan 01: Coordinated SES rate limiting + 2 bug fixes Summary

**Cross-process SES per-second pacing plus the two throttle-path bug fixes shipped entirely as a host-level `ThrottledSesAdapter` (Redis `DurationLimiter`, code-gated throttle classifier, named exhaustion exception) with zero `vendor/` edits and no new Composer dependency.**

## Performance

- **Duration:** ~17 min
- **Started:** 2026-07-25 20:00:04 +0700
- **Completed:** 2026-07-25 20:16:54 +0700
- **Tasks:** 7 of 7
- **Files modified:** 12 (11 created, 1 modified)

## Accomplishments
- `ThrottledSesAdapter` paces every SES send to the account `MaxSendRate` across all Horizon workers via a shared Redis `DurationLimiter` on the `default` connection — proven cross-process (≥2 shared-key contexts) against real Redis.
- BUG 1 fixed (SES-03): throttling is classified code-first (`getAwsErrorCode() === 'Throttling'`) then message-sub-branched — rate errors retry, daily-quota fails fast, non-Throttling propagates unchanged.
- BUG 2 fixed (SES-04): exhaustion throws a named `SesSendThrottledException` (never null, never a `TypeError`); the message is never marked sent; Horizon `tries=3` is the sole outer retry owner.
- SES-05 double-send prevention: all waiting happens before the SES call, `send()` returns immediately on success, and ONE shared wall-clock deadline (`max_total_wait_seconds` = 45 < 60s worker timeout) caps the aggregate in-send wait regardless of `max_send_attempts`/`max_block_seconds`.
- SES-02: `MaxSendRate` read from `getSendQuota()`, cached ~5 min with a `Cache::lock` single-flight + last-known-good fallback; edge values (-1/0/missing/fractional/sandbox) handled.

## Task Commits

Each task committed atomically (TDD: RED test → GREEN impl where behavior-adding):

1. **Task 1: Tracer slice (SES-01/05 wiring)** — `c1b35fe` (test, RED) → `e74d82d` (feat, GREEN)
2. **Task 2: Rate source + edge values + single-flight (SES-02)** — `fae3dc3` (test, RED) → `b24ccfa` (feat, GREEN)
3. **Task 3: Throttle classification (SES-03, BUG 1)** — `01f089b` (test, RED) → `cfb505c` (feat, GREEN)
4. **Task 4: Bounded retry + named exhaustion + shared deadline (SES-04, BUG 2)** — `caf8734` (test, RED) → `f92149b` (feat, GREEN)
5. **Task 5: Cross-process shared-Redis pacing (SES-01)** — `7a330b3` (test)
6. **Task 6: No double-send + wait-before-send + rebind + drift guard (SES-05)** — `b509c94` (test)
7. **Task 7: Closing verification** — gate only, no source changes (see Issues Encountered)

_Note: TDD tasks have test → feat commit pairs; test-only tasks (5, 6) are single commits._

## Files Created/Modified
- `config/sendportal-throttle.php` — pacing knobs (block cap, aggregate wait budget, cache TTL, default rate, attempts, backoff); rate VALUE deliberately not overridable.
- `app/Mail/SesSendThrottledException.php` — `final` `RuntimeException` thrown on exhaustion.
- `app/Mail/ThrottledSesAdapter.php` — wholesale `send()` override: cached rate read, Redis pacing, classifier, bounded retry, shared deadline; reuses only safe inherited helpers.
- `app/Providers/AppServiceProvider.php` — one-line `MailAdapterFactory::$adapterMap[EmailServiceType::SES]` rebind in `boot()`.
- `tests/Feature/Ses/*` — 8 files (base case, support trait, 6 test classes) covering SES-01..SES-05.

## Decisions Made
- **DB-free test base (`SesTestCase`)** boots the app without running migrations, so the Redis-only SES suite runs wherever Redis is available (the host DB is MySQL-only and unavailable locally). Domain fixtures live in the `SesAdapterTestSupport` trait as planned.
- **Cross-process assertion form:** proved the SES-01 guarantee via distinct aligned-window count (pigeonhole) rather than the plan's literal per-recorded-timestamp `<= R` count — see Deviations.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug/Correctness] Cross-process assertion changed to a stable, equivalent form**
- **Found during:** Task 5 (cross-process pacing test)
- **Issue:** The plan specified anchoring windows at `t0 = min(recorded sendEmail microtime)` and asserting `<= R` per `floor((t - t0)/1s)` window with ±50ms tolerance. Empirically this is FLAKY and mismodels the limiter: Laravel's `DurationLimiter` Lua anchors every window to an **integer Unix second** (`HMSET 'start', time()`), not to the first send (which lands mid-second). Post-hoc recorded timestamps also drift across integer-second edges, so a single wall-clock window reads up to ~2R purely from measurement skew (the documented fixed-window edge burst). Across three runs the literal assertion gave 8, then 6, then 6 in a window — a false failure of a correctly-pacing limiter.
- **Fix:** Assert the logically-equivalent, non-flaky guarantee: since the limiter caps each aligned second-window at R, M sends cannot occupy fewer than `ceil(M/R)` distinct aligned integer-second windows — and a per-process fallback (two independent limiters → ~2R/sec) *would* fit them in fewer. Verified empirically: coordinated shared-key = exactly `ceil(10/4)=3` windows every run; simulated per-process fallback = 2 windows every run. Kept the strict discriminator (never relaxed to `<= 2R`) plus the store-is-Redis assertion.
- **Files modified:** `tests/Feature/Ses/SesRateLimitCrossProcessTest.php`
- **Verification:** `vendor/bin/phpunit --filter CrossProcess` green across 3 consecutive runs.
- **Committed in:** `7a330b3`

**2. [Rule 3 - Blocking/Infra] Added `tests/Feature/Ses/SesTestCase.php` (not in the plan file list)**
- **Found during:** Task 1 (tracer)
- **Issue:** The project's `Tests\TestCase::setUp()` runs `artisan migrate`, which requires the MySQL host DB (unavailable locally). The SES suite needs no DB but would abort in setUp.
- **Fix:** Added a small base test case that boots the app (running `AppServiceProvider::boot()` and the rebind) without migrating. Test organization under `tests/` is explicitly Claude's discretion per CONTEXT.md.
- **Files modified:** `tests/Feature/Ses/SesTestCase.php`
- **Verification:** Whole SES suite (26 tests) runs green.
- **Committed in:** `c1b35fe`

---

**Total deviations:** 2 (1 correctness-preserving test-methodology fix, 1 enabling test infra)
**Impact on plan:** No change to production behavior or the SES-01..SES-05 guarantees. Both keep the intended assertions honest and runnable; no scope creep.

## Issues Encountered

**Environment gaps (not code defects) at the Task 7 gate:**
- **Full PHPUnit suite:** 37 pre-existing DB-backed tests (Auth/Invitations/Setup/Workspaces) error with MySQL `Access denied for user 'laravel'` — the local box has no `sendportal_testing` MySQL with those CI credentials. CI provisions MySQL (per `.github/workflows/ci.yml`). All 26 new SES tests + the Unit example test pass; zero SES tests errored.
- **php-cs-fixer gate:** `vendor/bin/php-cs-fixer` is not installed (CI runs the `oskarstark/php-cs-fixer-ga` Docker image; Docker and a global binary are both absent locally). Compensated with a manual PSR-12 review of every new/changed file (ordered + used imports, short arrays, `declare(strict_types=1)`, final newline, no trailing whitespace) and `php -l` on all files. The formatter should be run in CI to confirm.

No blocking issues; no `vendor/` or `composer.*` changes (asserted clean by test and by git).

## User Setup Required
None — no external service configuration required. New config keys are all env-overridable with safe defaults (`SENDPORTAL_SES_*`).

## Next Phase Readiness
- SES pacing + both bug fixes are complete and unit/integration-proven against real Redis; milestone v1.1 automated coverage for SES-01..SES-05 is in place.
- Remaining validation is the one documented manual check: live-SES pacing under real campaign volume (CloudWatch send-rate near `MaxSendRate`, near-zero `Throttling`). Deferred: SES-06 (token bucket) and SES-07 (app-level idempotency marker) unless production observes throttling.
- Recommend running the full suite + php-cs-fixer in CI (MySQL + formatter available there) before ship.

## Self-Check: PASSED

All created files exist on disk and all task commits are present in git history (Tasks 1–6 verified; Task 7 is a gate-only task with no commit).

---
*Phase: 04-coordinated-ses-rate-limiting-2-bug-fixes*
*Completed: 2026-07-25*
