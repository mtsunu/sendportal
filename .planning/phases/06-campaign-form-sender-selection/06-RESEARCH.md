# Phase 6: Campaign Form Sender Selection - Research

**Researched:** 2026-08-06
**Domain:** Laravel 11 view composition and SendPortal Core campaign-view overrides
**Confidence:** HIGH

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions
- **D-01:** Use a native single-select dropdown styled consistently with the existing package/Bootstrap form controls rather than a custom searchable widget.
- **D-02:** Render each sender option as `Label — from@example.com`, using the user-defined label plus email to distinguish entries.
- **D-03:** Selecting a sender replaces both From Name and From Email values. The two inputs remain manually editable after autofill.
- **D-04:** When no saved senders exist, keep an enabled blank picker with no special disabled-state or empty-state link; the ordinary manual From Name and From Email inputs remain the usable path.
- **D-05:** Preserve a blank option as the initial selection so the campaign create form has no pre-selected sender. The dropdown is a convenience selector, not a submitted campaign identity field.

### the agent's Discretion
- Exact placeholder wording for the blank option, provided it is visibly unselected and does not submit a sender identity.
- The minimal JavaScript mechanism and DOM hooks used to copy selected sender values into the existing inputs.

### Deferred Ideas (OUT OF SCOPE)
None — discussion stayed within phase scope.
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|------------------|
| SENDER-06 | When creating a campaign, the user can pick a saved sender from a dropdown that auto-fills the From Name and From Email fields; both fields remain manually editable and no sender is pre-selected by default. | The shared package form is included by both create and edit wrappers; a host view composer can inject current-workspace senders and a published partial can render a blank native select plus change handler. |
| SENDER-08 | The saved-sender UI on the campaign form is delivered with zero edits to `vendor/mettle/sendportal-core` — surfaced via a published/overridden package view and host-side seams (the same discipline as v1.1's `ThrottledSesAdapter`). | `SendportalBaseServiceProvider` registers the `sendportal` namespace and publishes its views to `resources/views/vendor/sendportal`; `AppServiceProvider` already owns host package seams. |
</phase_requirements>

## Summary

The package campaign create and edit actions return `sendportal::campaigns.create` and `sendportal::campaigns.edit`. Both wrappers include the same `sendportal::campaigns.partials.form`, but the package controller only passes `templates` and `emailServices` on create and `campaign`, `emailServices`, and `templates` on edit. It never passes saved senders. [VERIFIED: `vendor/mettle/sendportal-core/src/Http/Controllers/Campaigns/CampaignsController.php:92-103,129-141`] The smallest robust seam is a narrowly targeted `View::composer` registered in `AppServiceProvider::boot()` for the two wrapper view names; it can load `$user->currentWorkspace()->senders()->orderBy('label')->get()` and attach `senders` without replacing package controllers or routes. Laravel documents composers as render-time data binding and supports registering one composer for multiple views. [CITED: https://laravel.com/docs/11.x/views#view-composers]

Publish/override only the campaign views needed by the form, preserving the package wrapper structure and changing the shared partial to add a native blank select before the existing From fields. Laravel's package view loader checks `resources/views/vendor/<namespace>` before the package source, and this package explicitly publishes `resources/views` to `resources/views/vendor/sendportal`. [CITED: https://laravel.com/docs/11.x/packages#overriding-package-views] [VERIFIED: `vendor/mettle/sendportal-core/src/SendportalBaseServiceProvider.php:21-30,50-53`] The override therefore survives vendor updates, but it is intentionally coupled to the package's current partial markup and variable names; an upgrade must re-diff the published copies.

**Primary recommendation:** Add a targeted host `View::composer` for `sendportal::campaigns.create` and `sendportal::campaigns.edit`, publish/override `campaigns/partials/form.blade.php` (and retain synchronized create/edit wrappers if publishing the complete package view tree), render sender data as escaped `data-from-name`/`data-from-email` attributes, and use the existing `@push('js')` jQuery stack to copy both values on `change` while leaving the picker unnamed or otherwise non-submitted.

## Architectural Responsibility Map

| Capability | Primary Tier | Secondary Tier | Rationale |
|------------|-------------|----------------|-----------|
| Resolve saved senders for the form | Frontend Server (SSR) | Database / Storage | The package controller owns campaign data but lacks sender data; the host must bind workspace-scoped records during server-side view composition. [VERIFIED: `vendor/mettle/sendportal-core/src/Http/Controllers/Campaigns/CampaignsController.php:92-103,129-141`] |
| Render the sender picker and options | Frontend Server (SSR) | Browser / Client | Blade must emit the accessible select and escaped sender attributes; the browser only performs convenience autofill. [VERIFIED: `vendor/mettle/sendportal-core/resources/views/campaigns/partials/form.blade.php:1-8`] |
| Copy selected values into From fields | Browser / Client | Frontend Server (SSR) | The existing form already pushes jQuery JavaScript and the package layout loads jQuery before `@stack('js')`. [VERIFIED: `vendor/mettle/sendportal-core/resources/views/campaigns/partials/form.blade.php:24-58`; `vendor/mettle/sendportal-core/resources/views/layouts/base.blade.php:27-42`] |
| Enforce campaign submission contract | API / Backend | Database / Storage | The package request still requires editable `from_name` and `from_email`; the picker must not become a required or trusted campaign identity field. [VERIFIED: `vendor/mettle/sendportal-core/src/Http/Requests/CampaignStoreRequest.php:10-60`] |
| Enforce sender tenancy | Database / Storage | Frontend Server (SSR) | `Workspace::senders()` is a host-owned `HasMany` relation, and campaign routes are behind `RequireWorkspace`; do not query `Sender::query()` globally. [VERIFIED: `app/Models/Workspace.php:107-112`; `routes/web.php:126-130`; `app/Http/Middleware/RequireWorkspace.php:18-30`] |

## Standard Stack

### Core

| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Laravel `Illuminate\Support\Facades\View` | Laravel 11 (`laravel/framework` `^11.0`) | Targeted view composer registration | Native Laravel mechanism for binding data to views rendered by multiple routes. [CITED: https://laravel.com/docs/11.x/views#view-composers] [VERIFIED: `composer.json:6-14`] |
| Blade package views | SendPortal Core `v3.0.2` | Override the shared campaign form without vendor edits | The lockfile resolves `mettle/sendportal-core` to `v3.0.2`; its provider loads the `sendportal` namespace and publishes views. [VERIFIED: `composer.lock:2948-2953,2982-2987`; `vendor/mettle/sendportal-core/src/SendportalBaseServiceProvider.php:29-30,52`] |
| `App\Models\Sender` / `Workspace::senders()` | Host code | Workspace-scoped sender records | Phase 5 centralizes sender normalization and tenancy in the host model/relation. [VERIFIED: `app/Models/Sender.php:21-46`; `app/Models/Workspace.php:107-112`] |

### Supporting

| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| Native HTML `<select>` | Browser platform | Single sender picker with blank initial option | Required by D-01; native controls provide keyboard/accessibility behavior without a new package. [CITED: https://developer.mozilla.org/en-US/docs/Web/HTML/Reference/Elements/select] |
| Existing package jQuery asset | Package-published asset | Minimal `change` handler in the existing JS stack | Use only because the package form already uses `$`, `$(function(){})`, and `@push('js')`; do not add an npm dependency. [VERIFIED: `vendor/mettle/sendportal-core/resources/views/campaigns/partials/form.blade.php:24-58`; `vendor/mettle/sendportal-core/resources/views/layouts/base.blade.php:27-42`] |

**Installation:** No package installation is needed. Do not add Composer, npm, or other dependencies for this phase. [VERIFIED: `composer.json:6-22`; no `package.json` exists at repository root per project stack context.] 

## Architecture Patterns

### System Architecture Diagram

```text
Authenticated + verified + RequireWorkspace request
                    |
                    v
SendPortal Core campaign route -> CampaignsController::create/edit
                    |             (templates/emailServices/campaign only)
                    v
View::composer for sendportal::campaigns.create/edit
                    |
                    v
auth()->user()->currentWorkspace()->senders()->orderBy('label')->get()
                    |
                    v
Package namespace resolves host override in resources/views/vendor/sendportal
                    |
                    v
Shared campaign form: blank native select + escaped sender options
                    |
                    v
Browser change event -> copy option data into existing from_name/from_email inputs
                    |
                    v
CampaignStoreRequest validates submitted editable fields; sender picker identity is not submitted
```

### Recommended Project Structure

```text
app/Providers/AppServiceProvider.php                 # targeted View::composer seam
resources/views/vendor/sendportal/campaigns/
├── create.blade.php                                 # published package wrapper, synchronized
├── edit.blade.php                                   # published package wrapper, synchronized
└── partials/form.blade.php                          # sender select + existing form + JS
tests/Feature/Workspaces/CampaignSenderSelectionTest.php  # recommended phase coverage [ASSUMED]
```

The exact override files are a planning choice based on the package publish contract; the source-of-truth package paths are `resources/views/campaigns/create.blade.php`, `edit.blade.php`, and `partials/form.blade.php`. [VERIFIED: `vendor/mettle/sendportal-core/resources/views/campaigns/create.blade.php:1-33`; `edit.blade.php:1-28`; `partials/form.blade.php:1-58`]

### Pattern 1: Targeted render-time view composer

**What:** Register one composer against the two wrapper views and attach a `senders` collection to the view. [CITED: https://laravel.com/docs/11.x/views#view-composers]

**When to use:** When a package controller renders a stable named view but does not expose all host-owned presentation data. This avoids global `View::share`, controller replacement, and a database query in every unrelated view.

**Implementation shape:** [ASSUMED code shape; exact import/closure formatting should follow project conventions]

```php
use App\Models\User;
use Illuminate\Support\Facades\View;
use Illuminate\View\View as ViewInstance;

View::composer(
    ['sendportal::campaigns.create', 'sendportal::campaigns.edit'],
    static function (ViewInstance $view): void {
        /** @var User|null $user */
        $user = auth()->user();

        $view->with('senders', $user?->currentWorkspace()?->senders()->orderBy('label')->get() ?? collect());
    }
);
```

The exact selected view names and sender relation in this example are verified values: `view('sendportal::campaigns.create', ...)` and `view('sendportal::campaigns.edit', ...)` appear at controller lines 102 and 140, and `Workspace::senders(): HasMany` appears at `app/Models/Workspace.php:109-112`. [VERIFIED: `vendor/mettle/sendportal-core/src/Http/Controllers/Campaigns/CampaignsController.php:92-103,129-141`; `app/Models/Workspace.php:107-112`]

### Pattern 2: Data attributes plus change-only autofill

Render the option text as `{{ $sender->label }} — {{ $sender->from_email }}` and put the two values in escaped HTML attributes. The blank option should have `value=""` and no `selected` attribute; it must be the first option. [VERIFIED: `app/Models/Sender.php:12-16` defines `label`, `from_name`, and `from_email`; `vendor/mettle/sendportal-core/resources/views/components/select-field.blade.php:2-5` uses escaped `{{ }}` for option keys/text; MDN select guidance states the first option is selected when no option has `selected`. [CITED: https://developer.mozilla.org/en-US/docs/Web/HTML/Reference/Elements/select]]

Use stable DOM hooks such as `id="campaign-sender-picker"`, `data-from-name`, and `data-from-email`; on `change`, read the selected option and assign both existing fields. Do not make the picker a submitted campaign field: omit `name` from the convenience select, or use a name outside the request contract and explicitly verify it is ignored. Omitting `name` is the least ambiguous option. [CITED: https://developer.mozilla.org/en-US/docs/Web/HTML/Reference/Elements/select]

### Anti-Patterns to Avoid

- **Querying `Sender::all()` or `Sender::query()` in Blade:** loses the active-workspace boundary; query through `currentWorkspace()->senders()` only. [VERIFIED: `app/Models/Workspace.php:107-112`; prior Phase 5 relation-first contract in `05-02-SUMMARY.md:130-134`]
- **Replacing the package controller or route definitions:** larger upgrade surface and unnecessary for view data; the existing controller already renders stable named views. [VERIFIED: `vendor/mettle/sendportal-core/src/Http/Controllers/Campaigns/CampaignsController.php:92-103,129-141`]
- **Global `View::share('senders', ...)`:** exposes a campaign-only query/data contract to unrelated views; Laravel documents it as sharing with all views. [CITED: https://laravel.com/docs/11.x/views#sharing-data-with-all-views]
- **Submitting `sender_id` as a campaign field:** violates D-05 and creates a false persistence/security contract; the package request has no sender field and already requires `from_name`/`from_email`. [VERIFIED: `vendor/mettle/sendportal-core/src/Http/Requests/CampaignStoreRequest.php:31-39`]
- **Raw Blade or unescaped JavaScript interpolation for sender values:** labels/names/emails are user-controlled; use escaped Blade attributes or JSON encoding appropriate to the context, and test quotes/HTML characters.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| View data injection | Controller fork, route fork, or global shared state | Laravel targeted `View::composer` in `AppServiceProvider::boot()` | It is the framework-supported render-time binding seam and covers create/edit together. [CITED: https://laravel.com/docs/11.x/views#view-composers] |
| Select accessibility/search UI | Custom ARIA combobox or search widget | Native single `<select>` | D-01 explicitly chooses the native control; MDN documents its label/value/option semantics. [CITED: https://developer.mozilla.org/en-US/docs/Web/HTML/Reference/Elements/select] |
| Sender tenancy query | New repository or global sender lookup | Existing `Workspace::senders()` relation | Phase 5 already defines the host-owned relation and relation-first isolation contract. [VERIFIED: `app/Models/Workspace.php:107-112`; `05-02-SUMMARY.md:130-134`] |
| Frontend build | New JS package/build pipeline | Existing package jQuery and `@push('js')` | The form and layout already provide the exact runtime seam. [VERIFIED: `vendor/mettle/sendportal-core/resources/views/campaigns/partials/form.blade.php:24-58`; `vendor/mettle/sendportal-core/resources/views/layouts/base.blade.php:27-42`] |

**Key insight:** This phase is presentation-only. The picker must copy values into the package's existing required fields; it must not introduce sender persistence, provider selection, reply-to, default selection, or a new request field. [VERIFIED: `vendor/mettle/sendportal-core/src/Http/Requests/CampaignStoreRequest.php:31-39`; [CITED: `.planning/REQUIREMENTS.md:20-25,34-50`]]

## Common Pitfalls

### Pitfall 1: Create works but edit does not
**What goes wrong:** The create wrapper and edit wrapper both include the shared partial, but edit has a `$campaign` while create does not. [VERIFIED: `vendor/mettle/sendportal-core/resources/views/campaigns/create.blade.php:24-27`; `edit.blade.php:18-22`]
**Why it happens:** An override copies only one wrapper or assumes the package controller passes `senders`.
**How to avoid:** Composer-bind both exact wrapper names and test both GET paths; preserve `$campaign->from_name ?? old('from_name')` and `$campaign->from_email ?? old('from_email')`. [VERIFIED: `vendor/mettle/sendportal-core/resources/views/campaigns/partials/form.blade.php:3-4`]
**Warning signs:** Sender options appear on `/campaigns/create` but not `/campaigns/{id}/edit`, or edit loses existing campaign values.

### Pitfall 2: Blank option is accidentally selected incorrectly
**What goes wrong:** A `selected` attribute, non-empty value, or use of `$campaign` as picker value violates no-default behavior.
**How to avoid:** Put a first `<option value="">` with no `selected`; give the select no `name`; only the user change event copies data. [CITED: https://developer.mozilla.org/en-US/docs/Web/HTML/Reference/Elements/select]

### Pitfall 3: User-controlled values break markup or script
**What goes wrong:** A label containing quotes/HTML can break a `data-*` attribute or become script injection if interpolated into JavaScript.
**How to avoid:** Use normal escaped Blade interpolation in attributes and option text; if embedding a serialized map, use Laravel's context-safe JSON facilities rather than concatenated JavaScript. Add a feature assertion using a hostile label/name/email and inspect rendered HTML.

### Pitfall 4: Cross-workspace data leakage
**What goes wrong:** A global sender query lists records from another workspace, or a composer runs outside authenticated campaign requests and assumes a user exists.
**How to avoid:** Resolve from `auth()->user()->currentWorkspace()->senders()` and return an empty collection when no authenticated/current workspace exists. The campaign routes are protected by `auth`, `verified`, and `RequireWorkspace`, but defensive null handling keeps the composer safe in view/unit tests. [VERIFIED: `routes/web.php:126-130`; `app/Http/Middleware/RequireWorkspace.php:18-30`]

### Pitfall 5: Published view drifts from package upgrades
**What goes wrong:** A full copied package partial silently misses new package fields or JS changes after `mettle/sendportal-core` changes.
**How to avoid:** Keep the override minimal, retain all current package fields and JavaScript, pin/review the package lock, and add a vendor-boundary diff plus rendered create/edit smoke checks to the phase gate. Current installed package is `v3.0.2`, released in the lock metadata at `2024-05-08T15:45:54+00:00`. [VERIFIED: `composer.lock:2948-3006`]

## Code Examples

### Composer registration

Laravel's documented closure composer pattern is the model for the host seam. [CITED: https://laravel.com/docs/11.x/views#view-composers]

```php
View::composer(['sendportal::campaigns.create', 'sendportal::campaigns.edit'], function (View $view): void {
    $view->with('senders', /* currentWorkspace()->senders() */);
});
```

### Native picker contract

```html
<select id="campaign-sender-picker" class="form-control">
    <option value="">Choose a saved sender</option>
    <!-- each real option: escaped label/email text and data-from-name/data-from-email -->
</select>
```

The exact form component currently emits `<select name="...">` and escaped `<option value="...">...</option>` markup. [VERIFIED: `vendor/mettle/sendportal-core/resources/views/components/select-field.blade.php:1-6`] For this convenience-only control, a handwritten native select is preferable because the component requires a `name` and its options are submitted by normal HTML form behavior.

### Minimal browser behavior

```javascript
$('#campaign-sender-picker').on('change', function () {
    const option = this.options[this.selectedIndex];
    $('input[name="from_name"]').val(option.dataset.fromName || '');
    $('input[name="from_email"]').val(option.dataset.fromEmail || '');
});
```

This uses the existing jQuery loaded at `base.blade.php:27` and the existing form's `@push('js')` slot at `partials/form.blade.php:24-58`. [VERIFIED: those source lines] The exact DOM IDs/data attributes are implementation discretion; the behavior must copy both fields and remain editable afterward.

## Project Constraints (from AGENTS.md)

- PHP 8.4 must be a supported installation target — this is the stated project outcome. [VERIFIED: `AGENTS.md:11-14`]
- Do not disable Composer platform checks or silently drop vulnerability protection — installation must remain trustworthy. [VERIFIED: `AGENTS.md:13-16`]
- Preserve existing application behavior and Laravel 11/SendPortal Core integration — this is a focused compatibility milestone. [VERIFIED: `AGENTS.md:14-16`]
- Commit `composer.lock` once a valid graph is resolved — unpinned fresh resolution currently creates machine-to-machine drift. [VERIFIED: `AGENTS.md:15-16`]
- Use one PSR-4 class per file, project naming conventions, strict types in new PHP files, PHP-CS-Fixer-compatible style, and no source-path aliases. [VERIFIED: `AGENTS.md:92-114`]
- Keep controllers thin, use Form Requests for validation, use constructor injection where established, and use transactions for multi-write operations. [VERIFIED: `AGENTS.md:116-143`]
- Do not edit vendor files; package features belong in host `app/`/`resources/views/` and package route delegation remains in `routes/`. [VERIFIED: `AGENTS.md:259-267`]
- Before direct repository edits, use a GSD workflow entry point; this research artifact was initialized through the phase operation. [VERIFIED: `AGENTS.md:294-306`]

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Modify package campaign partial/controller in `vendor/` | Publish/override package views and bind host data through a service-provider composer | Project v1.2 boundary; current package v3.0.2 [VERIFIED: `composer.lock:2948-3006`] | Vendor upgrades remain possible, but override drift must be reviewed. |
| Global view sharing for campaign-only data | Targeted composers for `sendportal::campaigns.create` and `.edit` | Laravel-supported pattern [CITED: https://laravel.com/docs/11.x/views#view-composers] | Limits query/data scope and keeps unrelated views unaffected. |

**Deprecated/outdated:** No project dependency or package API is being deprecated by this phase. Avoid introducing a custom select library or frontend build system; those are outside the locked scope. [ASSUMED recommendation]

## Validation Architecture

### Test Framework

| Property | Value |
|----------|-------|
| Framework | PHPUnit 10.5.64 [VERIFIED: `phpunit --version` probe; `composer.json:20-22`] |
| Config file | `phpunit.xml.dist` [VERIFIED: file exists] |
| Quick run command | `vendor/bin/phpunit tests/Feature/Workspaces/CampaignSenderSelectionTest.php` [ASSUMED proposed file] |
| Full suite command | `vendor/bin/phpunit` [VERIFIED: Phase 5 summaries and installed PHPUnit] |

### Phase Requirements → Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| SENDER-06 | Create form renders blank picker, current-workspace options, exact label/email text, and no sender selected; edit renders the same picker while retaining campaign fields. | feature/rendered HTML | `vendor/bin/phpunit tests/Feature/Workspaces/CampaignSenderSelectionTest.php` | ❌ Wave 0 [ASSUMED path] |
| SENDER-06 | Browser change handler copies both sender values and does not disable later manual editing. | rendered JS contract + manual browser | same focused command plus end-of-phase browser check | ❌ Wave 0 [ASSUMED path] |
| SENDER-06 | Empty sender collection renders enabled blank picker and ordinary manual fields. | feature/rendered HTML | same focused command | ❌ Wave 0 [ASSUMED path] |
| SENDER-08 | No files under `vendor/mettle/sendportal-core` change; host override and AppServiceProvider seam are present. | repository/integration | `git diff --check && test -z "$(git diff --name-only -- vendor/mettle/sendportal-core)"` | ✅ existing command pattern [VERIFIED: `05-02-SUMMARY.md:112-118`] |

### Sampling Rate

- **Per task commit:** `vendor/bin/phpunit tests/Feature/Workspaces/CampaignSenderSelectionTest.php` [ASSUMED proposed focused suite]
- **Per wave merge:** `vendor/bin/phpunit` [VERIFIED: existing project command]
- **Phase gate:** Full suite green, focused create/edit/empty/escaping assertions green, and zero vendor diff before `/gsd-verify-work`.

### Wave 0 Gaps

- [ ] Add the focused campaign sender selection feature test [ASSUMED path].
- [ ] Add campaign test fixtures/factories only if the existing package test setup cannot create a draft campaign and required email service; prefer existing factories before new infrastructure. [ASSUMED]
- [ ] Add a manual browser check for create and edit change behavior because PHPUnit can verify emitted HTML/JS but not execute the browser event. [ASSUMED]

## Security Domain

### Applicable ASVS Categories

| ASVS Category | Applies | Standard Control |
|---------------|---------|-----------------|
| V2 Authentication | yes | Existing `auth` and `verified` middleware on package campaign routes. [VERIFIED: `routes/web.php:126-130`] |
| V3 Session Management | yes | Preserve existing Laravel web session/form flow; do not add a parallel endpoint. [VERIFIED: `routes/web.php:126-130`] |
| V4 Access Control | yes | Use current authenticated user's active workspace relation; never globally list senders. [VERIFIED: `app/Models/Workspace.php:107-112`; `app/Providers/AppServiceProvider.php:37-55`] |
| V5 Input Validation | yes | Existing package `CampaignStoreRequest` validates required `from_name` and `from_email`; picker is not a new trust/input field. [VERIFIED: `vendor/mettle/sendportal-core/src/Http/Requests/CampaignStoreRequest.php:31-39`] |
| V6 Cryptography | no | No secrets or cryptographic operation is added. [ASSUMED based on phase scope] |

### Known Threat Patterns for Laravel/Blade campaign form

| Pattern | STRIDE | Standard Mitigation |
|---------|--------|---------------------|
| Sender from another workspace appears in options | Information disclosure / Elevation | `$user->currentWorkspace()->senders()` and test two workspaces; no global query. |
| Label/name/email breaks HTML or script | Tampering / XSS | Escaped Blade text/attributes; hostile-value rendered HTML test; avoid raw JavaScript concatenation. |
| Picker is trusted as campaign identity | Tampering | Do not submit `sender_id`; package request validates the two editable fields server-side. |
| Package upgrade removes or changes override contract | Availability | Keep vendor lock reviewed, compare published override with upstream views, and run create/edit smoke tests after dependency updates. |

## Alternatives Considered

| Approach | Decision | Tradeoff |
|----------|----------|----------|
| Targeted `View::composer` + partial override | **Use** | Small host seam, no controller fork, data is request/render scoped; coupled to stable package view names. |
| Override create/edit wrappers and query sender data directly in Blade | Reject | Avoids a composer but mixes persistence into presentation and duplicates tenancy logic. [ASSUMED evaluation] |
| Replace package campaign routes/controller with host controller | Reject | Largest surface, duplicates package behavior, and increases upgrade drift. [ASSUMED evaluation] |
| Global `View::share` | Reject | Data is available to every view and query scope is too broad. [CITED: https://laravel.com/docs/11.x/views#sharing-data-with-all-views] |
| Custom searchable/select package | Reject | Contradicts D-01 and adds dependency/accessibility/build risk. [VERIFIED: `06-CONTEXT.md:17-21`] |

## Environment Availability

| Dependency | Required By | Available | Version | Fallback |
|------------|------------|-----------|---------|----------|
| PHP | Laravel tests/lint | ✓ | 8.4.23 | — |
| Composer | Dependency metadata | ✓ | 2.10.2 | — |
| PHPUnit | Focused/full validation | ✓ | 10.5.64 | — |
| SendPortal Core vendor tree | View/controller inspection and runtime | ✓ | v3.0.2 | — |
| Browser with JavaScript | Final change-event verification | Not probed | — | Manual end-of-phase verification required [ASSUMED] |

## Assumptions Log

| # | Claim | Section | Risk if Wrong |
|---|-------|---------|---------------|
| A1 | `tests/Feature/Workspaces/CampaignSenderSelectionTest.php` is the best new test path. | Validation Architecture | Planner may need to extend an existing campaign test or choose another file. |
| A2 | A null-safe empty collection fallback is desirable in the composer. | Pattern 1 | Exact unauthenticated view-test behavior may differ; campaign routes should still be protected. |
| A3 | The package's jQuery asset is available in every deployment rendering the package layout. | Standard Stack / Code Examples | Autofill will fail without JS; manual fallback remains usable, so browser verification is required. |
| A4 | The override should omit the picker `name` attribute rather than submit a non-contract field. | Pattern 2 | A package component may be preferred for visual consistency, but its required `name` makes omission less direct. |
| A5 | The package's current view names remain stable across future upgrades. | Pitfalls | A package upgrade can break the composer; lock and smoke tests must catch it. |

## Open Questions (RESOLVED)

1. **Should the publish operation copy the whole package view tree or only the three campaign files?**
   - What we know: The provider publishes the whole package `resources/views` directory to `resources/views/vendor/sendportal`. [VERIFIED: `vendor/mettle/sendportal-core/src/SendportalBaseServiceProvider.php:28-30`]
   - Resolution: Use the smallest three-file override needed by the shared form (`campaigns/create.blade.php`, `campaigns/edit.blade.php`, and `campaigns/partials/form.blade.php`). Preserve exact wrapper content and do not copy unrelated package views, minimizing upgrade drift.

2. **Can the existing test harness render package campaign create/edit pages with valid package fixtures?**
   - What we know: PHPUnit and the package are installed; the package controller requires email services/templates and the campaign form requires campaign fields. [VERIFIED: `CampaignsController.php:92-103,129-141`; `CampaignStoreRequest.php:22-60`]
   - Resolution: Use deterministic named-view rendering with package-shaped template/email-service data and a lightweight campaign object for edit coverage. Retain `php artisan route:list --path=campaigns -v` as the route smoke check; do not add campaign persistence fixtures because this phase is presentation-only.

## Sources

### Primary (HIGH confidence)

- `vendor/mettle/sendportal-core/src/Http/Controllers/Campaigns/CampaignsController.php:92-103,129-141` — create/edit view names and current view data.
- `vendor/mettle/sendportal-core/resources/views/campaigns/create.blade.php:24-27` and `edit.blade.php:18-22` — both include the shared form.
- `vendor/mettle/sendportal-core/resources/views/campaigns/partials/form.blade.php:1-58` — existing fields, package components, and JS stack.
- `vendor/mettle/sendportal-core/src/SendportalBaseServiceProvider.php:21-30,50-53` — namespace loading and `sendportal-views` publish destination.
- `app/Models/Sender.php:21-46`, `app/Models/Workspace.php:107-112`, `app/Providers/AppServiceProvider.php:29-73` — host model and integration seams.
- `composer.lock:2948-3006` — installed SendPortal Core `v3.0.2` and source metadata.

### Secondary (MEDIUM confidence)

- [CITED: https://laravel.com/docs/11.x/views#view-composers] — official composer registration and multi-view behavior.
- [CITED: https://laravel.com/docs/11.x/packages#overriding-package-views] — official package view override precedence and publish pattern.
- [CITED: https://laravel.com/docs/11.x/providers#the-boot-method] — provider boot timing and composer registration location.
- [CITED: https://developer.mozilla.org/en-US/docs/Web/HTML/Reference/Elements/select] — native select option, blank value, label, and change behavior.

### Tertiary (LOW confidence)

- None. The remaining assumptions are explicitly listed in the Assumptions Log.

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — package version and all host/package seams were read directly; Laravel/MDN behavior is cited from official documentation.
- Architecture: HIGH — create/edit/controller/partial/provider paths were read end-to-end.
- Pitfalls: MEDIUM-HIGH — tenancy and escaping are verified in source; upgrade and browser-runtime risks are implementation risks requiring phase tests/manual verification.

**Research date:** 2026-08-06
**Valid until:** 2026-09-05, or until `mettle/sendportal-core` is upgraded.
