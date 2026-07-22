# Coding Conventions

**Analysis Date:** 2026-07-22

## Naming Patterns

**Files:**
- Use one PSR-4 class, trait, interface, or migration per PascalCase PHP file. Application code follows its namespace directory exactly: `app/Services/Workspaces/CreateWorkspace.php`, `app/Http/Requests/Workspaces/WorkspaceInvitationStoreRequest.php`, and `app/Traits/HasWorkspaces.php`.
- Name migration files with Laravel's timestamped snake_case convention, as in `database/migrations/2020_11_13_120125_create_api_tokens_table.php`.
- Use PascalCase Blade view filenames only where a component name requires it; normal views are lowercase paths such as `resources/views/workspaces/index.blade.php`.

**Functions:**
- Use camelCase method names. Service entry points are consistently named `handle`, as in `app/Services/Workspaces/CreateWorkspace.php` and `app/Services/Workspaces/AcceptInvitation.php`.
- Use descriptive, sentence-like snake_case test method names and mark them with `/** @test */`, as in `tests/Feature/Workspaces/WorkspacesControllerTest.php`.
- Use Laravel lifecycle and framework method names unchanged (`setUp`, `rules`, `authorize`, `render`, `mount`) in files such as `tests/TestCase.php` and `app/Http/Requests/Workspaces/WorkspaceInvitationStoreRequest.php`.

**Variables:**
- Use camelCase local variables and properties (`$workspaceName`, `$sendInvitation`, `$postData`) throughout `app/Services/Workspaces/CreateWorkspace.php` and `tests/Feature/Invitations/NewUserInvitationTest.php`.
- Use lower-case snake_case only for external array keys, database columns, route parameters, and environment variables, for example `workspace_id` in `app/Models/Workspace.php` and `SENDPORTAL_REGISTER` in `tests/Feature/Auth/AuthConfigEnabledTest.php`.

**Types:**
- Use PascalCase classes and interfaces; suffix HTTP validation classes with `Request`, repositories with `Repository`, and action-like services with an imperative noun/verb class name, as in `app/Http/Requests/ApiTokens/ApiTokenStoreRequest.php`, `app/Repositories/ApiTokenRepository.php`, and `app/Services/Workspaces/RemoveUserFromWorkspace.php`.
- Prefer native parameter and return types in new application code, including nullable types, as demonstrated by `app/Services/Workspaces/CreateWorkspace.php` and `app/Setup/StepInterface.php`.

## Code Style

**Formatting:**
- Apply PHP-CS-Fixer using `.php-cs-fixer.dist.php`; it enforces `@PSR12`, short arrays, alphabetical ordered imports, and no unused imports.
- Use four spaces, LF line endings, UTF-8, trailing-whitespace removal, and a final newline per `.editorconfig`.
- Add `declare(strict_types=1);` immediately after `<?php` in new application, factory, migration, route, and feature-test PHP files, following `app/Models/Workspace.php` and `tests/Feature/Setup/SetupTest.php`.
- Keep a blank line between the PHP declaration, strict-types declaration, namespace, imports, and class body, as in `app/Http/Controllers/Workspaces/WorkspacesController.php`.

**Linting:**
- Formatting is automated by the PHP-CS-Fixer pull-request workflow in `.github/workflows/format.yml`, which runs the config at `.php-cs-fixer.dist.php` and commits formatting changes back to the PR branch.
- No separate static-analysis configuration (PHPStan or Psalm) is present; preserve the existing PHP-CS-Fixer contract rather than introducing tool-specific annotations.

## Import Organization

**Order:**
1. Namespace declaration, then project imports (`App\\...`) as in `app/Http/Controllers/Workspaces/WorkspacesController.php`.
2. PHP/framework imports (`Exception`, `Illuminate\\...`, `Livewire\\...`) in alphabetical class order, also in `app/Http/Controllers/Workspaces/WorkspacesController.php`.
3. Third-party imports (`Sendportal\\...`, `Ramsey\\...`) alphabetically with the rest, as configured by `.php-cs-fixer.dist.php`.

**Path Aliases:**
- Do not use source-path aliases. Resolve PHP classes through Composer PSR-4 namespaces configured in `composer.json`: `App\\` maps to `app/`, `Database\\Factories\\` to `database/factories/`, and `Tests\\` to `tests/`.

