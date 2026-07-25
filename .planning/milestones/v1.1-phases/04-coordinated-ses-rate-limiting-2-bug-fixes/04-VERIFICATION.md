---
phase: 04-coordinated-ses-rate-limiting-2-bug-fixes
verified: 2026-07-25T00:00:00Z
status: human_needed
verdict: VERIFIED-WITH-CAVEATS
score: 5/5 success criteria verified (7/7 must-have truths)
behavior_unverified: 0
overrides_applied: 0
human_verification:
  - test: "Run the full DB-backed PHPUnit suite in CI (MySQL provisioned)"
    expected: "The 37 pre-existing Auth/Invitations/Setup/Workspaces tests pass — confirms the AppServiceProvider::boot() static rebind introduced no regression. Locally these ERROR only on MySQL 'Access denied for user laravel' (no local DB), not on code."
    why_human: "Requires a provisioned MySQL (sendportal_testing) with CI credentials; unavailable in this environment. The 26 SES tests + 1 Unit test pass locally against live Redis."
  - test: "Run php-cs-fixer PSR-12 gate in CI on app/Mail, config/sendportal-throttle.php, tests/Feature/Ses"
    expected: "Zero style violations."
    why_human: "vendor/bin/php-cs-fixer is not installed locally and the CI uses the oskarstark/php-cs-fixer-ga Docker image (Docker + global binary both absent locally). php -l passes on all new files; manual PSR-12 review done by executor."
  - test: "Live-SES pacing under real campaign volume (documented manual check, SES-01)"
    expected: "CloudWatch/SES shows send rate at ~MaxSendRate with near-zero Throttling errors."
    why_human: "Requires live AWS SES credentials + real volume; cannot be automated."
---

# Phase 4: Coordinated SES rate limiting + 2 bug fixes — Verification Report

**Phase Goal:** SES campaign sends proactively paced to the account per-second `MaxSendRate`, coordinated cross-process (shared Redis) across all Horizon workers (≤20), plus fixes for the throttle-code misclassification and the null-return `TypeError` on retry exhaustion — delivered entirely as a host-level adapter override (no `vendor/` edits, no new Composer dependency).

**Verified:** 2026-07-25
**Status:** human_needed (VERIFIED-WITH-CAVEATS — implementation and all 5 criteria proven by passing tests against live Redis; two outstanding CI-only gates)
**Re-verification:** No — initial verification
**Test run:** `vendor/bin/phpunit tests/Feature/Ses` → **OK (26 tests, 37 assertions)** against live Redis (`redis-cli ping` = PONG).

## Goal Achievement

### Observable Truths / Success Criteria

| # | Success Criterion (ROADMAP) → Requirement | Status | Evidence |
|---|-------------------------------------------|--------|----------|
| 1 | Combined `sendEmail` never exceeds `MaxSendRate`/sec, proven cross-process (shared Redis key), not per-process, by ≥2-worker test — SES-01 | ✓ VERIFIED | `SesRateLimitCrossProcessTest`: two adapters share one derived key; 10 sends at R=4 must occupy ≥ ceil(10/4)=3 aligned integer-second windows (pigeonhole equivalent of ≤R/sec). `assertInstanceOf(Connection::class, app('redis')->connection())` guards the silent per-process fallback. Passes against live Redis. |
| 2 | Throttling rate-exceeded retried to eventual success; Throttling daily-quota fails fast — SES-03 | ✓ VERIFIED | `SesThrottleDetectionTest` (classifier: RATE/DAILY_QUOTA/PROPAGATE) + `SesExhaustionTest::a_rate_error_is_retried_to_success_within_the_bounded_loop` (N-1 fail then success, returns id, exactly 2 sendEmail calls). Daily-quota `send()` throws with a single sendEmail call. |
| 3 | Exhaustion throws named `SesSendThrottledException` (never `TypeError`/null), message not marked sent, Horizon sole retry owner — SES-04 | ✓ VERIFIED | `SesExhaustionTest`: throws `SesSendThrottledException` after exactly `max_send_attempts`; `the_message_is_never_marked_sent_when_send_throws` proves markSent unreachable. `runWithRetries()` always returns a string or throws — no null reaches `resolveMessageId()`. `max_send_attempts=3`. |
| 4 | Fault injection between SES call and markSent does not double-send; all waiting before the SES call; block bounded < 60s — SES-05 | ✓ VERIFIED | `SesDoubleSendTest`: successful send calls sendEmail once, returns < 1.0s (no post-send wait); crash-before-markSent yields exactly 1 send; `max_total_wait_seconds` (45) < 60; config guard clamps a ≥60 value to 59. `SesExhaustionTest` aggregate-bound test: budget=1s caps elapsed < 2s even with attempts=100. |
| 5 | No `vendor/` change, no new dependency; `MaxSendRate` live from `getSendQuota()` cached ~5min with edge-value + single-flight handling; QuotaService untouched — SES-02 | ✓ VERIFIED | `SesRateSourceTest`: -1→unlimited bypass, 0/missing→default, 13.9→13, 1.0→1, one getSendQuota call across two cached sends, held lock→last-known-good. `git status --porcelain` clean for `vendor/mettle/sendportal-core`, `composer.json`, `composer.lock` (all tracked). No `composer require`; `tech-stack.added: []`. |

