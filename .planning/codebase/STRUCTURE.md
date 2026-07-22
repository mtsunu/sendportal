# Codebase Structure

**Analysis Date:** 2026-07-22

## Directory Layout

```text
sendportal/
├── app/                         # Host Laravel application code
│   ├── Console/                  # Artisan kernel and installer/upgrade commands
│   ├── Exceptions/               # Global exception handler
│   ├── Http/                     # Controllers, middleware, requests, resources
│   ├── Livewire/                 # Interactive setup component
│   ├── Models/                   # Host Eloquent models
│   ├── Providers/                # Laravel/provider and core-package integration setup
│   ├── Repositories/             # Host persistence adapters over Core repositories
│   ├── Services/Workspaces/      # Workspace and invitation use cases
│   ├── Setup/                    # Setup wizard step implementations
│   └── Traits/                   # Reusable workspace, invitation, and command behavior
├── bootstrap/                    # Application construction and framework cache directory
├── config/                       # Laravel and host configuration maps
├── database/                     # Host schema migrations, factories, and seeders
├── public/                       # HTTP front controller and public static assets
├── resources/views/              # Host Blade pages and package-layout extension fragments
├── routes/                       # Web/API/console/broadcast route registries
├── tests/                        # PHPUnit base support, feature tests, and unit tests
├── artisan                        # CLI entry point
├── composer.json                 # PHP dependency/autoload definition
├── phpunit.xml.dist              # PHPUnit suite/environment configuration
└── server.php                    # PHP built-in development-server router
```

## Directory Purposes

**`app/Http/Controllers/`:**

- Purpose: Own the host application's request actions; keep controllers thin and delegate domain writes to services/repositories.
- Contains: Base `Controller`, `SetupController`, authentication controllers in `Auth/`, and workspace controllers in `Workspaces/`.
- Key files: `app/Http/Controllers/Workspaces/WorkspacesController.php`, `app/Http/Controllers/Auth/ApiTokenController.php`, `app/Http/Controllers/Auth/RegisterController.php`.

**`app/Http/Requests/`:**

- Purpose: Authorize and validate host HTTP form input before a controller action executes.
- Contains: Top-level account/profile request classes plus `ApiTokens/` and `Workspaces/` feature subdirectories.
- Key files: `app/Http/Requests/ProfileUpdateRequest.php`, `app/Http/Requests/ApiTokens/ApiTokenStoreRequest.php`, `app/Http/Requests/Workspaces/WorkspaceStoreRequest.php`.

**`app/Http/Middleware/`:**

- Purpose: Apply application-wide request controls and workspace-specific authorization/tenancy guards.
- Contains: Laravel middleware customizations and host `RequireWorkspace`, `OwnsCurrentWorkspace`, and `OwnsRequestedWorkspace` middleware.
- Key files: `app/Http/Kernel.php`, `app/Http/Middleware/RequireWorkspace.php`, `app/Http/Middleware/OwnsCurrentWorkspace.php`.

**`app/Services/Workspaces/`:**

- Purpose: Hold host workspace business operations that combine models, repositories, transactions, and/or mail side effects.
- Contains: One class per operation using a public `handle(...)` method.
- Key files: `app/Services/Workspaces/CreateWorkspace.php`, `app/Services/Workspaces/SendInvitation.php`, `app/Services/Workspaces/AcceptInvitation.php`.

**`app/Repositories/`:**

- Purpose: Wrap host Eloquent models in SendPortal Core repository abstractions and host reusable database queries.
- Contains: `WorkspacesRepository` and tenant-scoped `ApiTokenRepository`.
- Key files: `app/Repositories/WorkspacesRepository.php`, `app/Repositories/ApiTokenRepository.php`.

**`app/Models/`:**

- Purpose: Define host-owned persistence and Eloquent relationships.
- Contains: `User`, `Workspace`, `Invitation`, and `ApiToken`.
- Key files: `app/Models/User.php`, `app/Models/Workspace.php`, `app/Models/Invitation.php`, `app/Models/ApiToken.php`.

**`app/Traits/`:**

- Purpose: Share behavior across host models, validation rules, and Artisan commands without adding a separate service layer.
- Contains: Workspace selection/membership behavior, invitation lookup/expiry checks, and command/migration utilities.
- Key files: `app/Traits/HasWorkspaces.php`, `app/Traits/ChecksInvitations.php`, `app/Traits/HasSendportalMigrationHandlers.php`.

**`app/Setup/` and `app/Livewire/`:**

- Purpose: Implement the first-run setup wizard.
- Contains: `StepInterface`, concrete setup steps, the environment-writing trait, and the `Setup` Livewire component.
- Key files: `app/Livewire/Setup.php`, `app/Setup/StepInterface.php`, `app/Setup/Database.php`, `app/Setup/Admin.php`.

