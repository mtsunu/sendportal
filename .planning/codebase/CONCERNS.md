# Codebase Concerns

**Analysis Date:** 2026-07-22

## Tech Debt

**Mutable workspace resolution in the user model:**
- Issue: `currentWorkspace()` and `currentWorkspaceId()` in `app/Traits/HasWorkspaces.php` both call `switchToWorkspace()` while resolving an accessor. Reading the current workspace therefore writes the user record. If `users.current_workspace_id` points at a missing workspace, `Workspace::find()` returns `null` and is passed to a non-null `Workspace` parameter.
- Files: `app/Traits/HasWorkspaces.php`, `app/Models/User.php`, `database/migrations/2019_08_24_114758_adjust_users_table.php`
- Impact: A stale or invalid current-workspace ID turns ordinary authorization and rendering requests into a `TypeError`; repeated reads also issue avoidable updates. The schema has no foreign key protecting `current_workspace_id`.
- Fix approach: Make resolution read-only, handle a missing workspace by clearing the stale ID deliberately, and add an integrity constraint or a repair path for `current_workspace_id`.

**Legacy API-token user field remains in creation paths:**
- Issue: The `api_token` field was removed from `users`, but `app/Console/Commands/InstallApplication.php` and `database/factories/UserFactory.php` still supply it when creating users. `App\\Models\\User::$fillable` does not include the removed field, so these values have no effect.
- Files: `app/Console/Commands/InstallApplication.php`, `database/factories/UserFactory.php`, `app/Models/User.php`, `database/migrations/2021_01_26_151747_remove_api_token_from_users_table.php`
- Impact: The installation and test fixtures express an API-authentication behavior that no longer exists, increasing maintenance cost and making upgrades harder to reason about.
- Fix approach: Remove the obsolete attribute from the installer and factory; keep all workspace API-token behavior in `app/Models/ApiToken.php` and `app/Http/Controllers/Auth/ApiTokenController.php`.

**Legacy Laravel application structure:**
- Issue: Routing, HTTP-kernel middleware registration, and controller dispatch use compatibility-era conventions alongside Laravel 11 dependencies.
- Files: `routes/web.php`, `app/Http/Kernel.php`, `app/Exceptions/Handler.php`, `composer.json`
- Impact: Framework upgrades require preserving multiple deprecated compatibility paths and make framework documentation less directly applicable.
- Fix approach: Incrementally adopt Laravel 11 bootstrap, middleware, route-action, and exception-registration conventions, guarded by request and authentication regression tests.

**No reproducible dependency snapshot:**
- Issue: `composer.json` permits development stability while no `composer.lock` is committed. CI runs `composer install`, so its dependency graph is resolved from moving version ranges rather than a repository snapshot.
- Files: `composer.json`, `.github/workflows/ci.yml`
- Impact: Builds and security posture can change without an application commit; a newly released indirect dependency can break CI or production deployments.
- Fix approach: Commit `composer.lock`, use `composer install --prefer-dist --no-interaction` against it in CI and deployment, and retain an explicit scheduled dependency-update workflow.

## Known Bugs

**Workspace owners can retract invitations from other workspaces:**
- Symptoms: An owner of any current workspace can send `DELETE /users/invitations/{invitation}` for an invitation ID belonging to another workspace. The route verifies ownership only of the caller's current workspace; the action deletes the route-bound invitation without checking its `workspace_id`.
- Files: `routes/web.php`, `app/Http/Controllers/Workspaces/WorkspaceInvitationsController.php`, `app/Http/Middleware/OwnsCurrentWorkspace.php`, `tests/Feature/Workspaces/WorkspaceInvitationsControllerTest.php`
- Trigger: Authenticate as a workspace owner and submit the delete route with an invitation UUID created for a separate workspace.
- Workaround: None in the application.
- Fix approach: Resolve invitations through `$request->user()->currentWorkspace()->invitations()` or enforce a policy comparing the invitation workspace to the current workspace. Add cross-workspace delete denial coverage.

