<!-- GSD:project-start source:PROJECT.md -->

## Project

**SendPortal PHP 8.4 Compatibility**

SendPortal is a self-hosted email-marketing application built as a Laravel 11 host around the `mettle/sendportal-core` package. This project makes the existing application installable and operational on PHP 8.4 by resolving Composer constraints, updating compatible dependency bounds, and verifying the runtime and automated checks.

**Core Value:** Operators can install and run SendPortal reliably on PHP 8.4 without bypassing dependency or platform requirements.

### Constraints

- **Runtime**: PHP 8.4 must be a supported installation target — this is the stated project outcome.
- **Dependency safety**: Do not disable Composer platform checks or silently drop vulnerability protection — installation must remain trustworthy.
- **Compatibility**: Preserve existing application behavior and Laravel 11/SendPortal Core integration — this is a focused compatibility milestone.
- **Reproducibility**: Commit `composer.lock` once a valid graph is resolved — unpinned fresh resolution currently creates machine-to-machine drift.

<!-- GSD:project-end -->

<!-- GSD:stack-start source:codebase/STACK.md -->

## Technology Stack

## Languages

- PHP 8.2 or 8.3 - Application, HTTP layer, Eloquent models, console commands, Livewire components, and tests throughout `app/`, `config/`, `database/`, `routes/`, and `tests/`; the supported versions are declared in `composer.json`.
- Blade/PHP templates - Server-rendered HTML in `resources/views/`.
- HTML/CSS/JavaScript - Browser assets are supplied by Laravel/UI and the published SendPortal Core assets; this repository has no `package.json` or local JavaScript build configuration.
- YAML - GitHub Actions automation in `.github/workflows/ci.yml` and `.github/workflows/format.yml`.

## Runtime

- PHP 8.2 or 8.3 with Composer-managed dependencies, as constrained in `composer.json` and exercised by `.github/workflows/ci.yml`.
- A web server or PHP runtime dispatches through `public/index.php`; Artisan commands dispatch through `artisan` and `app/Console/Kernel.php`.
- Composer - dependency manifest: `composer.json`.
- Lockfile: missing (`composer.lock` is not present), so installs resolve the permitted version ranges in `composer.json`.
- JavaScript package manager: not detected; no Node manifest or frontend lockfile is present.

## Frameworks

- Laravel 11 (`laravel/framework` `^11.0`) - MVC framework, routing, Eloquent ORM, jobs, mail, cache, filesystem, sessions, and configuration; bootstrapped by `bootstrap/app.php` and configured under `config/`.
- SendPortal Core (`mettle/sendportal-core` `^3.0`) - Campaign, subscriber/list, tracking, reporting, public/API routes, and provider-specific email functionality. The host delegates those routes from `routes/web.php` and `routes/api.php` through `Sendportal::...Routes()`.
- Livewire 3 (`livewire/livewire` `^3.4`) - Server-driven interactive setup UI; registered in `app/Providers/AppServiceProvider.php` and rendered from `resources/views/livewire/setup.blade.php`.
- Laravel UI (`laravel/ui` `^4.5`) - Authentication route scaffolding used by `routes/web.php` via `Auth::routes()`.
- PHPUnit 10 (`phpunit/phpunit` `^10.5`) - Unit and feature tests in `tests/`; config: `phpunit.xml.dist`.
- Faker (`fakerphp/faker` `^1.23`) and Mockery (`mockery/mockery` `^1.6`) - factories and test doubles in `database/factories/` and `tests/`.
- Composer - install, autoload generation, and Laravel package discovery scripted in `composer.json`.
- Laravel Tinker (`laravel/tinker` `^2.9`) - interactive application shell.
- Laravel Ignition (`spatie/laravel-ignition` `^2.5.1`) and Collision (`nunomaduro/collision` `^8.1`) - local exception and CLI error presentation.
- PHP-CS-Fixer configuration in `.php-cs-fixer.dist.php`; CI runs it in `.github/workflows/format.yml`.

## Key Dependencies