**`app/Providers/`:**

- Purpose: Register the host application with Laravel and SendPortal Core.
- Contains: Application boot configuration, route grouping, event listeners, optional broadcasting routes, auth policies, and Horizon access policy.
- Key files: `app/Providers/AppServiceProvider.php`, `app/Providers/RouteServiceProvider.php`, `app/Providers/HorizonServiceProvider.php`.

**`app/Console/`:**

- Purpose: Register and implement host-specific Artisan maintenance commands.
- Contains: Console kernel plus `sp:install` and `sp:upgrade` commands.
- Key files: `app/Console/Kernel.php`, `app/Console/Commands/InstallApplication.php`, `app/Console/Commands/UpgradeApplication.php`.

**`routes/`:**

- Purpose: Define host route groups and mount the SendPortal Core route registrars.
- Contains: Web, API, console, and channel route files.
- Key files: `routes/web.php`, `routes/api.php`, `routes/console.php`, `routes/channels.php`.

**`resources/views/`:**

- Purpose: Render all host-owned browser pages using Blade.
- Contains: Feature folders for `auth/`, `profile/`, `workspaces/`, `users/`, `api-tokens/`, and setup wizard pages; `layouts/` holds HTML fragments injected into Core layouts.
- Key files: `resources/views/auth/login.blade.php`, `resources/views/workspaces/index.blade.php`, `resources/views/livewire/setup.blade.php`, `resources/views/layouts/sidebar/manageUsersMenuItem.blade.php`.

**`database/`:**

- Purpose: Version host schema and generate test data.
- Contains: Timestamped migrations, model factories, and a seed entry point.
- Key files: `database/migrations/2017_04_11_000000_create_workspaces_table.php`, `database/migrations/2017_04_11_100000_create_invitations_table.php`, `database/factories/WorkspaceFactory.php`.

**`config/`:**

- Purpose: Define Laravel runtime configuration and host-level SendPortal controls.
- Contains: Framework service configuration plus the host auth and API-throttle policy map.
- Key files: `config/app.php`, `config/auth.php`, `config/sendportal-host.php`, `config/queue.php`, `config/horizon.php`.

**`tests/`:**

- Purpose: Exercise host workflows with PHPUnit, test factories, and Laravel's application test harness.
- Contains: `Feature/` scenarios grouped by auth, setup, workspaces, and invitations; `Unit/` for isolated tests; shared support classes at the directory root.
- Key files: `tests/TestCase.php`, `tests/TestSupportTrait.php`, `tests/Feature/Workspaces/WorkspacesControllerTest.php`, `tests/Feature/Auth/WorkspaceApiTokenTest.php`.

## Key File Locations

**Entry Points:**

- `public/index.php`: HTTP front controller that starts Laravel's request lifecycle.
- `artisan`: Artisan CLI front controller.
- `bootstrap/app.php`: Constructs the Laravel container and binds HTTP/console kernels plus the exception handler.
- `app/Providers/RouteServiceProvider.php`: Attaches `routes/web.php` and `routes/api.php` to their middleware groups.
- `routes/web.php`: Host browser routes and SendPortal Core web-route delegation.
- `routes/api.php`: Core API route delegation and workspace/throttle boundary.

**Configuration:**

- `composer.json`: Package dependencies, PSR-4 autoloading, and Composer lifecycle scripts.
- `config/app.php`: Provider registration and base Laravel runtime settings.
- `config/sendportal-host.php`: Registration, password-reset, locale, and API throttling policy.
- `config/database.php`: Database connection configuration.
- `config/mail.php`: Laravel mail transport configuration.
- `config/queue.php` and `config/horizon.php`: Async job and Horizon settings.
- `.env.example`: Environment variable template; do not put implementation logic or secrets in this file.

**Core Logic:**

- `app/Providers/AppServiceProvider.php`: Configures the package's tenant resolver and host UI injection points.
- `app/Traits/HasWorkspaces.php`: Defines current-workspace selection and membership behavior on `User`.
- `app/Http/Middleware/RequireWorkspace.php`: Stops access to a tenant route without a current workspace.
- `app/Services/Workspaces/`: Workspace/member/invitation use cases.
- `app/Repositories/`: Eloquent repository adaptation.
- `app/Livewire/Setup.php` and `app/Setup/`: Setup-wizard orchestration and stages.

**Testing:**

- `phpunit.xml.dist`: Test suite and test-environment settings.
- `tests/CreatesApplication.php`: Builds the Laravel app for tests.
- `tests/TestCase.php`: Shared base test case.
- `tests/Feature/`: Browser-level HTTP and database behavior of host features.
- `database/factories/`: Factory state for test data.

## Naming Conventions

**Files:**

