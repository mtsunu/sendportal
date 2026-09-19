# Phase 6: Campaign Form Sender Selection - Pattern Map

**Mapped:** 2026-08-06  
**Files analyzed:** 5 candidate new/modified files  
**Analogs found:** 5 / 5

## Scope and data flow

This is a host-side presentation/integration change, not a campaign persistence change:

```text
auth + verified + RequireWorkspace campaign request
  -> SendPortal Core CampaignsController::create/edit
  -> named package wrapper view
  -> host view composer attaches current-workspace senders
  -> host override includes shared campaign partial
  -> native select change event copies data attributes into existing from_* inputs
  -> existing CampaignStoreRequest validates/submits from_name/from_email
```

The picker is intentionally not a submitted field. Do not add a route, request rule, model, migration, `sender_id`, or vendor edit.

## File Classification

| New/Modified File | Role | Data Flow | Closest Analog | Match Quality |
|---|---|---|---|---|
| `app/Providers/AppServiceProvider.php` | provider/configuration seam | request-response / render-time data binding | existing SendPortal resolver and HTML resolver in the same file | exact seam, new composer use |
| `resources/views/vendor/sendportal/campaigns/create.blade.php` | package-layout view override | request-response / SSR | `vendor/.../campaigns/create.blade.php` and Phase 5 `resources/views/senders/create.blade.php` | exact package wrapper |
| `resources/views/vendor/sendportal/campaigns/edit.blade.php` | package-layout view override | request-response / SSR | `vendor/.../campaigns/edit.blade.php` and Phase 5 `resources/views/senders/edit.blade.php` | exact package wrapper |
| `resources/views/vendor/sendportal/campaigns/partials/form.blade.php` | shared form component/partial | request-response + browser transform | `vendor/.../campaigns/partials/form.blade.php`, Phase 5 `resources/views/senders/_form.blade.php` | exact shared form/JS seam |
| `tests/Feature/Workspaces/CampaignSenderSelectionTest.php` | feature/integration test | request-response / rendered HTML + tenancy | `tests/Feature/Workspaces/SenderControllerTest.php` | role/data-flow match |

No model, service, controller, route, request, migration, or JavaScript build file is indicated. `App\\Models\\Sender` and `Workspace::senders()` are reused unchanged.

## Pattern Assignments

### `app/Providers/AppServiceProvider.php` (provider/configuration, request-response render seam)

**Analog:** existing host package seams in `app/Providers/AppServiceProvider.php` (lines 29-72).

**Imports/style pattern** (lines 1-18):

```php
declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\ServiceProvider;
use Sendportal\Base\Facades\Sendportal;
```

Keep the existing import ordering and `boot(): void`. Add the Laravel `View` facade and the concrete view type using the project's normal namespaced imports; do not introduce a controller fork or a global shared variable.

**Existing seam/core pattern** (lines 37-71):

```php
Sendportal::setCurrentWorkspaceIdResolver(
    static function () {
        /** @var User $user */
        $user = auth()->user();
        $request = request();
        // ... resolve the active workspace / API token tenancy ...
    }
);

Sendportal::setSidebarHtmlContentResolver(
    static function () {
        return view('layouts.sidebar.manageUsersMenuItem')->render()
            . view('layouts.sidebar.sendersMenuItem')->render();
    }
);
```

Register the new composer alongside these package seams in `boot()`. The closest data-binding shape from research is:

```php
View::composer(
    ['sendportal::campaigns.create', 'sendportal::campaigns.edit'],
    static function (ViewInstance $view): void {
        /** @var User|null $user */
        $user = auth()->user();

        $view->with(
            'senders',
            $user?->currentWorkspace()?->senders()->orderBy('label')->get() ?? collect()
        );
    }
);
```

The exact package view names are verified in `vendor/mettle/sendportal-core/src/Http/Controllers/Campaigns/CampaignsController.php:92-103,129-141`. Use the active user's workspace relation, never `Sender::query()`/`Sender::all()`. The null/empty fallback is useful for isolated rendering tests; normal campaign routes already have `auth`, `verified`, and `RequireWorkspace` (`routes/web.php:126-130`).

**Why this seam:** the package controller supplies only `templates`/`emailServices` on create and `campaign`/`emailServices`/`templates` on edit. It renders stable named views but has no sender data. A targeted composer is render-time, limited to the two campaign wrappers, and avoids changing package routes/controllers.

### `resources/views/vendor/sendportal/campaigns/create.blade.php` (view override, request-response SSR)

**Analog:** `vendor/mettle/sendportal-core/resources/views/campaigns/create.blade.php` (lines 1-33).

Preserve the package wrapper exactly except for synchronization into the host override. Required structure:

```blade
@extends('sendportal::layouts.app')

@section('title', __('Create Campaign'))
@section('heading', __('Campaigns'))

{{-- preserve package email-service empty callout and card --}}
<form action="{{ route('sendportal.campaigns.store') }}" method="POST" class="form-horizontal">
    @csrf
    @include('sendportal::campaigns.partials.form')
</form>
```