- `mettle/sendportal-core` `^3.0` - Owns the newsletter/campaign domain, core public and authenticated API route registration, provider interactions, and published frontend assets. Host integration points are `app/Providers/AppServiceProvider.php`, `routes/web.php`, and `routes/api.php`.
- `laravel/framework` `^11.0` - Owns HTTP lifecycle, database access, authentication, mail, queue, cache, filesystem, and configuration used across `app/`.
- `livewire/livewire` `^3.4` - Powers the installer wizard component at `app/Livewire/Setup.php`.
- `laravel/horizon` `^5.24` - Supervises Redis workers and exposes the Horizon UI configured in `config/horizon.php`.
- `guzzlehttp/guzzle` `^7.8.1` - HTTP client available to Laravel and SendPortal-related integrations; no direct Guzzle usage is present in local `app/` code.
- Laravel queue/cache/database/filesystem packages are supplied by the framework and configured in `config/queue.php`, `config/cache.php`, `config/database.php`, and `config/filesystems.php`.
- `roave/security-advisories` `dev-master` - Composer conflict list preventing installation of known-vulnerable packages.

## Configuration

- Laravel reads environment values through the files in `config/`; an `.env.example` template is present, but its contents are not used by this map. `app/Console/Commands/InstallApplication.php` creates or updates `.env` during interactive installation.
- Application identity and encryption use `APP_NAME`, `APP_ENV`, `APP_DEBUG`, `APP_URL`, and `APP_KEY` in `config/app.php`.
- Database selection and credentials are configured through `DB_*` / `DATABASE_URL` in `config/database.php`; queues, cache, sessions, and Redis are separately configurable through `QUEUE_*`, `CACHE_*`, `SESSION_*`, and `REDIS_*` variables.
- Mail providers use `MAIL_*`, `MAILGUN_*`, `POSTMARK_TOKEN`, and `AWS_*` settings from `config/mail.php` and `config/services.php`.
- Host-specific behavior is configured by `SENDPORTAL_REGISTER`, `SENDPORTAL_PASSWORD_RESET`, and `SENDPORTAL_THROTTLE_MIDDLEWARE` in `config/sendportal-host.php`.
- `composer.json` - dependency constraints, PSR-4 autoloading, and Composer lifecycle scripts.
- `phpunit.xml.dist` - PHPUnit runner settings and test environment defaults.
- `.php-cs-fixer.dist.php` - PSR-12-oriented formatting rules.
- `.github/workflows/ci.yml` - PHP 8.2/8.3 test matrix against MySQL and PostgreSQL.
- `.github/workflows/format.yml` - pull-request PHP formatting automation.

## Platform Requirements

- PHP 8.2+ and Composer are required by `composer.json`.
- MySQL 5.7+ or PostgreSQL 9.4+ are supported application databases, per `README.md`; the CI workflow tests both in `.github/workflows/ci.yml`.
- Redis and the `phpredis` extension are required when using the configured Redis queues/cache or Laravel Horizon (`config/database.php`, `config/horizon.php`).
- PDO driver support is required for the selected configured database (`config/database.php`).
- Self-hosted PHP/Laravel deployment; no hosting-provider or deployment workflow is committed. Production worker topology is defined in `config/horizon.php`: Redis-backed queues include `default`, `sendportal-message-dispatch`, and `sendportal-webhook-process`.
- Persist `storage/` and configure a durable database. Use a process manager to run Horizon when Redis queues are selected.

<!-- GSD:stack-end -->

<!-- GSD:conventions-start source:CONVENTIONS.md -->

## Conventions

## Naming Patterns

- Use one PSR-4 class, trait, interface, or migration per PascalCase PHP file. Application code follows its namespace directory exactly: `app/Services/Workspaces/CreateWorkspace.php`, `app/Http/Requests/Workspaces/WorkspaceInvitationStoreRequest.php`, and `app/Traits/HasWorkspaces.php`.
- Name migration files with Laravel's timestamped snake_case convention, as in `database/migrations/2020_11_13_120125_create_api_tokens_table.php`.
- Use PascalCase Blade view filenames only where a component name requires it; normal views are lowercase paths such as `resources/views/workspaces/index.blade.php`.
- Use camelCase method names. Service entry points are consistently named `handle`, as in `app/Services/Workspaces/CreateWorkspace.php` and `app/Services/Workspaces/AcceptInvitation.php`.
- Use descriptive, sentence-like snake_case test method names and mark them with `/** @test */`, as in `tests/Feature/Workspaces/WorkspacesControllerTest.php`.
- Use Laravel lifecycle and framework method names unchanged (`setUp`, `rules`, `authorize`, `render`, `mount`) in files such as `tests/TestCase.php` and `app/Http/Requests/Workspaces/WorkspaceInvitationStoreRequest.php`.
- Use camelCase local variables and properties (`$workspaceName`, `$sendInvitation`, `$postData`) throughout `app/Services/Workspaces/CreateWorkspace.php` and `tests/Feature/Invitations/NewUserInvitationTest.php`.
- Use lower-case snake_case only for external array keys, database columns, route parameters, and environment variables, for example `workspace_id` in `app/Models/Workspace.php` and `SENDPORTAL_REGISTER` in `tests/Feature/Auth/AuthConfigEnabledTest.php`.
- Use PascalCase classes and interfaces; suffix HTTP validation classes with `Request`, repositories with `Repository`, and action-like services with an imperative noun/verb class name, as in `app/Http/Requests/ApiTokens/ApiTokenStoreRequest.php`, `app/Repositories/ApiTokenRepository.php`, and `app/Services/Workspaces/RemoveUserFromWorkspace.php`.
- Prefer native parameter and return types in new application code, including nullable types, as demonstrated by `app/Services/Workspaces/CreateWorkspace.php` and `app/Setup/StepInterface.php`.

