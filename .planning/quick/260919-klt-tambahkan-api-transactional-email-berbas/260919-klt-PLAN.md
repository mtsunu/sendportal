---
type: quick
id: 260919-klt
status: planned
description: Tambahkan API transactional email berbasis queue dengan token workspace, validasi payload, dan regression test
created: 2026-09-19
must_haves:
  truths:
    - A valid workspace API token can queue one transactional email through POST /api/v1/notifications/email.
    - The endpoint rejects missing or invalid workspace tokens and invalid recipient/payload data without queueing mail.
    - The queued email uses the application's configured mail sender and supports plain-text body plus optional HTML content.
  artifacts:
    - path: routes/api.php
      provides: Workspace-protected transactional email route.
    - path: app/Http/Controllers/Api/TransactionalEmailController.php
      provides: 202 JSON response after queueing the transactional mailable.
    - path: app/Mail/TransactionalEmail.php
      provides: Queueable Laravel mailable with subject, plain-text, and optional HTML content.
    - path: tests/Feature/Api/TransactionalEmailControllerTest.php
      provides: Authentication, validation, queueing, and content regression coverage.
  key_links:
    - from: routes/api.php
      to: app/Http/Controllers/Api/TransactionalEmailController.php
      via: POST api/v1/notifications/email route registration inside RequireWorkspace middleware.
    - from: app/Http/Controllers/Api/TransactionalEmailController.php
      to: app/Mail/TransactionalEmail.php
      via: Mail::to(...)->queue(...).
    - from: app/Mail/TransactionalEmail.php
      to: resources/views/emails/transactional*.blade.php
      via: Laravel Content view and text view definitions.
---

# Quick Task: Transactional Email API

## Scope

Add a host-owned transactional email endpoint without editing `vendor/mettle/sendportal-core` or adding database state. The endpoint is intentionally single-recipient and uses Laravel's configured default mailer; it does not expose an arbitrary `from` field or replace the existing campaign API.

## Task 1: Implement the queued transactional email contract

**Type:** tdd

**Files:**

- `app/Http/Requests/Api/TransactionalEmailRequest.php`
- `app/Http/Controllers/Api/TransactionalEmailController.php`
- `app/Mail/TransactionalEmail.php`
- `resources/views/emails/transactional.blade.php`
- `resources/views/emails/transactional-text.blade.php`
- `routes/api.php`

**Action:**

Define a validated request with required `to`, `subject`, and plain-text `body`, plus optional `html`. Register `POST /api/v1/notifications/email` inside the existing throttle and `RequireWorkspace` group. Queue a `ShouldQueue` mailable via `Mail::to(...)`; return HTTP 202 with a stable queued status/message. Keep the sender controlled by Laravel's configured `mail.from` settings and escape the plain-text fallback in the HTML view while allowing the explicit `html` field to render as caller-provided markup.

Apply the TDD gate: first express the endpoint contract in the feature test, then implement the request/controller/mailable/views/routes until the test passes, then refactor only for project style and bounded scope.

**Verify:**

- `php artisan route:list --path=api/v1/notifications/email`
- `php artisan test tests/Feature/Api/TransactionalEmailControllerTest.php`
- `php -l` on each new PHP file

**Done:**

The route is visible at the expected URI, valid token requests return 202 and queue exactly one `TransactionalEmail`, invalid auth/input returns the correct failure without queueing, and the mailable carries the requested subject/body/html data.

## Task 2: Add regression coverage and run focused quality checks

**Type:** tdd

**Files:**

- `tests/Feature/Api/TransactionalEmailControllerTest.php`

**Action:**

Cover a valid workspace token, missing/invalid token, invalid recipient or missing required content, and successful queue assertions including recipient, subject, body, and HTML payload. Use `Mail::fake()` and existing workspace/API-token factories; do not print or persist any token beyond test fixtures.

**Verify:**

- `php artisan test tests/Feature/Api/TransactionalEmailControllerTest.php tests/Feature/Auth/WorkspaceApiTokenTest.php`
- `vendor/bin/php-cs-fixer fix --diff --dry-run --using-cache=no app/Http/Controllers/Api/TransactionalEmailController.php app/Http/Requests/Api/TransactionalEmailRequest.php app/Mail/TransactionalEmail.php routes/api.php tests/Feature/Api/TransactionalEmailControllerTest.php`
- `git diff --check`

**Done:**

The focused endpoint and token-auth tests pass, formatting reports no changes for scoped PHP files, and the diff contains no whitespace errors or unrelated vendor/schema changes.

<threat_model>
ASVS level: 1; blocking threshold: high.

- Authentication: place the route under the existing `RequireWorkspace` middleware so only a valid bearer/query API token mapped to a workspace can reach the controller; verify missing and invalid tokens return 401.
- Authorization: resolve only the workspace context already attached to the token; do not add cross-workspace lookup or user-selected workspace input.
- Input safety: validate `to` as an email and required strings; do not accept caller-controlled `from` values. Escape plain-text content in the HTML fallback; raw HTML is an explicit authenticated caller payload.
- Operational safety: queue the mailable, return no message content in the response, and avoid logging recipients, bodies, subjects, or credentials.
</threat_model>
