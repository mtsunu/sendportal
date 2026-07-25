---
phase: 4
slug: coordinated-ses-rate-limiting-2-bug-fixes
title: Coordinated SES rate limiting + 2 bug fixes
date: 2026-07-25
source: user decisions (finalized in conversation) + milestone v1.1 research
---

# Phase 4 Context: Coordinated SES rate limiting + 2 bug fixes

Design decisions the operator has **locked**. The planner must treat these as
constraints, not open questions.

## Locked decisions

- **LOCKED — Delivery mechanism:** Host-level override only. `app/Mail/ThrottledSesAdapter`
  `extends Sendportal\Base\Adapters\SesMailAdapter` and overrides `send()` wholesale (never
  `parent::send()`). Rebind `MailAdapterFactory::$adapterMap[EmailServiceType::SES]` in
  `AppServiceProvider::boot()`. No `vendor/mettle/sendportal-core` edits. No fork, no
  Composer patch.
- **LOCKED — No new dependency:** Use Laravel's bundled `Illuminate\Support\Facades\Redis::throttle()`
  (`DurationLimiter`). No `composer require`.
- **LOCKED — Rate source:** `getSendQuota()['MaxSendRate']` from SES, cached ~5 min. No
  config/env override of the rate value (rate is authoritative from SES).
- **LOCKED — Blocking, not release-to-queue:** Pace by bounded in-`send()` block
  (`->block(15)`), NOT by releasing the job back to the queue (the adapter has no access to
  the job; release paths were evaluated and rejected — see RESEARCH ARCHITECTURE). Throw is
  reserved for limiter-timeout / retry-exhaustion overflow only.
- **LOCKED — Bug-1 (SES-03):** Detect throttling by `SesException::getAwsErrorCode() ===
  'Throttling'`, sub-branched on message: retry the rate case, fail-fast the daily-quota case.
- **LOCKED — Bug-2 (SES-04):** Throw a named `SesSendThrottledException` on exhaustion; never
  return `null`. Horizon `tries=3` is the single outer retry owner.
- **LOCKED — Scope fence:** Do NOT change `QuotaService` daily pre-check (`Max24HourSend`),
  no SESv2 migration, no UI, at most one info log line. Deferred: SES-06 (token bucket),
  SES-07 (app-level idempotency marker).
- **LOCKED — Standards:** PHP 8.4, `declare(strict_types=1)`, native parameter/return types,
  PSR-12 (`.php-cs-fixer.dist.php`). Do not disable Composer platform checks.

## Claude's discretion (planner may decide)

- Exact structure/naming of the retry+block loop inside `send()` (subject to the SES-05
  wait-before-send invariant and the bounded-attempts rule).
- The precise conservative fallback rate for `MaxSendRate` = `0`/missing, and the exact
  single-flight mechanism (`Cache::lock` vs `Cache::remember` with lock helper).
- Test file organization under `tests/` (Feature vs Unit split), and how Redis is provided
  for the cross-process limiter test in CI.
- Whether the `-1` (unlimited) case bypasses the limiter entirely or uses a very high allow.

## Key references

- Verified send path, code shapes, pitfalls, and Validation Architecture: `04-RESEARCH.md`.
- Requirements: `.planning/REQUIREMENTS.md` (SES-01…SES-05).
- Success criteria: `.planning/ROADMAP.md` → Phase 4.
