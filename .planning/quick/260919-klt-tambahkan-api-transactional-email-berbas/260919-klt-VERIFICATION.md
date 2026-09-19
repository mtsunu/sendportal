---
status: passed
verified: 2026-09-19
---

# Verification: Transactional Email API

## Must-have results

| Must-have | Evidence | Result |
|---|---|---|
| Valid workspace token queues one transactional email | Feature test passes; `Mail::assertQueued(TransactionalEmail::class)` verifies recipient, subject, body, and HTML payload. | PASS |
| Missing/invalid access and invalid payload are rejected before queueing | Feature tests pass for 401 access rejection and 422 validation with `Mail::assertNothingQueued()`. | PASS |
| Email supports plain text and optional HTML using configured sender | Queueable mailable defines HTML/text views; render smoke checks show explicit HTML and escaped plain-text fallback. | PASS |

## Checks

- Route exists as `POST api/v1/notifications/email` under the existing workspace middleware group.
- Focused endpoint and API-token suites: 6 passed, 14 assertions.
- PHP lint and scoped PHP-CS-Fixer dry-run passed.
- No schema or vendor package changes were introduced.

## Remaining environment gate

Live SMTP/provider delivery and production queue-worker operation were not exercised because they require deployment credentials and services; the code path is covered through Laravel's mail fake and mailable rendering checks.
