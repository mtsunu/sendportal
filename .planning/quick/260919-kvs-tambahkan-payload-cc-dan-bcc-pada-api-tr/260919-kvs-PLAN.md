---
type: quick
id: 260919-kvs
status: planned
description: Tambahkan payload cc dan bcc pada API transactional email
created: 2026-09-19
must_haves:
  truths:
    - A valid transactional email request can include one or more CC and BCC recipients.
    - CC and BCC values accept one email string or an array of email strings and are validated before queueing.
    - The queued mailable preserves To, CC, BCC, subject, body, and optional HTML recipients/content without exposing sender override fields.
  artifacts:
    - path: app/Http/Requests/Api/TransactionalEmailRequest.php
      provides: Normalization and validation for CC/BCC recipient payloads.
    - path: app/Http/Controllers/Api/TransactionalEmailController.php
      provides: Forwarding validated CC/BCC recipients to Laravel PendingMail before queueing.
    - path: tests/Feature/Api/TransactionalEmailControllerTest.php
      provides: Regression coverage for CC/BCC success, normalization, and validation failure.
  key_links:
    - from: app/Http/Requests/Api/TransactionalEmailRequest.php
      to: app/Http/Controllers/Api/TransactionalEmailController.php
      via: validated cc/bcc arrays returned by FormRequest.
    - from: app/Http/Controllers/Api/TransactionalEmailController.php
      to: Illuminate\Support\Facades\Mail
      via: PendingMail::cc()/bcc() followed by queue().
---

# Quick Task: Transactional Email CC/BCC

## Scope

Extend the existing host-owned transactional email endpoint with optional `cc` and `bcc` payloads. Preserve the existing route, queue behavior, default sender configuration, workspace token boundary, and vendor-package boundary. Do not add schema state or accept caller-controlled `from` values.

## Task 1: Normalize, validate, and forward CC/BCC recipients

**Type:** tdd

**Files:**

- `app/Http/Requests/Api/TransactionalEmailRequest.php`
- `app/Http/Controllers/Api/TransactionalEmailController.php`

**Action:**

Normalize a single string `cc` or `bcc` value to a one-item array before validation; retain arrays for multiple recipients. Validate each recipient as an email and pass non-empty validated lists through Laravel's `PendingMail::cc()` and `PendingMail::bcc()` before queueing the existing `TransactionalEmail` mailable.

Apply the TDD gate by extending the feature contract tests first, then implement the request/controller changes and refactor only for project style.

**Verify:**

- `php artisan test tests/Feature/Api/TransactionalEmailControllerTest.php`
- `php -l app/Http/Requests/Api/TransactionalEmailRequest.php`
- `php -l app/Http/Controllers/Api/TransactionalEmailController.php`

**Done:**

Valid requests queue the mailable with CC/BCC recipients; invalid CC/BCC entries return 422 and no mail is queued; requests without CC/BCC retain the existing behavior.

## Task 2: Expand focused regression checks and record verification

**Type:** tdd

**Files:**

- `tests/Feature/Api/TransactionalEmailControllerTest.php`

**Action:**

Assert CC and BCC are present on the queued mailable, exercise both array and single-string input forms, and assert invalid copy recipients fail validation without queueing. Re-run the existing workspace API-token coverage to guard the unchanged authentication boundary.

**Verify:**

- `php artisan test tests/Feature/Api/TransactionalEmailControllerTest.php tests/Feature/Auth/WorkspaceApiTokenTest.php`
- `vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php --diff --dry-run --using-cache=no -- app/Http/Controllers/Api/TransactionalEmailController.php app/Http/Requests/Api/TransactionalEmailRequest.php tests/Feature/Api/TransactionalEmailControllerTest.php`
- `git diff --check`

**Done:**

All scoped tests pass, formatter reports no changes, and only the intended controller/request/test changes are committed.

<threat_model>
ASVS level: 1; blocking threshold: high.

- Authentication and authorization remain delegated to the existing workspace token resolver and `RequireWorkspace` middleware.
- Validate every CC/BCC address as an email and never accept or derive a sender address from the request.
- Do not log or return recipient lists or message content; queue only the validated data needed by the mailable.
- Treat caller-provided HTML as the existing authenticated content contract; CC/BCC additions do not widen the HTML or sender trust boundary.
</threat_model>
