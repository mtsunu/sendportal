---
status: complete
quick_id: 260919-klt
commit: 4b37dbc
completed: 2026-09-19
---

# Quick Task Summary: Transactional Email API

Implemented a host-owned transactional email endpoint at `POST /api/v1/notifications/email`.

## Delivered

- Workspace API-token protection through the existing `RequireWorkspace` middleware.
- Request validation for `to`, `subject`, `body`, and optional `html`.
- Queueable `TransactionalEmail` mailable using the application's configured default mail sender.
- HTML and plain-text email views; plain-text fallback is escaped in the HTML view.
- JSON `202 Accepted` response with a stable queued status/message.
- Feature coverage for successful queueing, missing token rejection, and invalid payload rejection.

## Verification

- `php artisan test tests/Feature/Api/TransactionalEmailControllerTest.php` — 3 passed, 11 assertions.
- `php artisan test tests/Feature/Api/TransactionalEmailControllerTest.php tests/Feature/Auth/WorkspaceApiTokenTest.php` — 6 passed, 14 assertions.
- `vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php --diff --dry-run --using-cache=no -- ...` — no fixable files.
- PHP lint passed for all new PHP files.
- `git diff --cached --check` passed before the implementation commit.
- Mailable render smoke checks passed for explicit HTML and escaped plain-text fallback.

## Operational note

Actual provider delivery still depends on the deployment's `MAIL_*` configuration and a non-sync queue worker when asynchronous delivery is required.