Do not move the form into a host controller, alter the action, remove CSRF, or change the package card/layout. The composer sees this wrapper name and its included partial receives `$templates`, `$emailServices`, and the optional `$campaign` context.

Phase 5 view analogs (`resources/views/senders/create.blade.php:1-21`) confirm host-owned pages extend `sendportal::layouts.app`, use package shell cards, and include a focused form partial. For this override, retain the package's existing wrapper rather than replacing it with the Phase 5 host wrapper.

### `resources/views/vendor/sendportal/campaigns/edit.blade.php` (view override, request-response SSR)

**Analog:** `vendor/mettle/sendportal-core/resources/views/campaigns/edit.blade.php` (lines 1-28).

Preserve edit-specific method/action and campaign values:

```blade
@extends('sendportal::layouts.app')

@section('title', __('Edit Campaign'))
@section('heading')
    {{ __('Edit Campaign') }}
@stop

<form action="{{ route('sendportal.campaigns.update', $campaign->id) }}" method="POST" class="form-horizontal">
    @csrf
    @method('PUT')
    @include('sendportal::campaigns.partials.form')
</form>
```

The shared partial must continue using `$campaign->from_name ?? old('from_name')` and `$campaign->from_email ?? old('from_email')` so edit loads preserve existing values and failed submissions preserve old input. Never infer the picker selection from campaign values; the select remains blank on edit.

### `resources/views/vendor/sendportal/campaigns/partials/form.blade.php` (shared form + client transform)

**Analog:** `vendor/mettle/sendportal-core/resources/views/campaigns/partials/form.blade.php` (lines 1-58).

**Existing field/import-free component pattern** (lines 1-13):

```blade
<x-sendportal.text-field name="name" :label="__('Campaign Name')" :value="$campaign->name ?? old('name')" />
<x-sendportal.text-field name="subject" :label="__('Email Subject')" :value="$campaign->subject ?? old('subject')" />
<x-sendportal.text-field name="from_name" :label="__('From Name')" :value="$campaign->from_name ?? old('from_name')" />
<x-sendportal.text-field name="from_email" :label="__('From Email')" type="email" :value="$campaign->from_email ?? old('from_email')" />
<x-sendportal.select-field name="template_id" :label="__('Template')" :options="$templates" :value="$campaign->template_id ?? old('template_id')" />
```

Insert the picker immediately before the existing From Name component. Use a handwritten native select because the package `x-sendportal.select-field` requires a `name` and emits a submitted field (`vendor/.../components/select-field.blade.php:1-6`), while this control must have no `name`.

**Picker rendering contract:**

```blade
<div class="form-group row">
    <label for="campaign-sender-picker" class="col-sm-3 col-form-label">
        {{ __('Saved Sender') }}
    </label>
    <div class="col-sm-9">
        <select id="campaign-sender-picker" class="form-control">
            <option value="">{{ __('Choose a saved sender') }}</option>
            @foreach ($senders as $sender)
                <option
                    value="{{ $sender->id }}"
                    data-from-name="{{ $sender->from_name }}"
                    data-from-email="{{ $sender->from_email }}"
                >{{ $sender->label }} — {{ $sender->from_email }}</option>
            @endforeach
        </select>
    </div>
</div>
```

Use escaped Blade interpolation for both option text and `data-*` attributes. Keep the blank option first, with `value=""`, no `selected`, and no `name`; it is a convenience control, not a campaign identity field. Sender options should already be label-ordered by the composer. The select should remain enabled and render only the blank option when `$senders` is empty.

**Existing JS stack and insertion point** (lines 22-58):

```blade
@push('js')
    <script>
        $(function () {
            // existing package initialization runs here
        });
    </script>
@endpush
```

Append the minimal change handler in this existing stack, preserving the package tracking behavior:

```javascript
$('#campaign-sender-picker').on('change', function () {
    const option = this.options[this.selectedIndex];
    $('input[name="from_name"]').val(option.dataset.fromName || '');
    $('input[name="from_email"]').val(option.dataset.fromEmail || '');
});
```

The package layout loads jQuery before `@stack('js')` (`vendor/.../layouts/base.blade.php:27-42`). The handler must be change-only: it must not overwrite existing edit/old values on page load, and selecting the blank option may clear both fields. Inputs remain the existing package components and therefore remain editable after autofill.

Retain all package fields, Summernote include, cancel link, submit button, tracking initialization, and `@push('js')` wrapper. Do not add a build pipeline or dependency.

### `tests/Feature/Workspaces/CampaignSenderSelectionTest.php` (feature test, request-response/rendered contract)

**Analog:** `tests/Feature/Workspaces/SenderControllerTest.php` (lines 1-317).

**Test setup pattern** (lines 1-14 and 20-35):

```php
declare(strict_types=1);

namespace Tests\Feature\Workspaces;

use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignSenderSelectionTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function ...(): void
    {
        $user = $this->createUserWithWorkspace();
        $this->actingAs($user);
        // request/assert rendered response
    }
}
```