**Removing an unrelated user clears that user's active workspace:**
- Symptoms: `WorkspaceUsersController::destroy()` finds a user by arbitrary numeric ID and delegates to `RemoveUserFromWorkspace` without confirming that the target belongs to the caller's workspace. Detaching is a no-op for an unrelated user, but the service unconditionally sets that user's `current_workspace_id` to `null`.
- Files: `app/Http/Controllers/Workspaces/WorkspaceUsersController.php`, `app/Services/Workspaces/RemoveUserFromWorkspace.php`, `routes/web.php`, `tests/Feature/Workspaces/WorkspaceUserControllerTest.php`
- Trigger: Authenticate as a workspace owner and call `DELETE /users/{id}` with the ID of a user from another workspace.
- Workaround: None in the application.
- Fix approach: Fetch the target through `$workspace->users()`, return 404 when absent, and clear `current_workspace_id` only when it equals the workspace being left. Add missing-user and cross-workspace target tests.

**Workspace deletion cannot safely include outstanding invitations:**
- Symptoms: `Workspace::detachUsersAndDestroy()` detaches members and deletes the workspace but does not delete related invitations. The invitations migration defines a foreign key to `workspaces`, so a workspace with invitations cannot be removed through this method.
- Files: `app/Models/Workspace.php`, `database/migrations/2017_04_11_100000_create_invitations_table.php`
- Trigger: Call `detachUsersAndDestroy()` on a workspace with an invitation row.
- Workaround: Delete all related invitations before deleting the workspace.
- Fix approach: Wrap membership, invitation, and workspace deletion in a transaction, or define an intentional database-level cascade and cover it with a model/service test.

## Security Considerations

**Unauthenticated web installer can mutate deployment configuration:**
- Risk: `GET /setup` has no authentication or network restriction. When the user table cannot be queried or is empty, the Livewire setup flow can create the environment file, set application/database configuration, run migrations, and create an admin account. The controller treats every database exception as an installable state.
- Files: `routes/web.php`, `app/Http/Controllers/SetupController.php`, `app/Livewire/Setup.php`, `app/Setup/Env.php`, `app/Setup/Database.php`, `app/Setup/Migrations.php`, `app/Setup/Admin.php`, `app/Setup/WritesToEnvironment.php`
- Current mitigation: `SetupController::index()` redirects to login when `User::exists()` succeeds.
- Recommendations: Make installation a CLI-only or explicitly deployment-gated operation; persist an installation-complete marker independent of a database query; require a one-time secret and/or local network access during setup; return a generic unavailable response on database failures after deployment.

**Workspace API tokens are bearer secrets retained and transmitted as plaintext:**
- Risk: `api_tokens.api_token` stores the usable token value. The resolver accepts it from either the Authorization bearer header or the `api_token` query parameter, which can expose it in request logs, analytics, proxy URLs, browser history, and error reporting.
- Files: `app/Models/ApiToken.php`, `app/Providers/AppServiceProvider.php`, `app/Http/Controllers/Auth/ApiTokenController.php`, `database/migrations/2020_11_13_120125_create_api_tokens_table.php`
- Current mitigation: Tokens are generated with `Str::random(32)`, are unique in the database, and API routes use configured throttling in `routes/api.php`.
- Recommendations: Accept credentials only in the Authorization header; store a one-way hash plus a non-secret lookup prefix; reveal a raw token once at creation; add expiry, rotation/revocation audit fields, and rate-limit monitoring.

**State-changing GET endpoints are vulnerable to cross-site navigation effects:**
- Risk: Logout and workspace switching modify session or persisted state through GET routes. Laravel's CSRF protection does not protect GET requests; the Lax session-cookie setting permits cookies on top-level cross-site navigations.
- Files: `routes/web.php`, `app/Http/Controllers/Auth/LoginController.php`, `app/Http/Controllers/Workspaces/SwitchWorkspaceController.php`, `config/session.php`
- Current mitigation: Both routes require authentication, and workspace switching checks membership.
- Recommendations: Change both actions to POST routes protected by CSRF middleware, update the UI forms, and add tests asserting GET is unavailable for state changes.

**Trusted-host protection is present but disabled:**
- Risk: The global HTTP kernel comments out `TrustHosts`, leaving host validation inactive. Host-header-derived URLs can affect redirects and generated links when the surrounding deployment does not sanitize the incoming Host header.
- Files: `app/Http/Kernel.php`, `app/Http/Middleware/TrustHosts.php`, `config/app.php`
- Current mitigation: Deployment proxies can impose host validation externally.
- Recommendations: Enable `TrustHosts` with production domains, configure proxy trust narrowly, and add an integration check for host-header rejection.

