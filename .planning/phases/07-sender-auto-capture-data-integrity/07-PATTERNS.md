# Phase 7: Sender Auto-Capture & Data Integrity - Pattern Map

**Mapped:** 2026-08-06  
**Files analyzed:** 5 candidate new/modified files  
**Analogs found:** 5 / 5 role or data-flow matches; no exact host observer or atomic capture-service analog

## Scope and data flow

This phase is a host-side persistence integration. The package campaign controllers and any direct Eloquent save must converge on one host Eloquent lifecycle seam, while the existing campaign/message text columns remain historical snapshots:

```text
web package store ─┐
API package store ─┼─> vendor CampaignTenantRepository::store()
direct Campaign::save() ┘          └─> Campaign::save()
                                      └─> host CampaignObserver::created()
                                           └─> CaptureCampaignSender
                                                ├─ normalize a copy
                                                ├─ atomic no-update sender insert
                                                ├─ duplicate: silent no-op
                                                └─ failure: log + non-blocking warning

stored campaign from_* ─> CreateMessages ─> independent message from_* snapshot
sender edit/delete ──────────────────────> no historical-row mutation
```

The existing `senders` migration already supplies the workspace foreign key and composite unique index. Do not add a sender foreign key to package campaign/message tables, a new campaign route, a new submitted `sender_id`, a package repository wrapper, or any edit below `vendor/mettle/sendportal-core`.

## File Classification

| New/Modified File | Role | Data Flow | Closest Analog | Match Quality |
|---|---|---|---|---|
| `app/Observers/CampaignObserver.php` | observer/integration | event-driven, after-commit side effect | `vendor/mettle/sendportal-core/src/Models/Message.php` lifecycle hooks plus host provider boot | partial; no host observer exists |
| `app/Services/Senders/CaptureCampaignSender.php` | focused service | event-driven + CRUD/transform | `app/Services/Workspaces/CreateWorkspace.php` plus sender relation create | role match; atomic capture is new |
| `app/Providers/AppServiceProvider.php` | provider/bootstrap seam | event-driven registration | existing `boot()` package seams in the same file | exact self-pattern |
| `tests/Feature/Workspaces/CampaignSenderAutoCaptureTest.php` | feature/integration test | event-driven + request-response + CRUD | `tests/Feature/Workspaces/CampaignSenderSelectionTest.php` and `SenderControllerTest.php` | role/data-flow match |
| `tests/Feature/Workspaces/CampaignSenderIntegrityTest.php` | feature/integration test | CRUD + transform/snapshot | `tests/Feature/Workspaces/SenderControllerTest.php` plus package `CreateMessages` | role match; package snapshot contract is the key reference |

`app/Providers/EventServiceProvider.php`, routes, migrations, campaign views, and vendor files are reference-only for this phase. The existing campaign view override must remain unchanged except for any explicitly phase-scoped regression assertion.

## Pattern Assignments

### `app/Observers/CampaignObserver.php` (observer, event-driven/after-commit)

**Analogs:**

- `vendor/mettle/sendportal-core/src/Models/Message.php:87-103` is the closest Eloquent lifecycle example, but is vendor code and must not be edited.
- `app/Providers/AppServiceProvider.php:31-37,73-87` is the host bootstrap/registration seam.
- There is no existing host `app/Observers/` class.

**Eloquent lifecycle pattern** (`vendor/mettle/sendportal-core/src/Models/Message.php:87-103`):

```php
protected static function boot(): void
{
    parent::boot();

    static::creating(function ($model) {
        $model->hash = $model->hash ?: Uuid::uuid4()->toString();
    });

    static::deleting(function (self $message) {
        $message->failures()->delete();
    });
}
```

The new host observer should use the same model-event concept without adding a `boot()` method to the vendor `Campaign`. Register an external observer from the host provider, implement only `created`, and implement Laravel's `ShouldHandleEventsAfterCommit` contract so a campaign transaction is committed before the best-effort library side effect runs. Do not handle `updated`, `saved`, `retrieved`, or delete events.

**Registration/import style** (`app/Providers/AppServiceProvider.php:7-20,31-37`):

