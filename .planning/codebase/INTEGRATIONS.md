# External Integrations

**Analysis Date:** 2026-07-22

## APIs & External Services

**Email delivery and marketing providers:**
- SMTP - Default transactional mail transport for invitations, verification, and password-reset mail; configured in `config/mail.php` and called through Laravel mail notifications/services in `app/Services/Workspaces/SendInvitation.php`.
  - SDK/Client: Laravel Mail, supplied by `laravel/framework`.
  - Auth: `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, and `MAIL_ENCRYPTION`.
- Amazon SES - Optional Laravel mail transport and SendPortal provider option; defined in `config/mail.php` and `config/services.php`.
  - SDK/Client: Laravel mail/SES integration through framework dependencies; SendPortal campaign delivery is delegated to `mettle/sendportal-core`.
  - Auth: `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, and `AWS_DEFAULT_REGION`.
- Mailgun - Optional Laravel mail transport and SendPortal provider option; configured in `config/mail.php` and `config/services.php`.
  - SDK/Client: Laravel Mail integration; SendPortal campaign behavior is delegated to `mettle/sendportal-core`.
  - Auth: `MAILGUN_DOMAIN`, `MAILGUN_SECRET`, and optional `MAILGUN_ENDPOINT`.
- Postmark - Optional Laravel mail transport and SendPortal provider option; configured in `config/mail.php` and `config/services.php`.
  - SDK/Client: Laravel Mail integration; SendPortal campaign behavior is delegated to `mettle/sendportal-core`.
  - Auth: `POSTMARK_TOKEN`.
- SendGrid and Mailjet - Declared as SendPortal-supported providers in `README.md`; their provider clients and credentials are owned by `mettle/sendportal-core`, not by local application configuration files.
  - SDK/Client: `mettle/sendportal-core` `^3.0` from `composer.json`.
  - Auth: package-defined configuration; no local provider variables are declared outside the core package.

**SendPortal Core application API:**
- SendPortal Core - Provides the campaign-management API, public routes, reporting/tracking behavior, and related assets; host routes delegate to it in `routes/web.php` and `routes/api.php`.
  - SDK/Client: PHP package `mettle/sendportal-core` `^3.0`.
  - Auth: workspace-scoped bearer API token resolved by `app/Models/ApiToken.php` and the current-workspace resolver in `app/Providers/AppServiceProvider.php`.

**HTTP client:**
- Generic HTTP transport - Guzzle is declared in `composer.json`; no direct external HTTP request is issued from local `app/` code. Package-managed provider calls belong to SendPortal Core.
  - SDK/Client: `guzzlehttp/guzzle` `^7.8.1`.
  - Auth: provider-specific; no host-level direct HTTP client credentials are configured.

## Data Storage

**Databases:**
- MySQL - Default database driver in `config/database.php`; used by Laravel Eloquent models and local migrations in `database/migrations/`.
  - Connection: `DATABASE_URL` or `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, and optional `DB_SOCKET`.
  - Client: Laravel Eloquent/PDO via `laravel/framework`.
- PostgreSQL - Supported alternative database connection in `config/database.php`; CI executes the PHPUnit suite against PostgreSQL in `.github/workflows/ci.yml`.
  - Connection: `DATABASE_URL` or the same `DB_*` variables with `DB_CONNECTION=pgsql`.
  - Client: Laravel Eloquent/PDO via `laravel/framework`.
- SQLite and SQL Server - Framework-provided connection definitions in `config/database.php`; no committed deployment or test usage is present.
  - Connection: `DATABASE_URL` / `DB_DATABASE` for SQLite and `DB_*` for SQL Server.
  - Client: Laravel Eloquent/PDO via `laravel/framework`.

**File Storage:**
- Local filesystem by default (`storage/app`) through `config/filesystems.php`.
- Amazon S3 is an optional disk configured in `config/filesystems.php`.
  - Auth: `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, `AWS_BUCKET`, plus optional `AWS_URL` and `AWS_ENDPOINT`.