## Code Style

- Apply PHP-CS-Fixer using `.php-cs-fixer.dist.php`; it enforces `@PSR12`, short arrays, alphabetical ordered imports, and no unused imports.
- Use four spaces, LF line endings, UTF-8, trailing-whitespace removal, and a final newline per `.editorconfig`.
- Add `declare(strict_types=1);` immediately after `<?php` in new application, factory, migration, route, and feature-test PHP files, following `app/Models/Workspace.php` and `tests/Feature/Setup/SetupTest.php`.
- Keep a blank line between the PHP declaration, strict-types declaration, namespace, imports, and class body, as in `app/Http/Controllers/Workspaces/WorkspacesController.php`.
- Formatting is automated by the PHP-CS-Fixer pull-request workflow in `.github/workflows/format.yml`, which runs the config at `.php-cs-fixer.dist.php` and commits formatting changes back to the PR branch.
- No separate static-analysis configuration (PHPStan or Psalm) is present; preserve the existing PHP-CS-Fixer contract rather than introducing tool-specific annotations.

## Import Organization

- Do not use source-path aliases. Resolve PHP classes through Composer PSR-4 namespaces configured in `composer.json`: `App\\` maps to `app/`, `Database\\Factories\\` to `database/factories/`, and `Tests\\` to `tests/`.

## Error Handling

- Let framework validation and authorization handle ordinary request failures. Put validation rules in a Form Request, as in `app/Http/Requests/Workspaces/WorkspaceStoreRequest.php`, and use `authorize()` or middleware for access control, as in `app/Http/Requests/Workspaces/WorkspaceInvitationStoreRequest.php` and `app/Http/Middleware/OwnsRequestedWorkspace.php`.
- Use `abort(404)` to conceal inaccessible workspace resources, following `app/Http/Middleware/OwnsRequestedWorkspace.php` and `app/Http/Middleware/RequireWorkspace.php`.
- Declare `@throws Exception` when a controller or service deliberately allows a failure to propagate, as in `app/Services/Workspaces/CreateWorkspace.php` and `app/Http/Controllers/Workspaces/WorkspaceInvitationsController.php`.
- Use a transaction for a multi-write domain operation, as in `DB::transaction()` within `app/Services/Workspaces/CreateWorkspace.php`.
- Catch exceptions only at an explicit recovery boundary. The setup UI flashes a failure message in `app/Livewire/Setup.php`; the setup entry controller treats an unavailable user table as an uninstalled application in `app/Http/Controllers/SetupController.php`.

## Logging

- Allow uncaught failures to reach the Laravel exception handler in `app/Exceptions/Handler.php` rather than adding ad-hoc `echo`, `var_dump`, or `error_log` calls.
- When an operation has a user-facing recovery path, report it through the existing response/session convention in `app/Livewire/Setup.php`, not through direct output.

## Comments

- Use short section comments to separate route groups and test phases, as in `routes/web.php` and `tests/Feature/Workspaces/WorkspacesControllerTest.php` (`given`, `when`, `then`).
- Add comments only for non-obvious framework behavior or business intent, such as the automatic invitation acceptance explanation in `tests/Feature/Workspaces/WorkspaceInvitationsControllerTest.php`.
- Use PHPDoc rather than JSDoc/TSDoc. Document Eloquent model magic properties in model class docblocks (`app/Models/Workspace.php`) and add inline `/** @var Type $value */` annotations where inference is weak (`app/Services/Workspaces/CreateWorkspace.php`).
- Document exception contracts with `@throws` and add a brief behavior description for non-trivial public methods, as in `app/Models/Workspace.php`.

