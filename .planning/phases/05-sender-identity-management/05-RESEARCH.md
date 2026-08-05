# Phase 5: Sender Identity Management - Research

**Researched:** 2026-08-05
**Domain:** Laravel 11 / Eloquent workspace-scoped CRUD with Blade UI
**Confidence:** HIGH for repository patterns; MEDIUM for framework documentation

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions
- **D-01:** Every member of the current workspace may create, view, edit, and delete sender identities. Sender management is not owner-only, despite the existing Manage Users feature using `OwnsCurrentWorkspace`.
- **D-02:** Senders are a shared workspace library. A member may edit or delete any sender belonging to the current workspace; sender creator ownership is not required.
- **D-03:** The Senders navigation link is visible to all authenticated users with an active workspace, not only workspace owners.
- **D-04:** A direct request for a sender belonging to another workspace must resolve as `404 Not Found`, preserving tenant/resource concealment rather than exposing the sender's existence.

### the agent's Discretion
- The page layout and create/edit interaction model were not discussed; research and planning may choose the smallest approach consistent with existing package-layout Blade pages.
- Duplicate sender policy and field normalization were not discussed; research and planning may define the data rules while preserving the Phase 7 auto-capture requirements.
- Delete confirmation and any delete-specific UX were not discussed; research and planning may follow established application conventions.

### Deferred Ideas (OUT OF SCOPE)
None — discussion stayed within phase scope. Campaign sender selection and automatic sender capture are explicitly assigned to Phases 6 and 7 by the roadmap.
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|------------------|
| SENDER-01 | Create a saved sender identity with label, From Name, and From Email in the current workspace. | Add a host-owned Eloquent model, migration, workspace `hasMany` relation, Form Request, and authenticated store action. |
| SENDER-02 | Dedicated navigation-reachable Senders page listing current-workspace senders. | Add host routes/controller/view and a sidebar fragment returned through the existing SendPortal resolver seam. |
| SENDER-03 | Edit label, From Name, and From Email. | Use the same validated form for update and a current-workspace scoped resource lookup. |
| SENDER-04 | Delete a saved sender. | Add a CSRF-protected method-spoofed DELETE form and a current-workspace scoped destroy action. |
| SENDER-05 | Validate inputs and prevent cross-workspace view/edit/delete/use. | Use Form Request rules and never resolve a sender by unscoped ID; query through the authenticated user's current workspace and assert 404 for foreign IDs. |
</phase_requirements>

## User Constraints

The phase boundary is sender management only: a workspace-scoped sender library and dedicated CRUD page for label, From Name, and From Email; campaign-form selection and campaign auto-capture remain in Phases 6 and 7. [VERIFIED: .planning/phases/05-sender-identity-management/05-CONTEXT.md:6-10; quote: “Deliver a workspace-scoped sender identity library with a dedicated Senders page and full CRUD for label, From Name, and From Email. This phase covers sender management only; campaign-form selection and campaign auto-capture remain in Phases 6 and 7.”]

## Summary

Implement this as a host-owned Laravel feature, not a SendPortal Core modification. The existing application already owns host routes in `routes/web.php`, resolves the active workspace through the authenticated `User`, and renders host pages inside `sendportal::layouts.app`. [VERIFIED: routes/web.php:57-112; quote: “Route::namespace('Workspaces')” and “Sendportal::webRoutes();”] [VERIFIED: app/Providers/AppServiceProvider.php:37-61; quote: “Sendportal::setCurrentWorkspaceIdResolver” and “Sendportal::setSidebarHtmlContentResolver”]