```php
use App\Livewire\Setup;
use App\Mail\ThrottledSesAdapter;
use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Support\ServiceProvider;
use Sendportal\Base\Facades\Sendportal;
use Sendportal\Base\Factories\MailAdapterFactory;

public function boot(): void
{
    Paginator::useBootstrap();

    // Route SES sends through the host's coordinated, rate-limited adapter.
    MailAdapterFactory::$adapterMap[EmailServiceType::SES] = ThrottledSesAdapter::class;
```

Keep `declare(strict_types=1)`, PSR-4 imports, and the existing `boot(): void` shape. The observer should receive the concrete vendor `Sendportal\Base\Models\Campaign` and the host `CaptureCampaignSender` through constructor injection. A representative registration belongs alongside the existing host package seams:

```php
Campaign::observe(CampaignObserver::class);
```

`EventServiceProvider` is not the selected seam: its current mapping is for framework events (`app/Providers/EventServiceProvider.php:18-31`), while direct Eloquent observer registration makes the production model boundary explicit and covers web, API, and future direct `Campaign::save()` calls.

**Core coverage evidence:** the package web store calls the tenant repository at `vendor/mettle/sendportal-core/src/Http/Controllers/Campaigns/CampaignsController.php:105-113`; the API store does the same at `vendor/mettle/sendportal-core/src/Http/Controllers/Api/CampaignsController.php:36-50`; and `BaseTenantRepository` fills the model, assigns the trusted tenant, and calls `save()` at `vendor/mettle/sendportal-core/src/Repositories/BaseTenantRepository.php:236-279`.

**Failure boundary:** catch `Throwable` only at this observer's recovery boundary. The campaign must stay committed when capture fails. Delegate the actual insert to the service, log the failure with campaign/workspace IDs, and emit a fixed warning signal where a session exists; do not rethrow capture errors or put request validation in the observer. Duplicate `0`-row outcomes are expected and must not be warned about.

**Tenancy guard:** do not resolve the workspace from `auth()` or a submitted request field inside the observer. The package repository assigns `$workspaceId` to the campaign before `save()` (`vendor/mettle/sendportal-core/src/Repositories/BaseTenantRepository.php:271-279`), so the observer must pass the campaign's trusted `workspace_id` to the service.

### `app/Services/Senders/CaptureCampaignSender.php` (service, event-driven + CRUD/transform)

**Analog:** `app/Services/Workspaces/CreateWorkspace.php:13-45` for the host's focused service shape; secondary sender-specific analogs are `app/Http/Controllers/Workspaces/SendersController.php:31-38` and `:66-69`.

**Service structure and dependency style** (`app/Services/Workspaces/CreateWorkspace.php:13-45`):

```php
class CreateWorkspace
{
    public function __construct(WorkspacesRepository $workspacesRepo, AddWorkspaceMember $addWorkspaceMember)
    {
        $this->workspaces = $workspacesRepo;
        $this->addWorkspaceMember = $addWorkspaceMember;
    }

    /**
     * @throws Exception
     */
    public function handle(User $user, string $workspaceName, ?string $role = null): Workspace
    {
        return DB::transaction(function () use ($user, $workspaceName, $role) {
            // ... focused domain mutation ...
        });
    }
}
```

Use one PSR-4 class under `app/Services/Senders/`, strict types, constructor injection, a public `handle(Campaign $campaign)` entry point, and native return types. Unlike `CreateWorkspace`, this operation is intentionally best-effort and after-commit: it must not wrap campaign creation in a transaction or allow a sender failure to roll the campaign back.

**Sender tenancy/creation pattern** (`app/Http/Controllers/Workspaces/SendersController.php:31-38,66-69`):

```php
public function store(SenderStoreRequest $request): RedirectResponse
{
    $workspace = $request->user()->currentWorkspace();
    $workspace->senders()->create($request->validated());

    return redirect()
        ->route('senders.index')
        ->with('success', __('Sender saved successfully.'));
}

private function senderFor(Request $request, int $senderId): Sender
{
    return $request->user()->currentWorkspace()->senders()->findOrFail($senderId);
}
```

The relation-first pattern is correct for an authenticated sender CRUD request. For capture, use the campaign's trusted workspace ID instead of current-user/request state so direct and API-created campaigns cannot cross tenant boundaries.

**Normalization pattern** (`app/Models/Sender.php:29-41`):