## Function Design

- Keep HTTP controllers thin: inject dependencies, delegate work, then return a view or redirect, as in `app/Http/Controllers/Workspaces/WorkspaceInvitationsController.php`.
- Put cohesive domain mutations in a focused service class with a public `handle()` method, as in `app/Services/Workspaces/AddWorkspaceMember.php` and `app/Services/Workspaces/SendInvitation.php`.
- Prefer constructor injection for repositories and services, as in `app/Http/Controllers/Workspaces/WorkspacesController.php`.
- Use Laravel request injection and route-model binding in controller actions, as in `app/Http/Controllers/Workspaces/WorkspacesController.php`.
- Return explicit framework response types from controllers where established (`ViewContract`, `RedirectResponse`) as in `app/Http/Controllers/Workspaces/WorkspacesController.php`.
- Use `void` for mutation-only services (`app/Services/Workspaces/RemoveUserFromWorkspace.php`) and return the created/updated model or domain result when callers need it (`app/Services/Workspaces/CreateWorkspace.php`, `app/Services/Workspaces/AcceptInvitation.php`).

## Module Design

- PHP classes are autoloaded directly by namespace; define the class in its own file under the appropriate `app/` layer, as with `app/Repositories/WorkspacesRepository.php`.
- Compose shared model behavior through traits, following `app/Traits/HasWorkspaces.php` used by `app/Models/User.php`.
- Not used. Do not add PHP barrel/export files; import the concrete namespaced class directly, as in `app/Services/Workspaces/CreateWorkspace.php`.

<!-- GSD:conventions-end -->

<!-- GSD:architecture-start source:ARCHITECTURE.md -->

## Architecture

## System Overview

```text

```

## Component Responsibilities

| Component | Responsibility | File |
|-----------|----------------|------|
| Application bootstrap | Instantiates Laravel and binds the HTTP, console, and exception-handler contracts. | `bootstrap/app.php` |
| HTTP kernel | Applies global and route-group middleware, including session, CSRF, route binding, and throttling. | `app/Http/Kernel.php` |
| Route provider | Mounts the web route file in the `web` group and the API file under `/api` in the `api` group. | `app/Providers/RouteServiceProvider.php` |
| Host routes | Owns authentication, profiles, workspace administration, setup, API-token management, and core-package route delegation. | `routes/web.php`, `routes/api.php` |
| Core integration | Supplies the current-workspace resolver and injected header/sidebar Blade fragments; registers the setup Livewire component. | `app/Providers/AppServiceProvider.php` |
| Workspace domain | Models users, workspaces, membership roles, invitations, and API tokens. | `app/Models/User.php`, `app/Models/Workspace.php`, `app/Models/Invitation.php`, `app/Models/ApiToken.php` |
| Workspace operations | Encapsulates creation, membership, invitation acceptance/removal, and invitation email dispatch. | `app/Services/Workspaces/` |
| Persistence adapters | Wraps Eloquent through SendPortal Core base repositories. | `app/Repositories/WorkspacesRepository.php`, `app/Repositories/ApiTokenRepository.php` |
| Initial setup | Implements an ordered Livewire wizard with concrete steps for environment, key, URL, database, migrations, and admin creation. | `app/Livewire/Setup.php`, `app/Setup/` |
| Host UI | Provides Blade pages for host-owned account and workspace features; extends the package layouts where appropriate. | `resources/views/` |

## Pattern Overview

- Keep email-marketing features inside the `mettle/sendportal-core` package; the host invokes the package route registrars in `routes/web.php` and `routes/api.php`.
- Put host-owned request handling in thin controllers under `app/Http/Controllers/`, with validation in `app/Http/Requests/` and multi-step domain writes in `app/Services/Workspaces/`.
- Resolve tenancy through one application-level `Sendportal` workspace-ID resolver in `app/Providers/AppServiceProvider.php`; every protected core route is gated by `app/Http/Middleware/RequireWorkspace.php`.
- Use Laravel's container for constructor-injected controllers/services and Eloquent models for host-owned persistence. The setup wizard resolves its selected `StepInterface` implementation from the container in `app/Livewire/Setup.php`.

## Layers