## Performance Bottlenecks

**Invitation delivery runs synchronously in the web request:**
- Problem: Sending an invitation performs `Mail::send()` after creating the invitation (and, for existing users, after adding membership). It is not dispatched to a queue or protected by a transaction spanning the user-visible state change.
- Files: `app/Services/Workspaces/SendInvitation.php`, `app/Services/Workspaces/AcceptInvitation.php`, `config/queue.php`, `config/horizon.php`
- Cause: SMTP/provider latency blocks the owner request; a delivery failure can leave an invitation or accepted membership committed while the request reports an error.
- Improvement path: Persist the invitation and enqueue a mail job in a transaction with an after-commit dispatch; use retry/backoff and surface delivery state in the user-management UI.

**Current-workspace reads can write to the database:**
- Problem: Resolving `currentWorkspace` can call `save()` through `switchToWorkspace()`.
- Files: `app/Traits/HasWorkspaces.php`, `app/Models/User.php`, `app/Http/Controllers/Workspaces/WorkspaceUsersController.php`
- Cause: The accessor falls back by persisting a chosen workspace instead of returning it as read-only state.
- Improvement path: Separate selection from lookup, cache request-local resolution if needed, and save only from `SwitchWorkspaceController::switch()`.

## Fragile Areas

**Workspace authorization is split across route middleware, controller code, request authorization, and services:**
- Files: `routes/web.php`, `app/Http/Middleware/OwnsCurrentWorkspace.php`, `app/Http/Middleware/OwnsRequestedWorkspace.php`, `app/Http/Requests/Workspaces/WorkspaceInvitationStoreRequest.php`, `app/Http/Controllers/Workspaces/WorkspaceUsersController.php`, `app/Http/Controllers/Workspaces/WorkspaceInvitationsController.php`
- Why fragile: New endpoints can accidentally reuse a current-workspace check while acting on a route-bound resource from another workspace, as the invitation deletion path does. Services also accept models without independently validating tenant membership.
- Safe modification: Introduce Laravel policies or scoped route-model binding for every workspace-owned resource. Resolve resources from the current workspace relation before invoking a service.
- Test coverage: `tests/Feature/Workspaces/WorkspaceInvitationsControllerTest.php` covers deletion in the caller's own workspace only; `tests/Feature/Workspaces/WorkspaceUserControllerTest.php` covers removal of a real member only.

**Database-environment mutation is duplicated:**
- Files: `app/Console/Commands/InstallApplication.php`, `app/Setup/Database.php`, `app/Setup/WritesToEnvironment.php`
- Why fragile: CLI and web setup use separate regular-expression file rewrites and persistence checks, with different error handling. Values requiring environment-file quoting or escaping are not normalized at a shared boundary.
- Safe modification: Centralize environment writing behind one tested service, validate/sanitize supported values, and limit it to installation-only execution.
- Test coverage: `tests/Feature/Setup/SetupTest.php` and `tests/Feature/Setup/SetupControllerTest.php` exercise step progression and basic routing; they do not cover setup access after a database connection failure, persistence failures, or configuration-value escaping.

## Scaling Limits

**Default queue mode executes work inline:**
- Current capacity: `config/queue.php` defaults to the `sync` driver, and invitation mail is directly synchronous in `app/Services/Workspaces/SendInvitation.php`.
- Limit: Request throughput and timeout tolerance are bounded by downstream mail latency; mail-heavy workflows cannot use Horizon until a Redis queue is configured and message work is dispatched.
- Scaling path: Set a durable queue connection, run the configured Horizon supervisors in `config/horizon.php`, move all mail and campaign dispatch work to jobs, and monitor queue latency/failures.

**Workspace membership relations load complete collections:**
- Current capacity: `hasWorkspaces()`, `onWorkspace()`, and user-management rendering rely on loaded Eloquent collections rather than existence/scoped queries.
- Limit: Users with many workspaces or workspaces with many members cause larger reads and in-memory membership checks per request.
- Scaling path: Use `exists()`/scoped SQL for authorization checks and paginate member/invitation lists in `app/Http/Controllers/Workspaces/WorkspaceUsersController.php` and `resources/views/users/index.blade.php`.