- Use one PascalCase PHP class per file with the class name matching the filename: `app/Services/Workspaces/CreateWorkspace.php` contains `CreateWorkspace`.
- Group controllers and requests by feature namespace: `app/Http/Controllers/Workspaces/WorkspaceUsersController.php` and `app/Http/Requests/Workspaces/WorkspaceInvitationStoreRequest.php`.
- Name write-request validators with the `Request` suffix: `app/Http/Requests/ChangePasswordRequest.php`.
- Name business operation classes with imperative nouns/verbs and expose `handle(...)`: `app/Services/Workspaces/AddWorkspaceMember.php`.
- Name Blade views in lowercase kebab/camel as existing route/view names require; nested view paths mirror dot notation, for example `resources/views/profile/password/edit.blade.php` is `profile.password.edit`.
- Keep database migrations timestamp-prefixed and descriptive: `database/migrations/2020_11_13_120125_create_api_tokens_table.php`.

**Directories:**

- Use PascalCase feature subdirectories when they map to PHP namespaces, such as `app/Http/Controllers/Auth/` and `app/Services/Workspaces/`.
- Use lowercase view and route directories, such as `resources/views/workspaces/` and `routes/`.
- Keep cross-cutting Laravel concepts at their conventional `app/` locations instead of creating a parallel `src/` tree.

## Where to Add New Code

**New host feature:**

- Primary routes: Add browser endpoints in `routes/web.php`; add API endpoints in `routes/api.php` only when they belong to the host rather than SendPortal Core.
- Browser controller: Place it in a feature namespace below `app/Http/Controllers/`, for example `app/Http/Controllers/Workspaces/` for workspace administration.
- Input validation: Add a `FormRequest` under the matching `app/Http/Requests/<Feature>/` directory and type-hint it in the controller.
- Server-rendered UI: Add the matching Blade page under `resources/views/<feature>/` and use a dot-path view name.
- Tests: Put HTTP/database coverage in `tests/Feature/<Feature>/` and create model state through `database/factories/`.

**New workspace-domain operation:**

- Implementation: Add a focused service under `app/Services/Workspaces/` with a public `handle(...)` method.
- Persistence query: Add it to `app/Repositories/WorkspacesRepository.php` or `app/Repositories/ApiTokenRepository.php` when it is reusable; otherwise use an Eloquent relation in the service.
- Authorization: Reuse `app/Http/Middleware/RequireWorkspace.php` for tenant context and select `OwnsCurrentWorkspace` or `OwnsRequestedWorkspace` from `app/Http/Middleware/` for management mutations.
- Schema: Add a new timestamped host migration under `database/migrations/`; do not add a host migration for a table owned by SendPortal Core.

**New SendPortal Core extension point:**

- Route integration: Keep package route registration in `routes/web.php` or `routes/api.php` and preserve the surrounding middleware groups.
- Tenant/UI callbacks: Configure host callbacks in `app/Providers/AppServiceProvider.php`.
- Host layout inserts: Put injected partials under `resources/views/layouts/sidebar/` or `resources/views/layouts/header/` and render them through the provider.

**New setup stage:**

- Implementation: Create a class in `app/Setup/` that implements `app/Setup/StepInterface.php` and declares a `VIEW` constant.
- Wizard registration: Add the stage in the ordered `$steps` array in `app/Livewire/Setup.php`.
- UI: Add its Blade partial to `resources/views/setup/steps/`.
- Tests: Add feature coverage in `tests/Feature/Setup/`.

**Utilities:**

- Model-specific reusable behavior: Add a trait under `app/Traits/` and import it into the dependent host model/rule/command.
- Framework lifecycle wiring: Add service registration or boot-time configuration to the appropriate file in `app/Providers/`.
- Artisan maintenance action: Add a command in `app/Console/Commands/` and register it from `app/Console/Kernel.php`.

## Special Directories

**`bootstrap/cache/`:**

- Purpose: Laravel-generated framework cache files.
- Generated: Yes.
- Committed: No.

**`storage/`:**

- Purpose: Runtime logs, framework cache/session artifacts, and application-generated files under Laravel's standard storage locations.
- Generated: Contains runtime-generated content.
- Committed: Directory placeholders only; runtime artifacts are not source files.

**`public/`:**

- Purpose: Only web-server reachable files; the document root must point here so requests enter `public/index.php`.
- Generated: No for the front controller and checked-in static files; published package assets may be placed here by `app/Console/Commands/InstallApplication.php`.
- Committed: Yes for source/static public files.

**`vendor/`:**

- Purpose: Composer-installed Laravel, Livewire, SendPortal Core, and other dependencies declared in `composer.json`.
- Generated: Yes, by Composer.
- Committed: No.

**`.planning/codebase/`:**

- Purpose: Generated architecture reference documents for planning and execution workflows.
- Generated: Yes, by codebase mapping.
- Committed: Managed by the project planning workflow.

---

*Structure analysis: 2026-07-22*
