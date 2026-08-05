# Phase 5: Sender Identity Management - Context

**Gathered:** 2026-08-05
**Status:** Ready for planning

<domain>
## Phase Boundary

Deliver a workspace-scoped sender identity library with a dedicated Senders page and full CRUD for label, From Name, and From Email. This phase covers sender management only; campaign-form selection and campaign auto-capture remain in Phases 6 and 7.

</domain>

<decisions>
## Implementation Decisions

### Sender CRUD authorization
- **D-01:** Every member of the current workspace may create, view, edit, and delete sender identities. Sender management is not owner-only, despite the existing Manage Users feature using `OwnsCurrentWorkspace`.
- **D-02:** Senders are a shared workspace library. A member may edit or delete any sender belonging to the current workspace; sender creator ownership is not required.
- **D-03:** The Senders navigation link is visible to all authenticated users with an active workspace, not only workspace owners.
- **D-04:** A direct request for a sender belonging to another workspace must resolve as `404 Not Found`, preserving tenant/resource concealment rather than exposing the sender's existence.

### the agent's Discretion
- The page layout and create/edit interaction model were not discussed; research and planning may choose the smallest approach consistent with existing package-layout Blade pages.
- Duplicate sender policy and field normalization were not discussed; research and planning may define the data rules while preserving the Phase 7 auto-capture requirements.
- Delete confirmation and any delete-specific UX were not discussed; research and planning may follow established application conventions.

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Requirements and milestone scope
- `.planning/ROADMAP.md` — Phase 5 goal, requirements SENDER-01 through SENDER-05, and success criteria.
- `.planning/REQUIREMENTS.md` — locked sender-management requirements, tenancy boundary, and explicit out-of-scope items.
- `.planning/PROJECT.md` — v1.2 sender constraints, especially the zero-vendor-edit boundary and sender data scope.
- `.planning/STATE.md` — current milestone decisions and prior host/package integration decisions.

### Workspace and tenancy patterns
- `routes/web.php` — existing authenticated route groups, workspace middleware composition, and package route boundary.
- `app/Http/Middleware/RequireWorkspace.php` — current-workspace requirement and 404 behavior for web requests without a valid workspace.
- `app/Traits/HasWorkspaces.php` — current workspace resolution and membership/ownership helpers.
- `app/Models/Workspace.php` — workspace relationships and model conventions.
- `app/Http/Middleware/OwnsCurrentWorkspace.php` — existing owner-only pattern, intentionally not to be applied to sender CRUD.

### Host CRUD, UI, and tests
- `app/Http/Controllers/Workspaces/WorkspaceUsersController.php` — thin controller and current-workspace access pattern.
- `app/Http/Requests/Workspaces/WorkspaceUpdateRequest.php` — Form Request validation convention.
- `resources/views/users/index.blade.php` — existing package-layout Blade CRUD/list page style.
- `resources/views/layouts/sidebar/manageUsersMenuItem.blade.php` — existing navigation menu item and active-state pattern.
- `tests/Feature/Workspaces/WorkspaceUserControllerTest.php` — authenticated, workspace-isolation, and authorization test patterns.

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `RequireWorkspace` middleware: use to require a current workspace before sender routes execute.
- `User::currentWorkspace()` / `currentWorkspace` attribute: use the established request-local workspace resolution rather than introducing a separate tenant resolver.
- `sendportal::layouts.app`: host pages already extend the package application layout and provide the established shell.
- `RefreshDatabase` feature tests and workspace factories: reuse for sender CRUD and cross-workspace isolation coverage.

### Established Patterns
- Host routes are declared in `routes/web.php` and grouped with `auth`, `verified`, and workspace middleware; package routes remain delegated to `Sendportal::webRoutes()`.
- Controllers are thin and return typed views or redirects; validation belongs in Form Request classes.
- Workspace member management currently uses owner-only authorization and aborts with 404 when ownership is missing. Sender management must retain the 404 concealment behavior while allowing all workspace members.
- Existing list pages use Bootstrap-style cards/tables and translated labels within the package layout.

### Integration Points
- Add sender routes to the host-owned authenticated web routes, separate from the vendor package route registration.
- Add a sender navigation item to the host sidebar/header insertion points, visible whenever the user has an active workspace.
- Add a workspace relationship and sender persistence model/migration without changing vendor models.
- Scope every sender query and mutation through the current workspace; never trust an unscoped sender ID from the request.

</code_context>

<specifics>
## Specific Ideas

- User selected: "Semua member workspace" for sender CRUD.
- User selected: "Ya, semua sender bersama"; sender records are shared across the workspace rather than owned by their creator.
- User selected: cross-workspace direct access should return `404 Not Found`.
- User selected: the Senders link should be shown for all members, once a workspace is active.

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope. Campaign sender selection and automatic sender capture are explicitly assigned to Phases 6 and 7 by the roadmap.

</deferred>

---

*Phase: 5-Sender Identity Management*
*Context gathered: 2026-08-05*