`Tests\TestCase::setUp()` already disables Mix, enables exception handling, and runs migrations (`tests/TestCase.php:14-25`). Use `createUserWithWorkspace()`, `createWorkspaceUser()`, `Workspace::factory()`, and relation creation from `SenderControllerTest` rather than adding factories unless real package campaign fixtures prove necessary.

**Coverage to copy/extend:**

- authenticated active workspace user sees create picker and exact `Saved Sender`, `Choose a saved sender`, and `Label — email` output;
- edit renders the same picker while preserving `$campaign` From fields and leaving the picker blank;
- two workspaces prove foreign sender labels/emails are absent;
- zero senders proves an enabled blank picker and existing required From fields remain available;
- hostile quotes/HTML in label/name/email prove escaped option text/attributes and no raw script interpolation;
- rendered HTML asserts the picker has no `name`, no submitted `sender_id`, and first blank option has no `selected` attribute;
- rendered script asserts both `from_name` and `from_email` are assigned on change (browser interaction remains a manual verification item);
- repository safety assertion/check verifies no path under `vendor/mettle/sendportal-core` is changed.

Use sentence-like test names and `given/when/then` comments as established by project conventions. The Phase 5 boundary test at `SenderControllerTest.php:295-316` is a useful negative-boundary precedent: it asserts later campaign seams/classes do not exist. Phase 6 should replace that deferred assertion with positive campaign view-seam coverage, while retaining zero-vendor-edit verification.

## Shared Patterns

### Workspace tenancy

**Sources:** `app/Models/Workspace.php:106-112`, `app/Models/User.php:30-34`, `app/Providers/AppServiceProvider.php:37-55`, `routes/web.php:126-130`, and Phase 5 summary 05-02.

```php
public function senders(): HasMany
{
    return $this->hasMany(Sender::class);
}
```

Resolve the collection from the authenticated user's active workspace relation. This is a render-time read only; do not resolve senders globally or from a submitted ID. Campaign routes already pass through the package's protected web route group.

### Package view override boundary

**Source:** `vendor/mettle/sendportal-core/src/SendportalBaseServiceProvider.php:21-30,50-53`.

```php
$this->publishes([
    __DIR__.'/../resources/views' => resource_path('views/vendor/sendportal'),
], 'sendportal-views');

$this->loadViewsFrom(__DIR__.'/../resources/views', 'sendportal');
```

The host override directory wins over the package namespace at runtime. Copy only the three campaign files needed by the shared form (or otherwise document why a broader publish is required), and synchronize their untouched package structure. The vendor source is reference-only and must not be edited.

### Existing form and styling

Use package `.form-horizontal`, `.form-group row`, `offset-sm-3 col-sm-9`, `form-control`, package field components, Bootstrap cards, and package layout. Phase 5's `_form.blade.php:13-35` confirms the host's label/input/error conventions, but campaign fields must remain package components to preserve package behavior and validation display.

### Validation and manual fallback

`vendor/mettle/sendportal-core/src/Http/Requests/CampaignStoreRequest.php:22-60` requires `from_name`, `from_email`, `name`, `subject`, and email service fields; it has no sender field. Preserve this request contract. The picker only fills existing editable values in the browser. If JavaScript is unavailable or there are no senders, direct manual entry remains the valid path.

### Escaping

Phase 5 sender views use ordinary escaped Blade output (`resources/views/senders/index.blade.php:43-47`; `_form.blade.php:15-34`). Apply the same escaping to option text and data attributes. Never concatenate sender-controlled values into executable JavaScript.

### Zero-vendor-edit assertion

Phase 5 verification explicitly passed `git diff --name-only -- vendor/mettle/sendportal-core` empty (05-02-SUMMARY.md:112-118). Repeat that check at focused and phase gates, alongside `git diff --check`. The expected changed paths are host provider, host package overrides, and the new feature test only; no vendor path may appear.

## No Analog Found

None. There is no existing campaign sender picker, so the exact native select/change behavior is new. Its closest patterns are the package campaign partial's form/JS stack, the package select component's Bootstrap markup, and Phase 5's sender tenancy/escaping/test conventions.

## Planner Notes / Risks

- The package controller does not pass `$senders`; the composer must target both exact wrapper names.
- Create and edit both include the same partial, but edit has `$campaign`; preserve both contexts and do not infer a sender on edit.
- The package select component cannot be used unchanged because it always emits `name="..."`; handwritten native markup is the safer fit for a non-submitted convenience control.
- Full route tests may require package email-service/template/campaign fixtures. Prefer the real routes for smoke coverage; if package setup is impractical, render the overridden views with explicit package-shaped data and retain at least one route-level check.
- A PHP feature test can verify rendered HTML/JS contracts but cannot execute the browser event; manually verify create and edit selection, blank reset, and subsequent manual editing.

## Metadata

**Analog search scope:** host `app/`, `resources/views/`, `tests/`, `routes/`; Phase 5 summaries; installed `vendor/mettle/sendportal-core` campaign views, components, layouts, controller, request, and service provider.  
**Files scanned:** 5 candidate targets plus host models/provider/routes/test harness/Phase 5 views and tests and 11 package reference files.  
**Pattern extraction date:** 2026-08-06
