# Technology Stack

**Analysis Date:** 2026-07-22

## Languages

**Primary:**
- PHP 8.2 or 8.3 - Application, HTTP layer, Eloquent models, console commands, Livewire components, and tests throughout `app/`, `config/`, `database/`, `routes/`, and `tests/`; the supported versions are declared in `composer.json`.

**Secondary:**
- Blade/PHP templates - Server-rendered HTML in `resources/views/`.
- HTML/CSS/JavaScript - Browser assets are supplied by Laravel/UI and the published SendPortal Core assets; this repository has no `package.json` or local JavaScript build configuration.
- YAML - GitHub Actions automation in `.github/workflows/ci.yml` and `.github/workflows/format.yml`.

## Runtime

**Environment:**
- PHP 8.2 or 8.3 with Composer-managed dependencies, as constrained in `composer.json` and exercised by `.github/workflows/ci.yml`.
- A web server or PHP runtime dispatches through `public/index.php`; Artisan commands dispatch through `artisan` and `app/Console/Kernel.php`.

**Package Manager:**
- Composer - dependency manifest: `composer.json`.
- Lockfile: missing (`composer.lock` is not present), so installs resolve the permitted version ranges in `composer.json`.
- JavaScript package manager: not detected; no Node manifest or frontend lockfile is present.

## Frameworks

**Core:**
- Laravel 11 (`laravel/framework` `^11.0`) - MVC framework, routing, Eloquent ORM, jobs, mail, cache, filesystem, sessions, and configuration; bootstrapped by `bootstrap/app.php` and configured under `config/`.
- SendPortal Core (`mettle/sendportal-core` `^3.0`) - Campaign, subscriber/list, tracking, reporting, public/API routes, and provider-specific email functionality. The host delegates those routes from `routes/web.php` and `routes/api.php` through `Sendportal::...Routes()`.
- Livewire 3 (`livewire/livewire` `^3.4`) - Server-driven interactive setup UI; registered in `app/Providers/AppServiceProvider.php` and rendered from `resources/views/livewire/setup.blade.php`.
- Laravel UI (`laravel/ui` `^4.5`) - Authentication route scaffolding used by `routes/web.php` via `Auth::routes()`.

**Testing:**
- PHPUnit 10 (`phpunit/phpunit` `^10.5`) - Unit and feature tests in `tests/`; config: `phpunit.xml.dist`.
- Faker (`fakerphp/faker` `^1.23`) and Mockery (`mockery/mockery` `^1.6`) - factories and test doubles in `database/factories/` and `tests/`.

**Build/Dev:**
- Composer - install, autoload generation, and Laravel package discovery scripted in `composer.json`.
- Laravel Tinker (`laravel/tinker` `^2.9`) - interactive application shell.
- Laravel Ignition (`spatie/laravel-ignition` `^2.5.1`) and Collision (`nunomaduro/collision` `^8.1`) - local exception and CLI error presentation.
- PHP-CS-Fixer configuration in `.php-cs-fixer.dist.php`; CI runs it in `.github/workflows/format.yml`.

## Key Dependencies

**Critical:**
- `mettle/sendportal-core` `^3.0` - Owns the newsletter/campaign domain, core public and authenticated API route registration, provider interactions, and published frontend assets. Host integration points are `app/Providers/AppServiceProvider.php`, `routes/web.php`, and `routes/api.php`.
- `laravel/framework` `^11.0` - Owns HTTP lifecycle, database access, authentication, mail, queue, cache, filesystem, and configuration used across `app/`.
- `livewire/livewire` `^3.4` - Powers the installer wizard component at `app/Livewire/Setup.php`.
- `laravel/horizon` `^5.24` - Supervises Redis workers and exposes the Horizon UI configured in `config/horizon.php`.

**Infrastructure:**
- `guzzlehttp/guzzle` `^7.8.1` - HTTP client available to Laravel and SendPortal-related integrations; no direct Guzzle usage is present in local `app/` code.
- Laravel queue/cache/database/filesystem packages are supplied by the framework and configured in `config/queue.php`, `config/cache.php`, `config/database.php`, and `config/filesystems.php`.
- `roave/security-advisories` `dev-master` - Composer conflict list preventing installation of known-vulnerable packages.

## Configuration

**Environment:**
- Laravel reads environment values through the files in `config/`; an `.env.example` template is present, but its contents are not used by this map. `app/Console/Commands/InstallApplication.php` creates or updates `.env` during interactive installation.
- Application identity and encryption use `APP_NAME`, `APP_ENV`, `APP_DEBUG`, `APP_URL`, and `APP_KEY` in `config/app.php`.
- Database selection and credentials are configured through `DB_*` / `DATABASE_URL` in `config/database.php`; queues, cache, sessions, and Redis are separately configurable through `QUEUE_*`, `CACHE_*`, `SESSION_*`, and `REDIS_*` variables.
- Mail providers use `MAIL_*`, `MAILGUN_*`, `POSTMARK_TOKEN`, and `AWS_*` settings from `config/mail.php` and `config/services.php`.
- Host-specific behavior is configured by `SENDPORTAL_REGISTER`, `SENDPORTAL_PASSWORD_RESET`, and `SENDPORTAL_THROTTLE_MIDDLEWARE` in `config/sendportal-host.php`.

**Build:**
- `composer.json` - dependency constraints, PSR-4 autoloading, and Composer lifecycle scripts.
- `phpunit.xml.dist` - PHPUnit runner settings and test environment defaults.
- `.php-cs-fixer.dist.php` - PSR-12-oriented formatting rules.
- `.github/workflows/ci.yml` - PHP 8.2/8.3 test matrix against MySQL and PostgreSQL.
- `.github/workflows/format.yml` - pull-request PHP formatting automation.

## Platform Requirements

**Development:**
- PHP 8.2+ and Composer are required by `composer.json`.
- MySQL 5.7+ or PostgreSQL 9.4+ are supported application databases, per `README.md`; the CI workflow tests both in `.github/workflows/ci.yml`.
- Redis and the `phpredis` extension are required when using the configured Redis queues/cache or Laravel Horizon (`config/database.php`, `config/horizon.php`).
- PDO driver support is required for the selected configured database (`config/database.php`).

**Production:**
- Self-hosted PHP/Laravel deployment; no hosting-provider or deployment workflow is committed. Production worker topology is defined in `config/horizon.php`: Redis-backed queues include `default`, `sendportal-message-dispatch`, and `sendportal-webhook-process`.
- Persist `storage/` and configure a durable database. Use a process manager to run Horizon when Redis queues are selected.

---

*Stack analysis: 2026-07-22*