**Score:** 5/5 success criteria verified · 7/7 PLAN must-have truths verified · 0 behavior-unverified.

### Targeted Scrutiny (per verification request)

1. **Wholesale `send()` override does NOT call `parent::send()` + exact signature** — ✓ VERIFIED. `ThrottledSesAdapter::send(string $fromEmail, string $fromName, string $toEmail, string $subject, MessageTrackingOptions $trackingOptions, string $content): string` matches the parent `SesMailAdapter` signature byte-for-byte. Body builds payload via `buildPayload()` + `resolveClient()->sendEmail()` directly; `parent::send()` (which re-enters the buggy `ThrottlesSending` trait) is never called. No incompatible-signature fatal.
2. **SES-03 branches on `getAwsErrorMessage()`, and the test exercises the production field** — ✓ VERIFIED. `classifyThrottleException()` gates on `getAwsErrorCode() === 'Throttling'` then substring-matches `getAwsErrorMessage()` (not the verbose `getMessage()`). Confirmed in `vendor/aws/.../AwsException.php`: constructor sets `errorCode = context['code']`, `errorMessage = context['message']`; accessors return those fields. The test builds `new SesException($awsMessage, $command, ['code'=>'Throttling','message'=>$awsMessage])`, so `getAwsErrorMessage()` returns exactly the text production reads. (See minor note below.)
3. **SES-04 named exception, no null/TypeError, bounded, not marked sent** — ✓ VERIFIED. `runWithRetries()` returns `$attempt()` (string) or throws `SesSendThrottledException`; `pacedSesCall()`'s `then()` failure callback throws (never returns null). Bounded by `max_send_attempts` AND shared deadline. Tests assert exact call count and unreachable markSent.
4. **SES-05 all-wait-before-send, one shared deadline, config guard, no double-send** — ✓ VERIFIED. Single `$deadline = microtime(true) + maxTotalWaitSeconds()` computed once at entry; `currentBlockCap()` and `reactiveBackoff()` both draw from it; backoff only between FAILED attempts (never after success). `maxTotalWaitSeconds()` clamps ≥60 → 59 with a once-only warning. Fault-injection test proves no second send.
5. **SES-01 deviation is a valid cross-process proof, not a weakened check** — ✓ VERIFIED (sound). DurationLimiter anchors each window to an integer Unix second (`HMSET start, time()`), so a coordinated shared key caps each aligned window at R ⇒ 10 sends need ≥3 windows. A per-process fallback (two independent limiters, ~2R/sec combined) would pack 10 sends into 2 windows and FAIL the `>= 3` assertion. Never relaxed to ≤2R; store-is-Redis assertion additionally guards the fallback directly. Deviation is a stable, equivalent discriminator. (Theoretical note below.)
6. **Scope fences honored** — ✓ VERIFIED via git. `vendor/mettle/sendportal-core` clean; `composer.json`/`composer.lock` clean (both tracked). `QuotaService` is a vendor file and vendor is clean ⇒ untouched; no host override of it exists. Only `app/Mail/*` (new), `config/sendportal-throttle.php` (new), `tests/Feature/Ses/*` (new), and one line in `app/Providers/AppServiceProvider.php` changed.

### Required Artifacts

| Artifact | Status | Details |
|----------|--------|---------|
| `app/Mail/ThrottledSesAdapter.php` | ✓ VERIFIED | Wholesale override, native types, `final`, extends vendor `SesMailAdapter`; wired via factory rebind. |
| `app/Mail/SesSendThrottledException.php` | ✓ VERIFIED | `final class ... extends RuntimeException`; thrown on exhaustion + limiter block timeout. |
| `config/sendportal-throttle.php` | ✓ VERIFIED | All knobs env-overridable; no rate-VALUE override (LOCKED); no `declare` (matches config convention). |
| `app/Providers/AppServiceProvider.php` (rebind) | ✓ VERIFIED | `MailAdapterFactory::$adapterMap[EmailServiceType::SES] = ThrottledSesAdapter::class;` in `boot()`; proven by tracer + DoubleSend routing tests. |
| `tests/Feature/Ses/*` (8 files, 26 tests) | ✓ VERIFIED | All green against live Redis. |

### Key Link Verification