The safest tenancy boundary is relation-first lookup: `$request->user()->currentWorkspace()->senders()->findOrFail($id)` (proposed skeleton). Do not inject a globally bound `Sender` and then authorize it afterward, because an unscoped lookup can expose whether another workspace's record exists. Laravel's documented implicit binding returns 404 for missing models, but the workspace condition must be part of the lookup to make a foreign sender indistinguishable from a missing sender. [CITED: https://laravel.com/docs/11.x/routing#implicit-binding] [VERIFIED: app/Http/Middleware/RequireWorkspace.php:18-30; quote: “Sendportal::currentWorkspaceId();” and “abort(404);”]

Use a small resource-style controller, two Form Requests or one shared request, a host migration, `Sender` model, `Workspace::senders()` relation, Blade list/form views, and feature tests. Reuse the existing Bootstrap-style package shell and sidebar resolver. No new Composer package is required or recommended. [VERIFIED: resources/views/users/index.blade.php:1-17; quote: “@extends('sendportal::layouts.app')” and “<div class="card">”] [VERIFIED: composer.json:7-21; quote: “laravel/framework”: “^11.0” and “phpunit/phpunit”: “^10.5”]

**Primary recommendation:** Add host-owned `/senders` resource routes behind `auth`, `verified`, and `RequireWorkspace`; scope every read and mutation through `currentWorkspace()->senders()`; use Form Request validation with `required|string|max:255` for label/name and `required|email|max:255` for email; render the CRUD page in the package layout and expose its navigation item through the existing sidebar resolver.

## Architectural Responsibility Map

| Capability | Primary Tier | Secondary Tier | Rationale |
|------------|-------------|----------------|-----------|
| Sender CRUD HTTP contract | API / Backend | Browser / Client | Routes, middleware, controller, validation, and authorization own the security boundary; the browser only submits forms. [VERIFIED: routes/web.php:57-77; quote: “middleware(['auth', 'verified', RequireWorkspace::class, OwnsCurrentWorkspace::class])”] |
| Sender persistence and tenancy | Database / Storage | API / Backend | The sender row carries `workspace_id`; Eloquent relations must constrain all queries and writes to the active workspace. [CITED: https://laravel.com/docs/11.x/eloquent-relationships#one-to-many] |
| Senders page and forms | Browser / Client | Frontend Server (SSR) | Blade renders server-side HTML using the package layout and Bootstrap classes already used by host pages. [VERIFIED: resources/views/users/index.blade.php:1-17; quote: “@extends('sendportal::layouts.app')”] |
| Sidebar navigation | Frontend Server (SSR) | Browser / Client | The package sidebar renders host-provided HTML from `Sendportal::sidebarHtmlContent()`, and the host resolver currently returns a Blade fragment. [VERIFIED: vendor/mettle/sendportal-core/resources/views/layouts/partials/sidebar.blade.php:41-43; quote: “{!! \\Sendportal\\Base\\Facades\\Sendportal::sidebarHtmlContent() !!}”] [VERIFIED: app/Providers/AppServiceProvider.php:58-61; quote: “return view('layouts.sidebar.manageUsersMenuItem')->render();”] |

## Standard Stack

### Core

| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| `laravel/framework` | `^11.0` in repository | Routing, middleware, Eloquent, validation, migrations, Blade integration | Locked project framework; no new dependency is needed. [VERIFIED: composer.json:7-14; quote: “laravel/framework”: “^11.0”] |
| `mettle/sendportal-core` | `^3.0` in repository | Provides the application layout and sidebar insertion seam | Existing package boundary; host code must extend rather than edit vendor code. [VERIFIED: composer.json:7-14; quote: “mettle/sendportal-core”: “^3.0”] [VERIFIED: vendor/mettle/sendportal-core/src/SendportalBaseServiceProvider.php:23-53; quote: “__DIR__.'/../resources/views' => resource_path('views/vendor/sendportal')”] |
| PHP | `^8.2` constraint; PHP 8.4.23 available in this session | Runtime for the Laravel application | Project outcome targets PHP 8.4 and the local probe reported `PHP 8.4.23`. [VERIFIED: composer.json:7-8; quote: “php”: “^8.2”] [VERIFIED: environment probe 2026-08-05; quote: “PHP 8.4.23 (cli)”] |

### Supporting

| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| PHPUnit | `^10.5` in repository | Feature tests for CRUD, validation, tenancy, and navigation | Add tests under `tests/Feature/Workspaces` following the existing `RefreshDatabase` pattern. [VERIFIED: composer.json:16-21; quote: “phpunit/phpunit”: “^10.5”] [VERIFIED: tests/Feature/Workspaces/WorkspaceUserControllerTest.php:9-14; quote: “use RefreshDatabase;”] |
| Laravel UI / Bootstrap package shell | `^4.5` in repository | Existing authenticated shell and Bootstrap-style HTML | Reuse the package layout and existing card/table/form classes; do not add a frontend package. [VERIFIED: composer.json:11-14; quote: “laravel/ui”: “^4.5”] [VERIFIED: 05-UI-SPEC.md:20-28; quote: “use the existing SendPortal package layout” and “no new component library”] |

### Alternatives Considered

| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Host resource controller plus Blade forms | Livewire CRUD | Livewire is already installed, but this phase has ordinary server-rendered CRUD patterns and no need to add client state. [ASSUMED] |
| Relation-scoped lookup | Global implicit `Sender $sender` binding plus later check | Implicit binding supplies 404 for absent models, but a global binding must be followed by a correct workspace check; relation-first lookup makes the tenant condition explicit at the query boundary. [CITED: https://laravel.com/docs/11.x/routing#implicit-binding] [ASSUMED] |
| Existing SendPortal sidebar resolver | Editing `vendor/mettle/sendportal-core` sidebar | Vendor edits violate the hard boundary and make upgrades unsafe. [VERIFIED: .planning/REQUIREMENTS.md:43-49; quote: “Hard project boundary — the package must stay upgradable; extend via published views + host seams.”] |

**Installation:** No installation command. The phase should use the existing Composer graph; no external package is recommended.

**Version verification:** No new package requires registry verification. Existing versions are read from `composer.json`; PHP availability was probed locally. [VERIFIED: composer.json:7-21; quote: “laravel/framework”: “^11.0”, “phpunit/phpunit”: “^10.5”]

## Package Legitimacy Audit

No external packages are installed or recommended for this phase; therefore the package legitimacy gate is not applicable. The implementation uses packages already present in `composer.json`. [VERIFIED: composer.json:7-21; quote: “require” and “require-dev”]

## Architecture Patterns

### System Architecture Diagram

```text
Authenticated browser
        |
        v
routes/web.php: /senders + auth + verified + RequireWorkspace
        |
        v
SenderController -- FormRequest validation --> 422/redirect with flashed errors
        |
        v
User::currentWorkspace() -> Workspace::senders() -> Sender row with workspace_id
        |                                      |
        |                                      +--> create/update/delete
        v
senders.index Blade view extends sendportal::layouts.app
        ^
        |
SendPortal Core sidebar -> AppServiceProvider resolver -> host sender menu fragment
```

The diagram reflects the existing host/package boundary and the required tenant gate. [VERIFIED: routes/web.php:108-112; quote: “Sendportal::webRoutes();”] [VERIFIED: vendor/mettle/sendportal-core/resources/views/layouts/partials/sidebar.blade.php:41-43; quote: “sidebarHtmlContent()”]

### Recommended Project Structure

```text
app/
├── Http/Controllers/Workspaces/SendersController.php
├── Http/Requests/Workspaces/SenderStoreRequest.php
├── Http/Requests/Workspaces/SenderUpdateRequest.php
└── Models/Sender.php
database/migrations/
└── <timestamp>_create_senders_table.php
resources/views/
├── senders/index.blade.php
├── senders/create.blade.php       # optional if form is separate
├── senders/edit.blade.php         # optional if form is separate
└── layouts/sidebar/sendersMenuItem.blade.php
tests/Feature/Workspaces/
└── SenderControllerTest.php
```

The exact filenames above are a recommended structure, not existing discrete values; the planner may collapse create/edit into one view while keeping the existing namespace and naming conventions. [ASSUMED] Existing application conventions require one PSR-4 class per file, `app/Http/Requests` Form Requests, and lower-case normal Blade view paths. [VERIFIED: AGENTS.md:92-101; quote: “Use one PSR-4 class, trait, interface, or migration per PascalCase PHP file” and “suffix HTTP validation classes with `Request`”] [VERIFIED: AGENTS.md:94; quote: “normal views are lowercase paths”]

### Pattern 1: Relation-first tenant scoping

**What:** Resolve the active workspace from the authenticated user, then query the sender through the workspace relation for every list, edit, update, and delete operation. [VERIFIED: app/Traits/HasWorkspaces.php:89-107; quote: “public function currentWorkspace(): ?Workspace”] [CITED: https://laravel.com/docs/11.x/eloquent-relationships#one-to-many]

**When to use:** Always for this phase; sender IDs from URLs are untrusted input and must never select a row outside the current workspace. [VERIFIED: .planning/phases/05-sender-identity-management/05-CONTEXT.md:71-76; quote: “Scope every sender query and mutation through the current workspace; never trust an unscoped sender ID from the request.”]

**Example:** The following is an implementation skeleton, not a source-of-truth code quote. [ASSUMED]

```php
$workspace = $request->user()->currentWorkspace();
$sender = $workspace->senders()->findOrFail($senderId);
$sender->update($request->validated());
```

The `findOrFail` result should remain 404 for a foreign workspace because the relation query includes the workspace constraint. [ASSUMED]

### Pattern 2: Form Request boundary

**What:** Keep validation in a Form Request and inject that request into controller store/update methods; Laravel validates before the controller action and flashes errors/input on traditional form failure. [CITED: https://laravel.com/docs/11.x/validation#form-request-validation] [VERIFIED: AGENTS.md:116-122; quote: “Put validation rules in a Form Request”]

**Recommended rules:** `label` and `from_name` are required strings with a bounded length; `from_email` is required, a valid email, and bounded in length. The field names and required behavior are locked by the phase requirements. [VERIFIED: .planning/REQUIREMENTS.md:12-16; quote: “label, a From Name, and a From Email” and “label and From Name required; From Email must be a valid email address”] [CITED: https://laravel.com/docs/11.x/validation#available-validation-rules]

**Example:** Proposed rule skeleton; the concrete column names are derived from the requirement quote above. [ASSUMED]

```php
public function rules(): array
{
    return [
        'label' => ['required', 'string', 'max:255'],
        'from_name' => ['required', 'string', 'max:255'],
        'from_email' => ['required', 'email', 'max:255'],
    ];
}
```

### Pattern 3: Host extension of package layout

**What:** Register sender routes in the host route file and render a host Blade fragment through `Sendportal::setSidebarHtmlContentResolver`; do not add sender routes or models to vendor code. [VERIFIED: app/Providers/AppServiceProvider.php:58-61; quote: “setSidebarHtmlContentResolver”] [VERIFIED: vendor/mettle/sendportal-core/src/SendportalBaseServiceProvider.php:50-53; quote: “loadViewsFrom” and “loadMigrationsFrom”] [VERIFIED: .planning/REQUIREMENTS.md:43-49; quote: “Editing `vendor/mettle/sendportal-core` to add sender UI”]

**When to use:** For the navigation item and host-owned CRUD page in this phase. Phase 6 may publish/override a package campaign view, but that is explicitly outside this phase. [VERIFIED: .planning/REQUIREMENTS.md:18-26; quote: “The saved-sender UI on the campaign form”]

### Anti-Patterns to Avoid

- **Applying `OwnsCurrentWorkspace` to sender routes:** It would reject members even though D-01 grants CRUD to every current-workspace member. [VERIFIED: app/Http/Middleware/OwnsCurrentWorkspace.php:15-21; quote: “if (! $request->user()->ownsCurrentWorkspace()) { abort(404); }”] [VERIFIED: 05-CONTEXT.md:17-19; quote: “Every member of the current workspace may create, view, edit, and delete sender identities.”]
- **Global `Sender::find($id)` followed by mutation:** This is an unscoped lookup and risks cross-tenant access. [VERIFIED: 05-CONTEXT.md:74-76; quote: “never trust an unscoped sender ID from the request.”]
- **Using `Workspace::find($id)` from request data:** The current workspace is already resolved from the authenticated user; accepting a workspace ID would create an unnecessary tenant-switching input. [VERIFIED: app/Providers/AppServiceProvider.php:39-54; quote: “auth()->user()” and “currentWorkspaceId()”] [ASSUMED]
- **Putting database writes in Blade or a fat controller:** The project convention puts validation in Form Requests and cohesive mutations in focused services where needed. [VERIFIED: AGENTS.md:116-143; quote: “Put validation rules in a Form Request” and “Put cohesive domain mutations in a focused service class with a public `handle()` method.”]
- **Editing `vendor/mettle/sendportal-core`:** The hard project boundary is zero vendor edits. [VERIFIED: .planning/REQUIREMENTS.md:43-46; quote: “Hard project boundary — the package must stay upgradable.”]

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| HTTP validation and error flashing | Manual `if` checks and custom session error plumbing | Laravel Form Requests and built-in validation rules | Laravel validates before the action and redirects/flashes errors for traditional forms. [CITED: https://laravel.com/docs/11.x/validation#form-request-validation] |
| 404 resource resolution | Custom response branching for every missing/foreign sender | Relation-scoped `findOrFail` or a relation-scoped binding | Laravel's failure convention is 404; the relation query supplies the tenant condition. [CITED: https://laravel.com/docs/11.x/routing#implicit-binding] |
| HTML method spoofing | JavaScript-only DELETE transport | Blade `@method('delete')` plus `@csrf` | Laravel documents hidden method spoofing for DELETE forms and CSRF for web forms. [CITED: https://laravel.com/docs/11.x/routing#form-method-spoofing] |
| New navigation infrastructure | A parallel layout/sidebar system | Existing SendPortal sidebar resolver | The package sidebar already renders host-provided HTML. [VERIFIED: vendor/mettle/sendportal-core/resources/views/layouts/partials/sidebar.blade.php:41-43; quote: “sidebarHtmlContent()”] |

**Key insight:** The difficult part is not CRUD mechanics; it is ensuring the workspace predicate is structurally present on every sender lookup. Centralizing that predicate in the `Workspace::senders()` relation and using it for all mutations is safer than relying on controller discipline alone. [ASSUMED]

## Common Pitfalls

### Pitfall 1: Owner-only middleware leaks into sender routes

**What goes wrong:** A normal workspace member receives 404 despite being entitled to manage shared senders. [VERIFIED: 05-CONTEXT.md:17-19; quote: “Sender management is not owner-only”]

**Why it happens:** The existing `/users` route group applies `OwnsCurrentWorkspace` to owner-only user management. [VERIFIED: routes/web.php:57-66; quote: “RequireWorkspace::class, OwnsCurrentWorkspace::class”]

**How to avoid:** Create a separate sender route group with `auth`, `verified`, and `RequireWorkspace` only. [ASSUMED]

**Warning signs:** Member CRUD tests return 404, or the navigation fragment is wrapped in an ownership check. [VERIFIED: resources/views/layouts/sidebar/manageUsersMenuItem.blade.php:1-8; quote: “@if (auth()->user()->ownsCurrentWorkspace())”]

### Pitfall 2: Foreign sender is fetched before tenant filtering

**What goes wrong:** A user can view or mutate a sender from another workspace by changing the URL ID. [VERIFIED: 05-CONTEXT.md:19-20; quote: “A direct request for a sender belonging to another workspace must resolve as `404 Not Found`”]

**Why it happens:** Eloquent model binding or `Sender::findOrFail` alone scopes by primary key, not workspace. [CITED: https://laravel.com/docs/11.x/routing#implicit-binding] [ASSUMED]

**How to avoid:** Use `$workspace->senders()->findOrFail(...)` for edit/update/destroy, and add explicit cross-workspace tests. [ASSUMED]

**Warning signs:** A foreign sender test returns 200, 302 success, or 403 instead of 404. [VERIFIED: 05-CONTEXT.md:19-20; quote: “404 Not Found”]

### Pitfall 3: Duplicate policy differs between MySQL and PostgreSQL

**What goes wrong:** Equivalent addresses with whitespace or casing can produce unexpected duplicate records or inconsistent future auto-capture behavior. [ASSUMED]

**Why it happens:** Phase 7 requires deduplication, while the current phase leaves normalization and duplicate policy to planning discretion; MySQL and PostgreSQL collations can compare strings differently. [VERIFIED: 05-CONTEXT.md:23-25; quote: “Duplicate sender policy and field normalization were not discussed”] [ASSUMED]

**How to avoid:** Recommended policy: trim label/name/email; lowercase the email before storage; preserve From Name casing; define the duplicate pair as normalized `(workspace_id, from_name, from_email)`. Decide whether to enforce the pair with a composite unique index now or defer enforcement to Phase 7 after confirming campaign-capture semantics. [ASSUMED]

**Warning signs:** Tests pass only on one database, or `Example@x.test` and `example@x.test` behave differently. [ASSUMED]

### Pitfall 4: Migration types do not match existing workspace keys

**What goes wrong:** Foreign-key creation fails or behaves differently across the project's MySQL/PostgreSQL CI matrix. [ASSUMED]

**Why it happens:** Existing `workspaces.id` is `increments('id')`, and existing package campaign rows use an unsigned integer workspace key. [VERIFIED: database/migrations/2017_04_11_000000_create_workspaces_table.php:18-22; quote: “$table->increments('id');”] [VERIFIED: vendor/mettle/sendportal-core/database/migrations/2017_04_28_223915_create_campaigns_table.php:20-23; quote: “$table->unsignedInteger('workspace_id')->index();”]

**How to avoid:** Match the existing integer width/signature and add an index/foreign key using the same style as the host migrations; run the full MySQL and PostgreSQL suite. [VERIFIED: .github/workflows/ci.yml:17-37; quote: “mysql:5.7” and “postgres”]

**Warning signs:** Migration succeeds locally but fails in PostgreSQL or foreign-key creation reports incompatible types. [ASSUMED]

### Pitfall 5: Delete UX omits CSRF, method spoofing, or confirmation

**What goes wrong:** DELETE requests are rejected or accidental deletions become easy. [CITED: https://laravel.com/docs/11.x/routing#form-method-spoofing]

**Why it happens:** HTML forms do not natively submit DELETE, and the UI contract requires confirmation. [VERIFIED: 05-UI-SPEC.md:130-137; quote: “Require the documented confirmation before DELETE.”]

**How to avoid:** Use a POST form with `@csrf` and `@method('delete')`, plus the agreed confirmation interaction; test cancellation leaves the row unchanged. [CITED: https://laravel.com/docs/11.x/routing#form-method-spoofing] [VERIFIED: 05-UI-SPEC.md:127; quote: “delete requires the documented confirmation before issuing DELETE”]

## Code Examples

Verified patterns from official sources and repository conventions:

### Relation-backed workspace access

```php
// Proposed implementation skeleton; exact Sender class/table names are planning decisions.
$workspace = $request->user()->currentWorkspace();
$senders = $workspace->senders()->latest()->get();
$sender = $workspace->senders()->findOrFail($senderId);
```

The `currentWorkspace()` method and its nullable return are verified in the source; the `senders()` relation and `$senderId` names are proposed. [VERIFIED: app/Traits/HasWorkspaces.php:89-107; quote: “public function currentWorkspace(): ?Workspace”] [ASSUMED]

### Form Request validation

```php
// Proposed skeleton using Laravel's documented Form Request pattern.
public function rules(): array
{
    return [
        'label' => ['required', 'string', 'max:255'],
        'from_name' => ['required', 'string', 'max:255'],
        'from_email' => ['required', 'email', 'max:255'],
    ];
}
```

Laravel documents Form Request `rules()` and the `required`, `string`, `email`, and `max` rules. [CITED: https://laravel.com/docs/11.x/validation#form-request-validation] [CITED: https://laravel.com/docs/11.x/validation#available-validation-rules] The field names are locked by the phase requirements; the length and normalization policy are recommendations. [VERIFIED: .planning/REQUIREMENTS.md:12-16; quote: “label, a From Name, and a From Email”] [ASSUMED]

### Existing host navigation seam

```php
// Existing verified host pattern; extend the resolver to render the sender fragment.
Sendportal::setSidebarHtmlContentResolver(
    static function () {
        return view('layouts.sidebar.manageUsersMenuItem')->render();
    }
);
```

The current source uses exactly this resolver and view call; the sender implementation should add/compose a separate sender menu fragment rather than edit the vendor sidebar. [VERIFIED: app/Providers/AppServiceProvider.php:58-61; quote: “Sendportal::setSidebarHtmlContentResolver” and “return view('layouts.sidebar.manageUsersMenuItem')->render();”]

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Owner-only host `/users` management | Separate shared-sender route group for all active-workspace members | Phase 5 decision, 2026-08-05 | Do not copy `OwnsCurrentWorkspace` from user management. [VERIFIED: 05-CONTEXT.md:17-19; quote: “Every member of the current workspace may create, view, edit, and delete sender identities.”] |
| Vendor sidebar or campaign edits | Host resolver/published-view seams | v1.1/v1.2 project boundary | Preserve vendor upgradeability. [VERIFIED: .planning/STATE.md:79-89; quote: “never edit vendor code”] |
| Unscoped record lookup | Workspace relation-scoped lookup | Phase 5 security requirement | Foreign IDs resolve as 404. [VERIFIED: 05-CONTEXT.md:19-20; quote: “404 Not Found”] |

**Deprecated/outdated:** None identified for this phase. Laravel's 11.x documentation page is marked as an older-version page because the site now serves Laravel 13.x, but this repository is locked to Laravel 11 and the cited 11.x concepts match the project stack. [CITED: https://laravel.com/docs/11.x/validation] [VERIFIED: composer.json:9; quote: “laravel/framework”: “^11.0”]

## Runtime State Inventory

Not a rename/refactor/migration-of-existing-data phase. The feature introduces a new host table and model; no existing sender runtime state was found in the inspected source. [VERIFIED: database/migrations directory inspection 2026-08-05; quote: existing host migrations include users, workspaces, invitations, and API tokens, with no sender migration]

## Common Runtime and Dependency Findings

PHP 8.4.23 and Composer 2.10.2 are available locally. MySQL and PostgreSQL client shims are present, while Docker was not reported by the availability probe; the repository CI itself provisions MySQL 5.7 and PostgreSQL services in GitHub Actions. [VERIFIED: environment probe 2026-08-05; quote: “PHP 8.4.23”, “Composer version 2.10.2”, “mysql”, and “psql”] [VERIFIED: .github/workflows/ci.yml:17-37; quote: “mysql:5.7” and “postgres”]

## Assumptions Log

| # | Claim | Section | Risk if Wrong |
|---|-------|---------|---------------|
| A1 | Use a `Sender` model/table and `Workspace::senders()` relation. | Architecture Patterns | Planner may need a different name, affecting routes, migration, and tests. |
| A2 | Normalize by trimming all fields and lowercasing only `from_email`. | Common Pitfalls | Duplicate behavior and later Phase 7 auto-capture could differ. |
| A3 | A composite uniqueness rule on normalized workspace/from-name/from-email is desirable, but its enforcement timing is a planning decision. | Common Pitfalls | Adding it now could constrain future capture semantics; omitting it could permit duplicates. |
| A4 | A server-rendered form is smaller and safer than introducing Livewire for this CRUD. | Alternatives Considered | The UI may need more interactivity than the current shell provides. |
| A5 | A relation-first `findOrFail` query is preferable to custom route binding. | Summary / Pattern 1 | Binding conventions could be preferred if the project adds a reusable scoped-binding abstraction. |

## Open Questions

1. **What exact duplicate policy should Phase 5 lock?**
   - What we know: Phase 7 requires identical From Name/From Email pairs to be deduplicated, while Context leaves duplicate policy and normalization to discretion. [VERIFIED: REQUIREMENTS.md:20-21; quote: “deduplicated so an identical pair is never stored twice”] [VERIFIED: 05-CONTEXT.md:23-25; quote: “Duplicate sender policy and field normalization were not discussed”]
   - What's unclear: Whether duplicate prevention belongs in a database unique index now, application validation now, or the Phase 7 capture service only.
   - Recommendation: Lock normalization before implementation; prefer trim + lowercase email and enforce the normalized pair at the database/application boundary if it does not conflict with the campaign capture seam. [ASSUMED]
2. **Should create and edit use one form view or separate views?**
   - What we know: The UI contract permits a smallest package-layout Blade approach and requires distinct create/edit copy. [VERIFIED: 05-UI-SPEC.md:80-104; quote: “Form heading (create) | `Add Sender`” and “Form heading (edit) | `Edit Sender`”]
   - What's unclear: Whether the planner prefers one conditional form or separate create/edit views.
   - Recommendation: Use one reusable form partial with separate page wrappers or a single page conditional; preserve the exact UI copy and server-side validation behavior. [ASSUMED]

## Validation Architecture

### Test Framework

| Property | Value |
|----------|-------|
| Framework | PHPUnit 10 (`^10.5`) |
| Config file | `phpunit.xml.dist` |
| Quick run command | `vendor/bin/phpunit tests/Feature/Workspaces/SenderControllerTest.php` |
| Full suite command | `vendor/bin/phpunit` |

These values are verified from the repository Composer manifest, PHPUnit config, and existing feature-test structure. [VERIFIED: composer.json:16-21; quote: “phpunit/phpunit”: “^10.5”] [VERIFIED: phpunit.xml.dist:2-10; quote: “<directory>tests</directory>”] [VERIFIED: tests/Feature/Workspaces/WorkspaceUserControllerTest.php:12-14; quote: “class WorkspaceUserControllerTest extends TestCase” and “use RefreshDatabase;”]

### Phase Requirements → Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| SENDER-01 | Member creates sender tied to current workspace | feature | `vendor/bin/phpunit tests/Feature/Workspaces/SenderControllerTest.php --filter=can_create` | ❌ Wave 0 |
| SENDER-02 | Active-workspace member sees navigation and only current workspace rows | feature | `vendor/bin/phpunit tests/Feature/Workspaces/SenderControllerTest.php --filter=can_view` | ❌ Wave 0 |
| SENDER-03 | Member edits any shared sender in current workspace | feature | `vendor/bin/phpunit tests/Feature/Workspaces/SenderControllerTest.php --filter=can_update` | ❌ Wave 0 |
| SENDER-04 | Member deletes sender with DELETE route | feature | `vendor/bin/phpunit tests/Feature/Workspaces/SenderControllerTest.php --filter=can_delete` | ❌ Wave 0 |
| SENDER-05 | Required/email validation and foreign sender 404 | feature | `vendor/bin/phpunit tests/Feature/Workspaces/SenderControllerTest.php --filter="validation|foreign"` | ❌ Wave 0 |

The test names and file path are proposed; existing tests use `RefreshDatabase`, `actingAs`, named routes, and status assertions. [VERIFIED: tests/Feature/Workspaces/WorkspaceUserControllerTest.php:21-63; quote: “actingAs($user)” and “assertStatus(404)”]

### Sampling Rate

- **Per task commit:** `vendor/bin/phpunit tests/Feature/Workspaces/SenderControllerTest.php`
- **Per wave merge:** `vendor/bin/phpunit`
- **Phase gate:** Full suite green before `/gsd-verify-work`; run both CI database variants when available. [VERIFIED: .github/workflows/ci.yml:66-77; quote: “Run Testsuite against MySQL” and “Run Testsuite against Postgres”]

### Wave 0 Gaps

- [ ] Sender model/factory if the test suite needs factory-generated sender records. [ASSUMED]
- [ ] `tests/Feature/Workspaces/SenderControllerTest.php` covering all five requirements. [ASSUMED]
- [ ] Migration must run under the existing `TestCase::setUp()` call to `artisan('migrate')`. [VERIFIED: tests/TestCase.php:17-25; quote: “$this->artisan('migrate')->run();”]

## Security Domain

### Applicable ASVS Categories

| ASVS Category | Applies | Standard Control |
|---------------|---------|-----------------|
| V2 Authentication | yes | `auth` middleware on all sender routes. [VERIFIED: routes/web.php:22-24; quote: “Route::middleware('auth')”] |
| V3 Session Management | yes | Existing verified web/session middleware and CSRF-protected Blade forms. [CITED: https://laravel.com/docs/11.x/routing#form-method-spoofing] |
| V4 Access Control | yes | `RequireWorkspace` plus relation-scoped sender queries; do not use owner-only middleware. [VERIFIED: app/Http/Middleware/RequireWorkspace.php:20-30; quote: “Sendportal::currentWorkspaceId();” and “abort(404);”] |
| V5 Input Validation | yes | Form Request rules for label, From Name, and From Email; use validated data only. [CITED: https://laravel.com/docs/11.x/validation#form-request-validation] |
| V6 Cryptography | no direct new cryptographic data | Sender fields are not secrets; do not log credentials or introduce encryption unnecessarily. [ASSUMED] |

### Known Threat Patterns for Laravel workspace CRUD

| Pattern | STRIDE | Standard Mitigation |
|---------|--------|---------------------|
| IDOR / cross-workspace sender ID | Elevation of privilege | Query through `currentWorkspace()->senders()` and assert 404 for foreign IDs. [VERIFIED: 05-CONTEXT.md:19-20; quote: “must resolve as `404 Not Found`”] |
| Mass assignment of tenant ownership | Tampering | Never accept `workspace_id` from the request; create through the workspace relation and validate only the three user fields. [ASSUMED] |
| Stored HTML in sender labels/names | Tampering / XSS | Escape values with normal Blade `{{ }}` output; do not use raw output for sender fields. [ASSUMED] |
| CSRF on update/delete | Tampering | Keep sender routes in `web.php` and include `@csrf`; use `@method` for non-POST verbs. [CITED: https://laravel.com/docs/11.x/routing#form-method-spoofing] |
| Enumeration through authorization response | Information disclosure | Return 404 for foreign workspace records, matching the locked decision. [VERIFIED: 05-CONTEXT.md:19-20; quote: “preserving tenant/resource concealment”] |

## Project Constraints (from AGENTS.md)

- PHP 8.4 must remain a supported installation target. [VERIFIED: AGENTS.md:11-16; quote: “PHP 8.4 must be a supported installation target”]
- Do not disable Composer platform checks or silently drop vulnerability protection. [VERIFIED: AGENTS.md:13-15; quote: “Do not disable Composer platform checks or silently drop vulnerability protection”]
- Preserve existing application behavior and Laravel 11/SendPortal Core integration. [VERIFIED: AGENTS.md:14-16; quote: “Preserve existing application behavior and Laravel 11/SendPortal Core integration”]
- Do not edit vendor code; use host routes, models, views, and resolver seams. [VERIFIED: .planning/REQUIREMENTS.md:43-49; quote: “Hard project boundary — the package must stay upgradable”]
- Use Form Requests for validation, thin controllers, constructor injection where dependencies exist, and transactions for multi-write operations. [VERIFIED: AGENTS.md:116-143; quote: “Put validation rules in a Form Request” and “Keep HTTP controllers thin”]
- Use strict types in new application, migration, route, and feature-test PHP files; apply PHP-CS-Fixer conventions. [VERIFIED: AGENTS.md:103-110; quote: “Add `declare(strict_types=1);`” and “Apply PHP-CS-Fixer”]
- Use 404 to conceal inaccessible workspace resources. [VERIFIED: AGENTS.md:118-121; quote: “Use `abort(404)` to conceal inaccessible workspace resources”]

## Environment Availability

| Dependency | Required By | Available | Version | Fallback |
|------------|------------|-----------|---------|----------|
| PHP | Application/tests | ✓ | 8.4.23 | — |
| Composer | Dependency/autoload/test commands | ✓ | 2.10.2 | — |
| MySQL client | Local DB verification | ✓ | Herd shim; version not reported | CI MySQL service |
| PostgreSQL client | Local DB verification | ✓ | Herd shim; version not reported | CI PostgreSQL service |
| Docker | Local service orchestration | ✗ | — | Use CI services or installed Herd database services |

The availability probe found PHP, Composer, MySQL, and PostgreSQL command paths; it did not report Docker. [VERIFIED: environment probe 2026-08-05; quote: “PHP 8.4.23”, “Composer version 2.10.2”, and paths for “mysql” and “psql”]

**Missing dependencies with no fallback:** None for implementation; DB-backed full verification may require CI if local databases are unavailable. [ASSUMED]

**Missing dependencies with fallback:** Docker is unavailable locally; use CI's declared MySQL/PostgreSQL services. [VERIFIED: .github/workflows/ci.yml:17-37; quote: “services: mysql” and “services: postgres”]

## Sources

### Primary (HIGH confidence)

- `.planning/phases/05-sender-identity-management/05-CONTEXT.md` — locked authorization, tenancy, scope, and discretion.
- `.planning/ROADMAP.md` and `.planning/REQUIREMENTS.md` — phase goal, SENDER-01 through SENDER-05, and out-of-scope boundaries.
- `AGENTS.md` — project constraints and coding conventions.
- `routes/web.php`, `RequireWorkspace.php`, `HasWorkspaces.php`, `Workspace.php`, `WorkspaceUsersController.php`, existing Blade views, and feature tests — verified host patterns.
- `vendor/mettle/sendportal-core/src/SendportalBaseServiceProvider.php`, `Services/Sendportal.php`, and sidebar view — verified package integration seams.

### Secondary (MEDIUM confidence)

- [Laravel 11 validation documentation](https://laravel.com/docs/11.x/validation) — Form Requests, rules, redirect/error behavior.
- [Laravel 11 Eloquent relationships documentation](https://laravel.com/docs/11.x/eloquent-relationships) — `hasMany`, relation query constraints, and child creation.
- [Laravel 11 routing documentation](https://laravel.com/docs/11.x/routing) — route groups, implicit binding, 404 behavior, CSRF/method spoofing.
- [Laravel 11 migrations documentation](https://laravel.com/docs/11.x/migrations) — schema, foreign keys, indexes, timestamps, and reversible migrations.
- [Laravel 11 controllers documentation](https://laravel.com/docs/11.x/controllers#resource-controllers) — resource CRUD route conventions.

### Tertiary (LOW confidence)

- None used for a locked implementation claim. Recommendations marked `[ASSUMED]` require planner confirmation.

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — existing Composer manifest and local PHP/Composer probe; no new package required.
- Architecture: HIGH — existing routes, middleware, models, views, tests, and package seam were opened this session.
- Pitfalls: MEDIUM — tenancy risks are directly evidenced; normalization/collation recommendations remain assumptions.

**Research date:** 2026-08-05
**Valid until:** 2026-09-04 for stable repository architecture; revisit framework guidance if Laravel 11 support or the locked dependency graph changes.