- Purpose: Construct Laravel, register framework/application providers, mount route groups, and configure package integration points.
- Location: `bootstrap/app.php`, `config/app.php`, `app/Providers/`.
- Contains: Contract bindings, provider boot hooks, route registration, event listener mapping, and Horizon authorization.
- Depends on: Laravel framework and package service providers configured by `composer.json` and `config/app.php`.
- Used by: `public/index.php` for HTTP and `artisan` for CLI.
- Purpose: Translate web/API requests into validated controller actions while applying authentication, verification, tenancy, ownership, and CSRF/session controls.
- Location: `routes/`, `app/Http/Kernel.php`, `app/Http/Middleware/`, `app/Http/Controllers/`, `app/Http/Requests/`.
- Contains: Named routes, conventional Laravel controllers, `FormRequest` validation, route-model binding, and middleware.
- Depends on: Models, services, repositories, Blade, Laravel authentication, and `Sendportal` facade.
- Used by: Browser clients and clients of the package-provided API routes.
- Purpose: Define the host's multi-workspace membership model and guard mutations that change membership or invitations.
- Location: `app/Models/`, `app/Traits/HasWorkspaces.php`, `app/Services/Workspaces/`, `app/Repositories/`.
- Contains: `User`, `Workspace`, `Invitation`, and `ApiToken` models; workspace service classes expose `handle(...)` operations.
- Depends on: Eloquent relations, database transactions, Laravel Mail, and SendPortal Core repository/model base classes.
- Used by: Auth/workspace controllers, tenancy resolver, middleware, factories, and feature tests.
- Purpose: Supply SendPortal Core's newsletter, subscriber, campaign, reporting, and public endpoints while letting the host define the current workspace and UI insertion points.
- Location: Package dependency `mettle/sendportal-core`, invoked from `routes/web.php`, `routes/api.php`, and `app/Providers/AppServiceProvider.php`.
- Contains: Package-provided web/API routes, tenant repositories/models, layouts, and the `Sendportal` facade.
- Depends on: The host's workspace resolver and the Laravel runtime.
- Used by: Authenticated host users and API-token callers after `RequireWorkspace` succeeds.
- Purpose: Render host-owned pages and setup screens.
- Location: `resources/views/`.
- Contains: Blade views grouped by auth, profile, workspaces, users, setup, API tokens, and package-layout extension fragments.
- Depends on: Controller view data, the authenticated user, named routes, and package layouts such as `sendportal::layouts.app`.
- Used by: Host controllers and the Livewire setup component.
- Purpose: Install/upgrade the host application and coordinate database migrations and published package assets.
- Location: `app/Console/Kernel.php`, `app/Console/Commands/InstallApplication.php`, `app/Console/Commands/UpgradeApplication.php`, `app/Traits/HasSendportalMigrationHandlers.php`.
- Contains: Artisan command signatures `sp:install` and `sp:upgrade`, shared migration helpers, and interactive configuration flow.
- Depends on: Laravel Artisan/migrator and `SendportalBaseServiceProvider`.
- Used by: Operators running `artisan`.

## Data Flow

### Primary protected SendPortal web request

### Host workspace management request

### API token tenancy flow

### Initial application setup flow

- Laravel sessions and cookies carry browser authentication through the `web` middleware group in `app/Http/Kernel.php`.
- `User::$activeWorkspace` is request-local model state, while `users.current_workspace_id` persists the current workspace in `app/Traits/HasWorkspaces.php`.
- The Livewire `Setup` component maintains wizard position and step completion in its public `active` and `steps` properties in `app/Livewire/Setup.php`.

## Key Abstractions

- Purpose: Defines the tenant ID that SendPortal Core uses for every scoped operation.
- Examples: `app/Providers/AppServiceProvider.php`, `app/Http/Middleware/RequireWorkspace.php`, `app/Traits/HasWorkspaces.php`, `app/Models/ApiToken.php`.
- Pattern: Configure one callback on the package facade; resolve from authenticated user first, then API token, and reject no-tenant requests before package handling.
- Purpose: Give business operations an explicit transaction/side-effect boundary rather than putting membership logic in controllers.
- Examples: `app/Services/Workspaces/CreateWorkspace.php`, `app/Services/Workspaces/AcceptInvitation.php`, `app/Services/Workspaces/SendInvitation.php`.
- Pattern: Stateless class with a public `handle(...)` method and constructor-injected collaborators; use `DB::transaction(...)` when an operation spans multiple writes.
- Purpose: Adapt host models to SendPortal Core's shared Eloquent repository APIs.
- Examples: `app/Repositories/WorkspacesRepository.php`, `app/Repositories/ApiTokenRepository.php`.
- Pattern: Extend a Core base repository and set `$modelName`; put reusable custom queries in the repository.
- Purpose: Make installer stages pluggable while keeping the Livewire wizard independent of each concrete stage.
- Examples: `app/Setup/StepInterface.php`, `app/Setup/Env.php`, `app/Setup/Database.php`, `app/Setup/Admin.php`.
- Pattern: Each step implements `check()` and `run(?array $input)` and may expose a `validate(...)` method; `app/Livewire/Setup.php` resolves the configured class dynamically.