```php
public static function normalizeInput(array $input): array
{
    return [
        'label' => trim((string) ($input['label'] ?? '')),
        'from_name' => trim((string) ($input['from_name'] ?? '')),
        'from_email' => strtolower(trim((string) ($input['from_email'] ?? ''))),
    ];
}
```

Call `Sender::normalizeInput()` on a separate payload containing the campaign's `from_name`/`from_email` and the selected label. Never assign the normalized values back to the campaign; D-08 requires submitted campaign values to remain authoritative.

**Deduplication/storage contract** (`database/migrations/2026_08_05_000000_create_senders_table.php:12-22`):

```php
$table->string('label');
$table->string('from_name');
$table->string('from_email');
$table->timestamps();

$table->foreign('workspace_id')->references('id')->on('workspaces');
$table->unique(['workspace_id', 'from_name', 'from_email']);
```

There is no exact host `insertOrIgnore` analog. The planned core operation should be a parameterized `DB::table('senders')->insertOrIgnore(...)` containing `workspace_id`, normalized `label`, `from_name`, `from_email`, and explicit `created_at`/`updated_at`. Treat affected-row `0` as the expected duplicate race/no-op; never use check-then-create or an updating `upsert`, because either can race or overwrite a user-managed label. Preserve the 255-character contracts from `vendor/mettle/sendportal-core/src/Http/Requests/CampaignStoreRequest.php:22-39` and diagnose an absent normalized key because `insertOrIgnore` can suppress more than duplicate errors on some drivers.

**Label decision gap:** no current host code defines an auto-capture label. The research recommendation is a bounded trimmed campaign name with a deterministic fallback, but this remains `[ASSUMED]` and must be locked by the planner without adding provenance fields.

### `app/Providers/AppServiceProvider.php` (provider, event-driven bootstrap)

**Analog:** the existing file itself, especially `app/Providers/AppServiceProvider.php:31-87`.

**Existing host boot seams** (`app/Providers/AppServiceProvider.php:39-71,73-87`):

```php
Sendportal::setCurrentWorkspaceIdResolver(
    static function () {
        /** @var User $user */
        $user = auth()->user();
        $request = request();
        $workspaceId = null;

        if ($user && $user->currentWorkspaceId()) {
            $workspaceId = $user->currentWorkspaceId();
        } elseif ($request && (($apiToken = $request->bearerToken()) || ($apiToken = $request->get('api_token')))) {
            $workspaceId = ApiToken::resolveWorkspaceId($apiToken);
        }

        if (! $workspaceId) {
            throw new RuntimeException('Current Workspace ID Resolver must not return a null value.');
        }

        return $workspaceId;
    }
);

View::composer(
    ['sendportal::campaigns.create', 'sendportal::campaigns.edit'],
    static function (ViewContract $view): void {
        // ... current-workspace sender collection ...
    }
);

Livewire::component('setup', Setup::class);
```

Add the concrete `Campaign` and `CampaignObserver` imports and register the observer once in `boot()`, near the other host/package integration seams. Do not move the current workspace resolver or campaign view composer, and do not bind a host replacement for `CampaignTenantRepositoryInterface`; the vendor provider already binds that interface by database driver at `vendor/mettle/sendportal-core/src/Providers/SendportalAppServiceProvider.php:34-41`.

### `tests/Feature/Workspaces/CampaignSenderAutoCaptureTest.php` (feature/integration, event-driven + request-response + CRUD)

**Analogs:**

- `tests/Feature/Workspaces/CampaignSenderSelectionTest.php:1-60,202-240` for the phase's workspace feature-test namespace, `RefreshDatabase`, authenticated workspace fixture, and package-shaped campaign rendering.
- `tests/Feature/Workspaces/SenderControllerTest.php:20-51,210-255` for sender values, normalization, and database assertions.
- `tests/Feature/Auth/WorkspaceApiTokenTest.php:23-64` for API-token request setup and unauthorized/authorized route assertions.

**Test setup/fixture pattern** (`tests/Feature/Workspaces/CampaignSenderSelectionTest.php:1-14` and `tests/TestCase.php:17-25`):

```php
declare(strict_types=1);

namespace Tests\Feature\Workspaces;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignSenderAutoCaptureTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function ...(): void
    {
        $user = $this->createUserWithWorkspace();
        $this->actingAs($user);
        // create a package-shaped Campaign and assert sender persistence
    }
}
```

