---
phase: 4
slug: coordinated-ses-rate-limiting-2-bug-fixes
# status lifecycle: draft (seeded by plan-phase) → validated (set by validate-phase §6)
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-07-25
---

# Phase 4 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit 10.5 (`phpunit/phpunit ^10.5`) |
| **Config file** | `phpunit.xml.dist` |
| **Quick run command** | `vendor/bin/phpunit --filter 'ThrottledSesAdapter|SesSendThrottled'` |
| **Full suite command** | `vendor/bin/phpunit` |
| **Estimated runtime** | ~30–90 seconds (quick filter: a few seconds) |

Redis note: the cross-process limiter test (SES-01) requires a live Redis (same as
Horizon/queue assume). `getSendQuota()` must be mocked/seeded so CI need not reach live SES.

---

## Sampling Rate

- **After every task commit:** Run the quick filter command.
- **After every plan wave:** Run the full suite command.
- **Before `/gsd-verify-work`:** Full suite must be green.
- **Max feedback latency:** ~90 seconds.

---

## Per-Task Verification Map

*Seeded by the planner from PLAN.md tasks. Each SES-0x requirement maps to at least one
automated test below.*

| Task ID | Plan | Wave | Requirement | Threat Ref | Secure Behavior | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|------------|-----------------|-----------|-------------------|-------------|--------|
| 4-01-01 | 01 | 1 | SES-01, SES-05 | T-04-01, T-04-05 | Tracer: factory returns ThrottledSesAdapter after boot AND one paced send succeeds end-to-end | integration | `vendor/bin/phpunit --filter Tracer` | ✅ | ⬜ pending |
| 4-01-02 | 01 | 1 | SES-02 | T-04-03 | MaxSendRate cached (~5min, single-flight) + edge values (-1/0/fractional/sandbox) handled | unit | `vendor/bin/phpunit --filter SesRate` | ✅ | ⬜ pending |
| 4-01-03 | 01 | 1 | SES-03 | T-04-04 | Classifier: `Throttling`+rate → RATE-RETRYABLE, daily-quota → FAIL-FAST, non-Throttling → PROPAGATE (matches `getAwsErrorMessage()`); daily-quota/non-Throttling proven via `send()` — retry-to-success is Task 4 | unit | `vendor/bin/phpunit --filter Detection` | ✅ | ⬜ pending |
| 4-01-04 | 01 | 1 | SES-04, SES-03 | T-04-02 | Rate error retried to success within the loop; exhaustion throws `SesSendThrottledException` (no null/TypeError), bounded attempts, message not marked sent; aggregate in-send wait bounded by one shared deadline < 60s | unit | `vendor/bin/phpunit --filter Exhaust` | ✅ | ⬜ pending |
| 4-01-05 | 01 | 1 | SES-01 | T-04-01 | Combined sends across ≥2 shared-key contexts hold ≤ R per aligned first-acquire-anchored fixed window (±50ms tolerance, strict ≤R not ≤2R, not rolling); store is Redis | integration | `vendor/bin/phpunit --filter CrossProcess` | ✅ | ⬜ pending |
| 4-01-06 | 01 | 1 | SES-05 | T-04-05 | No double-send on interrupt between send and mark; wait strictly before send; AGGREGATE in-send wait budget `max_total_wait_seconds` < 60 (single shared deadline, guard rejects ≥60); rebind routes SES→ThrottledSesAdapter; vendor/composer untouched | integration | `vendor/bin/phpunit --filter DoubleSend` | ✅ | ⬜ pending |
| 4-01-07 | 01 | 1 | SES-01..05 | — | Full suite green + php-cs-fixer clean; vendor/ and composer.* unchanged | gate | `vendor/bin/phpunit` | ✅ | ⬜ pending |

*Wave 0 note: this phase is TDD — each task writes its failing test first, so the test files
are created inside their owning task (no separate Wave 0). "File Exists ✅" means the task
creates it as part of RED.*

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky. Task IDs are placeholders — the planner
reconciles them with the final PLAN.md task breakdown.*

---

## Wave 0 Requirements

- [ ] `tests/Feature/Ses/ThrottledSesAdapterTest.php` — SES-02/03/04 unit coverage (mocked `SesClient`).
- [ ] `tests/Feature/Ses/SesRateLimitCrossProcessTest.php` — SES-01 shared-Redis pacing (≥2 contexts).
- [ ] `tests/Feature/Ses/SesDoubleSendTest.php` — SES-05 fault injection + rebind routing.
- [ ] Shared fixtures/helpers for mocking `SesClient::sendEmail` / `getSendQuota` and a Redis test connection.

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Real SES account pacing under load | SES-01 | Requires live AWS SES credentials + real campaign volume | In a staging workspace with a real SES email service, dispatch a large campaign and confirm CloudWatch/SES shows send rate at ~MaxSendRate with near-zero `Throttling` errors. |

*Automated tests cover the coordination logic with a mocked SES client; the live-account
behavior is the one manual confirmation.*

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 90s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