| Link | Status | Details |
|------|--------|---------|
| `AppServiceProvider::boot()` → `MailAdapterFactory::$adapterMap[SES]` | ✓ WIRED | `factory_returns_throttled_adapter_for_an_ses_service_after_boot` + `the_rebind_routes_ses_to_the_throttled_adapter` both pass. |
| `ThrottledSesAdapter::send()` → `Redis::throttle($key)->allow($rate)->every(1)->block(...)->then(...)` | ✓ WIRED | Shared `sp:ses:rate:{md5(region\|key)}` key; cross-process test proves atomic coordination on the `default` Redis connection. |
| `getAwsErrorCode()==='Throttling'` + `getAwsErrorMessage()` sub-branch | ✓ WIRED | Classifier correctness pivot; confirmed against AWS SDK source. |

### Anti-Patterns Found

None blocking. No `TODO`/`FIXME`/`XXX`/`HACK` debt markers in the new source. The `buildPayload()` docblock notes the vendor's pre-existing per-message-tracking `TODO(david)` limitation (SES cannot set per-message tracking) as context only — not new debt.

## Deviation Assessment

1. **Cross-process assertion reshaped to pigeonhole window-count (Rule 1, correctness-preserving)** — SOUND. The plan's literal per-recorded-timestamp `≤R` per `floor((t-t0)/1s)` window is genuinely flaky: the limiter anchors windows to integer Unix seconds, not to the first (mid-second) send, so post-hoc timestamps drift across second edges and read up to ~2R from measurement skew. The replacement — "M sends occupy ≥ ceil(M/R) aligned windows" + store-is-Redis assertion — is a stable, logically-equivalent discriminator that still strictly catches the per-process-fallback regression (2 windows < 3). Accepted.
   - *Theoretical note (non-blocking):* the window-count form is marginally weaker than strict per-window ≤R — a hypothetical distribution like (8,1,1) has ≥3 windows yet violates ≤R in window 1. This is impossible for a correctly-coordinated DurationLimiter (it caps each aligned window at R), and the store-is-Redis assertion closes the realistic fallback gap, so no real regression escapes.
2. **Added `tests/Feature/Ses/SesTestCase.php` DB-free base (Rule 3, enabling infra)** — SOUND. Project `Tests\TestCase::setUp()` runs `artisan migrate` (MySQL-only, unavailable locally); the SES suite needs no DB. Test organization under `tests/` is explicit Claude discretion per CONTEXT.md. Boots the app (runs `boot()` + rebind) without migrating. No production impact.

### Minor observation (non-blocking, WARNING)

**SES-03 test fidelity:** the test helper sets BOTH the constructor `$message` and `context['message']` to the same clean AWS wording. Production correctly reads `getAwsErrorMessage()` (verified by direct source reading), but because `getMessage()===getAwsErrorMessage()` in the fixture, the test would not *discriminate* a hypothetical regression that reverted production to the vendor's buggy `getMessage()` match. In real SES, `getMessage()` is the verbose `"Error executing SendEmail…"` wrapper. Optional hardening: set the constructor `$message` to a verbose wrapper distinct from `context['message']`. Correctness is not at risk today.

## Outstanding CI Gates (must pass before ship)

The implementation is complete and all behaviors are proven locally against live Redis. Two gates cannot run in this environment and MUST be green in CI before shipping:

1. **Full DB-backed PHPUnit suite** — 37 pre-existing Auth/Invitations/Setup/Workspaces tests ERROR locally only on MySQL `Access denied for user 'laravel'` (no local `sendportal_testing` DB). CI provisions MySQL (`.github/workflows/ci.yml`, PHP 8.2/8.3 matrix). Purpose: confirm the `boot()` static-array rebind causes no regression (low risk — it is a single idempotent array write). Locally: 26 SES + 1 Unit test pass; zero SES errors.
2. **php-cs-fixer PSR-12 format gate** — `vendor/bin/php-cs-fixer` is not installed locally and CI runs the `oskarstark/php-cs-fixer-ga` Docker image (Docker + global binary both absent here). `php -l` passes on all new files; executor did a manual PSR-12 review. Run `vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php --dry-run --diff app/Mail config/sendportal-throttle.php tests/Feature/Ses` in CI.

Additionally, the one documented **manual** check (live-SES pacing under real campaign volume, SES-01) remains — it requires live AWS credentials and cannot be automated.

## Gaps Summary

No code gaps. All 5 ROADMAP success criteria and all 5 requirements (SES-01..SES-05) are implemented as specified and backed by genuine, non-tautological automated tests that pass against a live Redis. Scope fences (no `vendor/` edit, no new dependency, QuotaService untouched, wholesale override, exact signature) are all honored and git-verified. The only items keeping this from an unconditional PASS are two CI-only verification gates (DB suite regression check + formatter) that this local environment cannot execute, plus the inherent live-SES manual check — hence VERIFIED-WITH-CAVEATS / `human_needed`.

---

_Verified: 2026-07-25_
_Verifier: Claude (gsd-verifier)_