`Tests\TestCase::setUp()` disables Mix, enables exception handling, and runs migrations (`tests/TestCase.php:17-25`). Reuse `createUserWithWorkspace()` / `createUserAndWorkspace()` from `tests/TestSupportTrait.php:19-52`; `WorkspaceFactory::configure()` attaches the owner (`database/factories/WorkspaceFactory.php:31-35`). Do not add a sender factory merely for these tests.

**Package campaign fixtures:** the installed package factory derives `workspace_id` from `Sendportal::currentWorkspaceId()` and creates an email service (`vendor/mettle/sendportal-core/database/factories/CampaignFactory.php:19-30`). Its `draft`, `queued`, `sending`, and `sent` states are at `:51-85`; use those states or explicit status values to prove capture is not status-gated. If a factory-created campaign is used, establish the active workspace before invoking it and override `from_name`/`from_email` explicitly.

**Required assertions/coverage:**

- Direct `Campaign::create()`/`save()` captures once; a later `update()` does not create or recapture a sender.
- The package web route and API route each capture through the same observer. The source paths are `routes/web.php:126-130`, `routes/api.php:9-15`, and the package controller stores at the lines cited above; do not test only the rendered form.
- Draft, scheduled/queued, sending, and immediate-send campaign modes all create the library record.
- Trimmed name/email input produces one normalized `(workspace_id, from_name, from_email)` pair; the existing label remains unchanged on duplicate; campaign row values remain exactly the submitted values.
- A deleted sender can be recreated by a later campaign with the same normalized pair.
- A forced capture failure leaves the campaign committed, emits the documented non-blocking warning, and writes a `Log::warning()` context entry. Duplicate races must not produce that warning.
- MySQL and PostgreSQL concurrency coverage must assert two successful campaigns and exactly one sender; local SQLite is suitable for the focused suite, while `.github/workflows/ci.yml:17-77` supplies the supported-driver CI services.

**Existing database assertion style** (`tests/Feature/Workspaces/SenderControllerTest.php:31-51`):

```php
$response = $this->post(route('senders.store'), [
    'label' => '  Product Updates  ',
    'from_name' => 'Acme Mail',
    'from_email' => '  NEWS@ACME.TEST  ',
]);

$response->assertRedirect(route('senders.index'));

$this->assertDatabaseHas('senders', [
    'workspace_id' => $user->currentWorkspace()->id,
    'label' => 'Product Updates',
    'from_name' => 'Acme Mail',
    'from_email' => 'news@acme.test',
]);
```

### `tests/Feature/Workspaces/CampaignSenderIntegrityTest.php` (feature/integration, CRUD + snapshot transform)

**Analog:** `tests/Feature/Workspaces/SenderControllerTest.php:96-165` for edit/delete behavior and `assertDatabaseHas`/`assertDatabaseMissing`; use the package pipeline as the authoritative data-flow analog.

**Existing sender CRUD test pattern** (`tests/Feature/Workspaces/SenderControllerTest.php:96-128,143-169`):

```php
$sender = $workspace->senders()->create([
    'label' => 'Original Sender',
    'from_name' => 'Original Name',
    'from_email' => 'original@example.test',
]);

$this->actingAs($member);
$this->put("/senders/{$sender->id}", [
    'label' => '  Updated Sender  ',
    'from_name' => 'Updated Name',
    'from_email' => '  UPDATED@EXAMPLE.TEST  ',
])->assertRedirect(route('senders.index'));

$this->delete("/senders/{$sender->id}")
    ->assertRedirect(route('senders.index'));

$this->assertDatabaseMissing('senders', ['id' => $sender->id]);
```

Use this route-level CRUD path, then assert the campaign and every existing message's `from_name`/`from_email` still contain the original values. The absence of a sender foreign key is intentional: `senders` only references `workspaces` (`database/migrations/2026_08_05_000000_create_senders_table.php:20-22`).

**Snapshot contract** (`vendor/mettle/sendportal-core/src/Pipelines/Campaigns/CreateMessages.php:159-187,198-217`):

```php
$attributes = [
    'workspace_id' => $campaign->workspace_id,
    'source_type' => Campaign::class,
    'source_id' => $campaign->id,
    'subject' => $campaign->subject,
    'from_name' => $campaign->from_name,
    'from_email' => $campaign->from_email,
];

// Draft path also reads the campaign's stored values.
Message::firstOrCreate(
    [/* campaign/subscriber identity */],
    [
        'from_name' => $campaign->from_name,
        'from_email' => $campaign->from_email,
    ]
);
```