## Error Handling

**Patterns:**
- Let framework validation and authorization handle ordinary request failures. Put validation rules in a Form Request, as in `app/Http/Requests/Workspaces/WorkspaceStoreRequest.php`, and use `authorize()` or middleware for access control, as in `app/Http/Requests/Workspaces/WorkspaceInvitationStoreRequest.php` and `app/Http/Middleware/OwnsRequestedWorkspace.php`.
- Use `abort(404)` to conceal inaccessible workspace resources, following `app/Http/Middleware/OwnsRequestedWorkspace.php` and `app/Http/Middleware/RequireWorkspace.php`.
- Declare `@throws Exception` when a controller or service deliberately allows a failure to propagate, as in `app/Services/Workspaces/CreateWorkspace.php` and `app/Http/Controllers/Workspaces/WorkspaceInvitationsController.php`.
- Use a transaction for a multi-write domain operation, as in `DB::transaction()` within `app/Services/Workspaces/CreateWorkspace.php`.
- Catch exceptions only at an explicit recovery boundary. The setup UI flashes a failure message in `app/Livewire/Setup.php`; the setup entry controller treats an unavailable user table as an uninstalled application in `app/Http/Controllers/SetupController.php`.

## Logging

**Framework:** Laravel's configured logger; no application-level `Log` facade usage is present under `app/`.

**Patterns:**
- Allow uncaught failures to reach the Laravel exception handler in `app/Exceptions/Handler.php` rather than adding ad-hoc `echo`, `var_dump`, or `error_log` calls.
- When an operation has a user-facing recovery path, report it through the existing response/session convention in `app/Livewire/Setup.php`, not through direct output.

## Comments

**When to Comment:**
- Use short section comments to separate route groups and test phases, as in `routes/web.php` and `tests/Feature/Workspaces/WorkspacesControllerTest.php` (`given`, `when`, `then`).
- Add comments only for non-obvious framework behavior or business intent, such as the automatic invitation acceptance explanation in `tests/Feature/Workspaces/WorkspaceInvitationsControllerTest.php`.

**JSDoc/TSDoc:**
- Use PHPDoc rather than JSDoc/TSDoc. Document Eloquent model magic properties in model class docblocks (`app/Models/Workspace.php`) and add inline `/** @var Type $value */` annotations where inference is weak (`app/Services/Workspaces/CreateWorkspace.php`).
- Document exception contracts with `@throws` and add a brief behavior description for non-trivial public methods, as in `app/Models/Workspace.php`.

## Function Design

**Size:**
- Keep HTTP controllers thin: inject dependencies, delegate work, then return a view or redirect, as in `app/Http/Controllers/Workspaces/WorkspaceInvitationsController.php`.
- Put cohesive domain mutations in a focused service class with a public `handle()` method, as in `app/Services/Workspaces/AddWorkspaceMember.php` and `app/Services/Workspaces/SendInvitation.php`.

**Parameters:**
- Prefer constructor injection for repositories and services, as in `app/Http/Controllers/Workspaces/WorkspacesController.php`.
- Use Laravel request injection and route-model binding in controller actions, as in `app/Http/Controllers/Workspaces/WorkspacesController.php`.

**Return Values:**
- Return explicit framework response types from controllers where established (`ViewContract`, `RedirectResponse`) as in `app/Http/Controllers/Workspaces/WorkspacesController.php`.
- Use `void` for mutation-only services (`app/Services/Workspaces/RemoveUserFromWorkspace.php`) and return the created/updated model or domain result when callers need it (`app/Services/Workspaces/CreateWorkspace.php`, `app/Services/Workspaces/AcceptInvitation.php`).

## Module Design

**Exports:**
- PHP classes are autoloaded directly by namespace; define the class in its own file under the appropriate `app/` layer, as with `app/Repositories/WorkspacesRepository.php`.
- Compose shared model behavior through traits, following `app/Traits/HasWorkspaces.php` used by `app/Models/User.php`.

**Barrel Files:**
- Not used. Do not add PHP barrel/export files; import the concrete namespaced class directly, as in `app/Services/Workspaces/CreateWorkspace.php`.

---

*Convention analysis: 2026-07-22*
