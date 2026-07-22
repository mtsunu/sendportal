<!-- refreshed: 2026-07-22 -->
# Architecture

**Analysis Date:** 2026-07-22

## System Overview

```text
┌────────────────────────────────────────────────────────────────────┐
│ Laravel HTTP / CLI entry points                                     │
│ `public/index.php`, `artisan`, `bootstrap/app.php`                  │
├────────────────┬───────────────────────┬───────────────────────────┤
│ Host web routes│ Host API routes       │ Console / setup UI        │
│ `routes/web.php│ `routes/api.php`      │ `app/Console`,            │
│ `              │                       │ `app/Livewire/Setup.php` │
└───────┬────────┴──────────┬────────────┴─────────────┬─────────────┘
        │                   │                          │
        ▼                   ▼                          ▼
┌────────────────────────────────────────────────────────────────────┐
│ Host application layer                                              │
│ controllers → FormRequests → workspace services / repositories      │
│ `app/Http`, `app/Services/Workspaces`, `app/Repositories`           │
└───────────────────────┬────────────────────────────────────────────┘
                        │ configures and delegates core behavior
                        ▼
┌────────────────────────────────────────────────────────────────────┐
│ SendPortal Core package                                              │
│ registered through `mettle/sendportal-core`; routes exposed by       │
│ `Sendportal::webRoutes()`, `apiRoutes()`, and public-route methods  │
└───────────────────────┬────────────────────────────────────────────┘
                        │
                        ▼
┌────────────────────────────────────────────────────────────────────┐
│ Persistence and external adapters                                    │
│ Eloquent models/migrations, mail, queue/Horizon, configured cache   │
│ `app/Models`, `database/migrations`, `config/mail.php`,             │
│ `config/queue.php`, `config/horizon.php`                            │
└────────────────────────────────────────────────────────────────────┘
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

**Overall:** Laravel modular-monolith host application that composes the SendPortal Core package and owns the identity/workspace shell around it.

**Key Characteristics:**

- Keep email-marketing features inside the `mettle/sendportal-core` package; the host invokes the package route registrars in `routes/web.php` and `routes/api.php`.
- Put host-owned request handling in thin controllers under `app/Http/Controllers/`, with validation in `app/Http/Requests/` and multi-step domain writes in `app/Services/Workspaces/`.
- Resolve tenancy through one application-level `Sendportal` workspace-ID resolver in `app/Providers/AppServiceProvider.php`; every protected core route is gated by `app/Http/Middleware/RequireWorkspace.php`.
- Use Laravel's container for constructor-injected controllers/services and Eloquent models for host-owned persistence. The setup wizard resolves its selected `StepInterface` implementation from the container in `app/Livewire/Setup.php`.

## Layers

**Bootstrap and providers:**

- Purpose: Construct Laravel, register framework/application providers, mount route groups, and configure package integration points.
- Location: `bootstrap/app.php`, `config/app.php`, `app/Providers/`.
- Contains: Contract bindings, provider boot hooks, route registration, event listener mapping, and Horizon authorization.
- Depends on: Laravel framework and package service providers configured by `composer.json` and `config/app.php`.
- Used by: `public/index.php` for HTTP and `artisan` for CLI.

**HTTP boundary:**

- Purpose: Translate web/API requests into validated controller actions while applying authentication, verification, tenancy, ownership, and CSRF/session controls.
- Location: `routes/`, `app/Http/Kernel.php`, `app/Http/Middleware/`, `app/Http/Controllers/`, `app/Http/Requests/`.
- Contains: Named routes, conventional Laravel controllers, `FormRequest` validation, route-model binding, and middleware.
- Depends on: Models, services, repositories, Blade, Laravel authentication, and `Sendportal` facade.
- Used by: Browser clients and clients of the package-provided API routes.

**Workspace domain:**

- Purpose: Define the host's multi-workspace membership model and guard mutations that change membership or invitations.
- Location: `app/Models/`, `app/Traits/HasWorkspaces.php`, `app/Services/Workspaces/`, `app/Repositories/`.
- Contains: `User`, `Workspace`, `Invitation`, and `ApiToken` models; workspace service classes expose `handle(...)` operations.
- Depends on: Eloquent relations, database transactions, Laravel Mail, and SendPortal Core repository/model base classes.
- Used by: Auth/workspace controllers, tenancy resolver, middleware, factories, and feature tests.

**Package feature layer:**

- Purpose: Supply SendPortal Core's newsletter, subscriber, campaign, reporting, and public endpoints while letting the host define the current workspace and UI insertion points.
- Location: Package dependency `mettle/sendportal-core`, invoked from `routes/web.php`, `routes/api.php`, and `app/Providers/AppServiceProvider.php`.
- Contains: Package-provided web/API routes, tenant repositories/models, layouts, and the `Sendportal` facade.
- Depends on: The host's workspace resolver and the Laravel runtime.
- Used by: Authenticated host users and API-token callers after `RequireWorkspace` succeeds.

**Presentation:**

- Purpose: Render host-owned pages and setup screens.
- Location: `resources/views/`.
- Contains: Blade views grouped by auth, profile, workspaces, users, setup, API tokens, and package-layout extension fragments.
- Depends on: Controller view data, the authenticated user, named routes, and package layouts such as `sendportal::layouts.app`.
- Used by: Host controllers and the Livewire setup component.

**CLI and maintenance:**

- Purpose: Install/upgrade the host application and coordinate database migrations and published package assets.
- Location: `app/Console/Kernel.php`, `app/Console/Commands/InstallApplication.php`, `app/Console/Commands/UpgradeApplication.php`, `app/Traits/HasSendportalMigrationHandlers.php`.
- Contains: Artisan command signatures `sp:install` and `sp:upgrade`, shared migration helpers, and interactive configuration flow.
- Depends on: Laravel Artisan/migrator and `SendportalBaseServiceProvider`.
- Used by: Operators running `artisan`.

## Data Flow

### Primary protected SendPortal web request

1. `public/index.php` boots Laravel through `bootstrap/app.php`, which resolves `App\Http\Kernel`.
2. `app/Http/Kernel.php` applies the global stack and `web` group (cookies, session, CSRF, and bindings).
3. `app/Providers/RouteServiceProvider.php:61` mounts `routes/web.php` in the controller namespace.
4. `routes/web.php:108` applies `auth`, `verified`, and `RequireWorkspace` before delegating to `Sendportal::webRoutes()`.
5. `app/Http/Middleware/RequireWorkspace.php:18` calls `Sendportal::currentWorkspaceId()` and stops a request without a resolvable workspace.
6. `app/Providers/AppServiceProvider.php:30` resolves the workspace from the signed-in user's `currentWorkspaceId()` or an API token.
7. SendPortal Core performs the tenant-scoped action and renders its response; host extension fragments are resolved from `resources/views/layouts/` through `app/Providers/AppServiceProvider.php:51`.

### Host workspace management request

1. `routes/web.php:80` maps workspace administration behind `auth`, `verified`, and `RequireWorkspace`; user-management routes add `OwnsCurrentWorkspace` at `routes/web.php:57`.
2. The controller receives a validated `FormRequest` or route-bound model. For example, `app/Http/Controllers/Workspaces/WorkspacesController.php:51` delegates workspace creation.
3. `app/Services/Workspaces/CreateWorkspace.php:33` opens a database transaction, persists through `WorkspacesRepository`, and attaches the owner with `AddWorkspaceMember`.
4. The model/repository layer writes the `workspaces` and `workspace_users` tables created by `database/migrations/2017_04_11_000000_create_workspaces_table.php`.
5. The controller redirects to a named host route and its Blade page in `resources/views/workspaces/` renders the refreshed state.

### API token tenancy flow

1. `app/Providers/RouteServiceProvider.php:75` mounts `routes/api.php` beneath `/api` with the `api` middleware group.
2. `routes/api.php:9` adds the configured throttle and `RequireWorkspace` before core authenticated API routes are registered at `routes/api.php:14`.
3. If no authenticated user supplies a workspace, `app/Providers/AppServiceProvider.php:39` reads the bearer token or `api_token` request parameter.
4. `app/Models/ApiToken.php` resolves its `workspace_id`; that ID becomes the current tenant for SendPortal Core.

### Initial application setup flow

1. `routes/web.php:19` exposes the `SetupController`, which shows `resources/views/setup/index.blade.php` only while no user exists.
2. `app/Providers/AppServiceProvider.php:63` registers the `setup` Livewire component.
3. `app/Livewire/Setup.php:22` selects ordered `StepInterface` classes in `app/Setup/`; each is checked and run through the service container at `app/Livewire/Setup.php:115`.
4. Individual setup steps update configuration, run migrations, and create the first user/workspace; their Blade partials live in `resources/views/setup/steps/`.

**State Management:**

- Laravel sessions and cookies carry browser authentication through the `web` middleware group in `app/Http/Kernel.php`.
- `User::$activeWorkspace` is request-local model state, while `users.current_workspace_id` persists the current workspace in `app/Traits/HasWorkspaces.php`.
- The Livewire `Setup` component maintains wizard position and step completion in its public `active` and `steps` properties in `app/Livewire/Setup.php`.

## Key Abstractions

**Current workspace resolver:**

- Purpose: Defines the tenant ID that SendPortal Core uses for every scoped operation.
- Examples: `app/Providers/AppServiceProvider.php`, `app/Http/Middleware/RequireWorkspace.php`, `app/Traits/HasWorkspaces.php`, `app/Models/ApiToken.php`.
- Pattern: Configure one callback on the package facade; resolve from authenticated user first, then API token, and reject no-tenant requests before package handling.

**Workspace services:**

- Purpose: Give business operations an explicit transaction/side-effect boundary rather than putting membership logic in controllers.
- Examples: `app/Services/Workspaces/CreateWorkspace.php`, `app/Services/Workspaces/AcceptInvitation.php`, `app/Services/Workspaces/SendInvitation.php`.
- Pattern: Stateless class with a public `handle(...)` method and constructor-injected collaborators; use `DB::transaction(...)` when an operation spans multiple writes.

**Repositories:**

- Purpose: Adapt host models to SendPortal Core's shared Eloquent repository APIs.
- Examples: `app/Repositories/WorkspacesRepository.php`, `app/Repositories/ApiTokenRepository.php`.
- Pattern: Extend a Core base repository and set `$modelName`; put reusable custom queries in the repository.

**Setup steps:**

- Purpose: Make installer stages pluggable while keeping the Livewire wizard independent of each concrete stage.
- Examples: `app/Setup/StepInterface.php`, `app/Setup/Env.php`, `app/Setup/Database.php`, `app/Setup/Admin.php`.
- Pattern: Each step implements `check()` and `run(?array $input)` and may expose a `validate(...)` method; `app/Livewire/Setup.php` resolves the configured class dynamically.

## Entry Points

**HTTP front controller:**

- Location: `public/index.php`.
- Triggers: Every web request directed to the Laravel public directory.
- Responsibilities: Requires Composer autoloading, boots the application, delegates request handling to the HTTP kernel, sends the response, and terminates the request lifecycle.

**Artisan CLI:**

- Location: `artisan`.
- Triggers: Operator/automation commands such as `php artisan sp:install` and `php artisan sp:upgrade`.
- Responsibilities: Boots the same application container and delegates to `app/Console/Kernel.php`.

**Route registries:**

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

**What happens:** A route invokes SendPortal Core or a tenant repository without the workspace guard and resolver path in `routes/web.php`, `routes/api.php`, and `app/Http/Middleware/RequireWorkspace.php`.
**Why it's wrong:** Core operations lose the tenant context established in `app/Providers/AppServiceProvider.php`, which causes a controlled request failure and can undermine consistent isolation.
**Do this instead:** Put tenant-aware endpoints in a group containing `RequireWorkspace`, then obtain the ID through `Sendportal::currentWorkspaceId()` as shown in `app/Http/Controllers/Auth/ApiTokenController.php`.

### Putting multi-write workspace logic in a controller

**What happens:** A controller directly creates a workspace and attaches members instead of using the existing operation classes in `app/Services/Workspaces/`.
**Why it's wrong:** Membership and workspace creation are a single consistency boundary and must maintain the relationship defined by `database/migrations/2017_04_11_000000_create_workspaces_table.php`.
**Do this instead:** Add or extend a `handle(...)` service in `app/Services/Workspaces/`; wrap coupled database writes in `DB::transaction(...)` following `app/Services/Workspaces/CreateWorkspace.php`.

## Error Handling

**Strategy:** Use Laravel validation/redirect behavior at the HTTP boundary, exceptions for service failures, transaction rollback for grouped persistence, and route-level HTTP responses/aborts for authorization or tenancy failures.

**Patterns:**

- `FormRequest` classes under `app/Http/Requests/` validate controller input before actions run.
- `app/Http/Middleware/RequireWorkspace.php` returns `401` for missing API tenancy and aborts host web requests with `404`.
- Workspace service methods declare exceptional cases and use transactions where required, for example `app/Services/Workspaces/CreateWorkspace.php`.
- `app/Livewire/Setup.php` converts setup-step exceptions into flashed user-facing errors and rethrows validation failures so Livewire displays them.
- The global exception boundary is `app/Exceptions/Handler.php`.

## Cross-Cutting Concerns

**Logging:** Laravel logging is configured in `config/logging.php`; host domain classes do not introduce a separate logger abstraction.

**Validation:** Browser mutations use `FormRequest` classes in `app/Http/Requests/`; registration uses Laravel validation plus `app/Rules/ValidInvitation.php`; setup stages validate their own data in `app/Setup/`.

**Authentication:** Laravel's session guard and email-verification middleware are configured in `config/auth.php`, applied in `routes/web.php`, and use `app/Models/User.php` as the provider model. Workspace ownership is enforced by host middleware under `app/Http/Middleware/`.

---

*Architecture analysis: 2026-07-22*