The package schema confirms independent text columns: campaign `from_name`/`from_email` are at `vendor/mettle/sendportal-core/database/migrations/2017_04_28_223915_create_campaigns_table.php:20-39`, and message snapshot columns are at `vendor/mettle/sendportal-core/database/migrations/2019_07_10_194325_create_messages_table.php:16-39`. Do not introduce a live `Sender` relation into either model.

**Fixture guidance:** `CampaignFactory` exposes `draft`, `queued`, `sending`, and `sent` states (`vendor/mettle/sendportal-core/database/factories/CampaignFactory.php:51-85`); `MessageFactory` exposes `pending` and `dispatched` (`vendor/mettle/sendportal-core/database/factories/MessageFactory.php:18-61`). For a message tied to a particular campaign, override `source_id`, `workspace_id`, subscriber, and the sender snapshot explicitly instead of relying on the factory's nested campaign. Cover existing draft/queued/sending/sent message rows, sender edit, sender delete, later message generation, and an explicit direct campaign `from_*` edit remaining authoritative.

## Shared Patterns

### Host/package creation boundary

**Sources:** `routes/web.php:126-130`, `routes/api.php:9-15`, `vendor/mettle/sendportal-core/src/Http/Controllers/Campaigns/CampaignsController.php:105-113`, `vendor/mettle/sendportal-core/src/Http/Controllers/Api/CampaignsController.php:36-50`, and `vendor/mettle/sendportal-core/src/Repositories/BaseTenantRepository.php:236-279`.

```php
// Web store
$campaign = $this->campaigns->store($workspaceId, $this->handleCheckboxes($request->validated()));

// API store
$campaign = $this->campaigns->store($workspaceId, $data);

// Shared tenant repository save
$instance->fill($data);
$instance->{$this->getTenantKey()} = $workspaceId;
$instance->save();
```

The host observer at the vendor `Campaign` model boundary is the common coverage point. Do not put capture in the web controller, API controller, route middleware, or only the campaign form; those would miss direct Eloquent creation.

### Trusted workspace resolution

**Sources:** `app/Models/Workspace.php:106-112`, `app/Providers/AppServiceProvider.php:39-58`, `app/Traits/HasWorkspaces.php:89-108`, and `app/Http/Controllers/Workspaces/SendersController.php:31-34`.

```php
public function senders(): HasMany
{
    return $this->hasMany(Sender::class);
}

$workspace = $request->user()->currentWorkspace();
$workspace->senders()->create($request->validated());
```

Request-facing sender CRUD uses the active user's workspace relation. Lifecycle capture must instead use the campaign's persisted `workspace_id`, which the tenant repository sets before `save()`; never accept a workspace or sender ID from campaign input.

### Normalization and uniqueness

**Sources:** `app/Models/Sender.php:29-41` and `database/migrations/2026_08_05_000000_create_senders_table.php:12-22`.

```php
$normalized = Sender::normalizeInput([
    'label' => $label,
    'from_name' => $campaign->from_name,
    'from_email' => $campaign->from_email,
]);

$table->unique(['workspace_id', 'from_name', 'from_email']);
```

The label is metadata, not part of the duplicate key. Use an atomic no-update insert and let the database unique index arbitrate concurrent requests. Do not update the existing label and do not rewrite campaign fields to the existing sender's canonical values.

### Recovery logging and warning transport

**Sources:** `app/Livewire/Setup.php:81-107`, `app/Mail/ThrottledSesAdapter.php:95-109`, `vendor/mettle/sendportal-core/resources/views/layouts/partials/warning.blade.php:1-9`, and `app/Http/Kernel.php:31-46`.

```php
try {
    $completed = $handler->run($data);
    // ... mark the setup step complete ...
} catch (Exception $exception) {
    session()->flash('error', $exception->getMessage());
}
```

```php
Log::warning(sprintf(
    'sendportal-throttle.max_total_wait_seconds (%d) must be < %ds worker timeout; clamping to %d.',
    $configured,
    self::WORKER_TIMEOUT_SECONDS,
    self::WORKER_TIMEOUT_SECONDS - 1
));
```