## Dependencies at Risk

**Unpinned Composer dependency graph:**
- Risk: `composer.json` uses broad caret constraints and `minimum-stability: dev` without a lockfile.
- Impact: Application, Laravel, Livewire, Horizon, and `mettle/sendportal-core` versions can resolve differently across machines and dates.
- Migration plan: Generate and commit `composer.lock`; use controlled update pull requests and test both database engines as configured in `.github/workflows/ci.yml`.

**Repository relies on an external core package for primary product behavior:**
- Risk: Subscriber, campaign, message, webhook, and public/API routes are registered from `mettle/sendportal-core`, rather than implemented in this repository.
- Impact: Changes to `composer.json`, `app/Providers/AppServiceProvider.php`, `routes/web.php`, and `routes/api.php` can break high-value behavior without source-level tests in this codebase.
- Migration plan: Pin and test a known core release through the lockfile; maintain contract tests for route registration, workspace resolution, auth, queues, and webhook flows.

## Missing Critical Features

**Installation lockdown:**
- Problem: The application does not provide an explicit, durable production lock for the browser setup workflow.
- Blocks: Safe deployment recovery during database outages and defense against an attacker reaching setup before or during initialization.
- Files: `routes/web.php`, `app/Http/Controllers/SetupController.php`, `app/Livewire/Setup.php`

**Tenant-scoped authorization policy layer:**
- Problem: Workspace-resource ownership is manually assembled instead of expressed as reusable policies/scoped bindings.
- Blocks: Reliable extension of member, invitation, token, and workspace-management features without IDOR regressions.
- Files: `app/Http/Middleware/OwnsCurrentWorkspace.php`, `app/Http/Middleware/OwnsRequestedWorkspace.php`, `app/Http/Controllers/Workspaces/WorkspaceInvitationsController.php`, `app/Http/Controllers/Workspaces/WorkspaceUsersController.php`

## Test Coverage Gaps

**Cross-workspace mutation authorization:**
- What's not tested: Deleting an invitation from another workspace; removing a user not attached to the current workspace; missing target IDs; and API-token management across workspace boundaries.
- Files: `tests/Feature/Workspaces/WorkspaceInvitationsControllerTest.php`, `tests/Feature/Workspaces/WorkspaceUserControllerTest.php`, `tests/Feature/Auth/WorkspaceApiTokenTest.php`, `app/Http/Controllers/Workspaces/WorkspaceInvitationsController.php`, `app/Http/Controllers/Workspaces/WorkspaceUsersController.php`
- Risk: High-impact tenant-isolation regressions can pass the suite.
- Priority: High

**Installer abuse and failure handling:**
- What's not tested: Unauthenticated setup behavior when the database is unreachable, setup mutation after deployment, invalid environment-file values, and admin/user creation races.
- Files: `tests/Feature/Setup/SetupTest.php`, `tests/Feature/Setup/SetupControllerTest.php`, `app/Http/Controllers/SetupController.php`, `app/Setup/Database.php`, `app/Setup/WritesToEnvironment.php`
- Risk: Configuration takeover and deployment failures remain unguarded.
- Priority: High

**Primary SendPortal-core integration flows:**
- What's not tested: The repository's tests focus on host authentication, setup, workspaces, and API token resolution. They do not exercise the core package routes registered by `Sendportal::webRoutes()`, `Sendportal::apiRoutes()`, and `Sendportal::publicApiRoutes()`.
- Files: `routes/web.php`, `routes/api.php`, `app/Providers/AppServiceProvider.php`, `tests/Feature/Auth/WorkspaceApiTokenTest.php`
- Risk: A dependency update or workspace-resolver change can break campaigns, message dispatch, webhooks, or public API behavior without host-repository test detection.
- Priority: High

**Static analysis and meaningful coverage reporting:**
- What's not tested: No PHPStan/Psalm configuration or coverage command is present. The PHPUnit source include targets `src/`, while application source is in `app/`, so coverage configuration does not target the host application's code.
- Files: `composer.json`, `phpunit.xml.dist`, `.github/workflows/ci.yml`, `app/`
- Risk: Type errors such as null workspace resolution and untested authorization branches are discovered only at runtime.
- Priority: Medium

---

*Concerns audit: 2026-07-22*