## Entry Points

- Location: `public/index.php`.
- Triggers: Every web request directed to the Laravel public directory.
- Responsibilities: Requires Composer autoloading, boots the application, delegates request handling to the HTTP kernel, sends the response, and terminates the request lifecycle.
- Location: `artisan`.
- Triggers: Operator/automation commands such as `php artisan sp:install` and `php artisan sp:upgrade`.
- Responsibilities: Boots the same application container and delegates to `app/Console/Kernel.php`.
- Location: `routes/web.php` and `routes/api.php`.
- Triggers: `app/Providers/RouteServiceProvider.php` during provider boot.
- Responsibilities: Register host endpoints, middleware boundaries, and the package-provided route groups.

## Architectural Constraints

- **Request lifecycle:** Laravel handles each HTTP request through the single PHP request lifecycle wired in `public/index.php` and `bootstrap/app.php`; do not keep request-specific data in static/global state.
- **Tenancy:** Every feature that operates on SendPortal Core tenant data must obtain its workspace through `Sendportal::currentWorkspaceId()` as configured in `app/Providers/AppServiceProvider.php` and must be protected with `app/Http/Middleware/RequireWorkspace.php`.
- **Ownership:** Workspace membership is insufficient for administration. Apply `app/Http/Middleware/OwnsCurrentWorkspace.php` for current-workspace user management and `app/Http/Middleware/OwnsRequestedWorkspace.php` for a route-bound workspace mutation.
- **Host/package boundary:** Add host shell features in `app/` and `resources/views/`; delegate core email-marketing endpoints through `Sendportal::webRoutes()`/`apiRoutes()` in `routes/`. Do not duplicate package routes or tenant models in this repository.
- **Schema ownership:** Host migrations in `database/migrations/` define host identity/workspace tables. Package tables and code remain owned by the dependency registered in `composer.json`.
- **Frontend boundary:** This repository contains Blade templates only; host pages commonly extend package-provided `sendportal::layouts.*` layouts, as in `resources/views/users/index.blade.php`.
- **Circular imports:** No circular PHP class dependency is indicated by the host namespaces. Laravel's service container resolves service/controller dependencies at runtime from `app/`.

## Anti-Patterns

### Bypassing the workspace boundary

### Putting multi-write workspace logic in a controller

## Error Handling

- `FormRequest` classes under `app/Http/Requests/` validate controller input before actions run.
- `app/Http/Middleware/RequireWorkspace.php` returns `401` for missing API tenancy and aborts host web requests with `404`.
- Workspace service methods declare exceptional cases and use transactions where required, for example `app/Services/Workspaces/CreateWorkspace.php`.
- `app/Livewire/Setup.php` converts setup-step exceptions into flashed user-facing errors and rethrows validation failures so Livewire displays them.
- The global exception boundary is `app/Exceptions/Handler.php`.

## Cross-Cutting Concerns

<!-- GSD:architecture-end -->

<!-- GSD:skills-start source:skills/ -->

## Project Skills

No project skills found. Add skills to any of: `.claude/skills/`, `.agents/skills/`, `.cursor/skills/`, `.github/skills/`, or `.codex/skills/` with a `SKILL.md` index file.
<!-- GSD:skills-end -->

<!-- GSD:workflow-start source:GSD defaults -->

## GSD Workflow Enforcement

Before using Edit, Write, or other file-changing tools, start work through a GSD command so planning artifacts and execution context stay in sync.

Use these entry points:

- `$gsd-quick` for small fixes, doc updates, and ad-hoc tasks
- `$gsd-debug` for investigation and bug fixing
- `$gsd-execute-phase` for planned phase work

Do not make direct repo edits outside a GSD workflow unless the user explicitly asks to bypass it.
<!-- GSD:workflow-end -->

<!-- GSD:profile-start -->

## Developer Profile

> Profile not yet configured. Run `$gsd-profile-user` to generate your developer profile.
> This section is managed by `generate-claude-profile` -- do not edit manually.
<!-- GSD:profile-end -->