Use the project/Laravel `Log` facade with structured campaign/workspace context, and a fixed user-facing warning rather than raw exception/sender content. The package warning partial renders `session('warning')`, but the `web` group is the only group with `StartSession`; `app/Http/Kernel.php:31-46` and `routes/api.php:9-15` show that API requests need a separate host-only warning signal. No existing host API warning/header analog was found; preserve the package JSON contract while choosing/documenting that signal.

### Historical sender snapshots

**Sources:** `vendor/mettle/sendportal-core/src/Models/Campaign.php:66-82`, `vendor/mettle/sendportal-core/src/Models/Message.php:63-103`, and `vendor/mettle/sendportal-core/src/Pipelines/Campaigns/CreateMessages.php:171-217`.

```php
// Campaign and Message are independent vendor models with guarded = [];
protected $table = 'sendportal_campaigns';
protected $guarded = [];

// CreateMessages copies the campaign values into both message paths.
'from_name' => $campaign->from_name,
'from_email' => $campaign->from_email,
```

Sender CRUD therefore has no legitimate callback into campaign/message rows. Existing messages remain snapshots; later messages must read the campaign row, and direct campaign edits may intentionally change what later messages receive.

### Feature-test and boundary style

**Sources:** `tests/Feature/Workspaces/CampaignSenderSelectionTest.php:22-60,202-240`, `tests/Feature/Workspaces/SenderControllerTest.php:295-316`, and `tests/Feature/Ses/SesDoubleSendTest.php:118-132`.

The campaign selection test renders named package views with a lightweight package-shaped fixture and asserts exact HTML contracts. Keep that regression suite green; auto-capture is backend-only and must not add a submitted `sender_id` or change the picker behavior. The Phase 5 negative-boundary test is the precedent for asserting deferred campaign seams/classes, while the SES suite supplies the structural vendor/manifest check:

```php
self::assertSame('', $this->gitPorcelain('vendor/mettle/sendportal-core'), 'No tracked vendor/core file may change.');

private function gitPorcelain(string $paths): string
{
    return trim((string) shell_exec('git status --porcelain -- ' . $paths . ' 2>/dev/null'));
}
```

Retain the host view override boundary established by `vendor/mettle/sendportal-core/src/SendportalBaseServiceProvider.php:28-30,50-53`; the package publishes/loads its view namespace, and host overrides under `resources/views/vendor/sendportal` are the permitted seam.

### Style and error contracts

Follow `AGENTS.md:78-146,232-281`: strict types, direct PSR-4 imports, constructor injection, focused `handle()` services, transactions only for required multi-write operations, catches only at explicit recovery boundaries, and Laravel logging instead of direct output. Do not add a new package, config alias, static/global request state, or vendor patch.

## No Analog Found

| File/Concern | Closest partial analog | Gap the planner must resolve |
|---|---|---|
| `app/Observers/CampaignObserver.php` | Vendor `Message::boot()` lifecycle hooks (`vendor/mettle/sendportal-core/src/Models/Message.php:87-103`) | No host observer directory or external observer registration exists; after-commit observer behavior is new. |
| `app/Services/Senders/CaptureCampaignSender.php` | `CreateWorkspace` service structure and `SendersController` relation create | No existing host `insertOrIgnore`, duplicate-race handling, or best-effort post-commit capture service exists. |
| API capture-failure warning | `Setup` session flash and package web warning partial | API has no session middleware and no existing host response-header/equivalent warning contract. |
| Cross-process same-pair proof | CI database services (`.github/workflows/ci.yml:17-77`) | MySQL/PostgreSQL are unavailable locally; the concurrency proof belongs in supported-driver CI. |

Use `07-RESEARCH.md` for the new observer/service/API-warning decisions rather than copying an unrelated controller or adding a speculative route/middleware without a plan-level decision.

## Metadata

**Analog search scope:** host `app/`, `database/`, `resources/views/`, `routes/`, and `tests/`; installed `vendor/mettle/sendportal-core` campaign controllers, repositories, models, migrations, factories, pipeline, warning view, and view provider; installed Laravel Eloquent event source; phase 6 pattern map and phase 7 context/research/validation.  
**Pattern extraction date:** 2026-08-06  
**Vendor status:** read-only reference; no vendor/application source edits made.