**Caching:**
- File cache by default at `storage/framework/cache/data` (`config/cache.php`).
- Redis is the configured alternative for cache and is the backend required by Horizon (`config/cache.php`, `config/database.php`, and `config/horizon.php`).
- Memcached and DynamoDB are framework-defined optional stores in `config/cache.php`; no local usage is committed.

## Authentication & Identity

**Auth Provider:**
- Custom Laravel authentication backed by the local `users` table and `App\\Models\\User` (`config/auth.php`, `app/Models/User.php`).
  - Implementation: session-based `web` guard, Laravel UI auth routes in `routes/web.php`, email verification through `MustVerifyEmail`, and optional password reset / public registration controlled by `config/sendportal-host.php`.
- Workspace API tokens are custom bearer tokens stored in `api_tokens` (`app/Models/ApiToken.php`, `database/migrations/2020_11_13_120125_create_api_tokens_table.php`).
  - Implementation: SendPortal resolves a workspace ID from an authenticated user's selected workspace or from a bearer token in `app/Providers/AppServiceProvider.php`.

## Monitoring & Observability

**Error Tracking:**
- Not detected. Development exception presentation uses Laravel Ignition from `composer.json`; no hosted error-tracking configuration is committed.

**Logs:**
- Laravel/Monolog logs locally to `storage/logs/laravel.log` by default (`config/logging.php`).
- Optional Slack critical-alert and Papertrail/syslog channels are declared in `config/logging.php` using `LOG_SLACK_WEBHOOK_URL`, `PAPERTRAIL_URL`, and `PAPERTRAIL_PORT`.
- Laravel Horizon monitors Redis queues at the configured `horizon` path (`config/horizon.php`, `app/Providers/HorizonServiceProvider.php`).

## CI/CD & Deployment

**Hosting:**
- Not detected. The repository describes a self-hosted SendPortal/Laravel application in `README.md`; no deployment manifest or hosting-provider configuration is committed.

**CI Pipeline:**
- GitHub Actions runs PHPUnit against MySQL and PostgreSQL using PHP 8.2 and 8.3 containers in `.github/workflows/ci.yml`.
- GitHub Actions runs PHP-CS-Fixer for pull requests in `.github/workflows/format.yml`.

## Environment Configuration

**Required env vars:**
- Core application: `APP_KEY`, `APP_ENV`, `APP_DEBUG`, `APP_URL`, and optionally `APP_NAME` (`config/app.php`).
- Database: select a driver with `DB_CONNECTION`, then supply `DATABASE_URL` or the appropriate `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` fields (`config/database.php`).
- Email delivery: set `MAIL_MAILER` and its relevant `MAIL_*` credentials; provider transports additionally require the Mailgun, Postmark, or AWS variables identified above (`config/mail.php`, `config/services.php`).
- Redis-backed Horizon / queues: `QUEUE_CONNECTION=redis` plus `REDIS_*`; Horizon's production supervisors in `config/horizon.php` use Redis.
- Host behavior: `SENDPORTAL_REGISTER`, `SENDPORTAL_PASSWORD_RESET`, and `SENDPORTAL_THROTTLE_MIDDLEWARE` (`config/sendportal-host.php`).

**Secrets location:**
- Runtime values belong in the Laravel environment file created by `app/Console/Commands/InstallApplication.php` from the existing `.env.example` template, or in deployment-managed environment variables. The actual `.env` contents are not inspected or committed.

## Webhooks & Callbacks

**Incoming:**
- SendPortal Core registers public HTTP routes through `Sendportal::publicWebRoutes()` and `Sendportal::publicApiRoutes()` in `routes/web.php` and `routes/api.php`. Provider callback endpoints and validation are package-owned; no concrete callback URL is declared in local host source.
- The Redis/Horizon queue `sendportal-webhook-process` in `config/horizon.php` confirms asynchronous webhook processing is expected when the core package is configured for it.

**Outgoing:**
- Transactional user mail is sent through the selected Laravel mail transport configured by `config/mail.php`; invitation delivery originates in `app/Services/Workspaces/SendInvitation.php`.
- Campaign-provider delivery and any core-defined outbound webhook behavior are owned by `mettle/sendportal-core`; no local outbound webhook endpoint configuration is present.

---

*Integration audit: 2026-07-22*
