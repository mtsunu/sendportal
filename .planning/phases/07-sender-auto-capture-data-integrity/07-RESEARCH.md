# Phase 07: Sender Auto-Capture & Data Integrity - Research

**Researched:** 2026-08-06  
**Domain:** Laravel 11 Eloquent lifecycle, workspace sender deduplication, campaign/message snapshots  
**Confidence:** HIGH for local architecture; MEDIUM for framework guidance

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions
#### Auto-capture coverage and failure handling
- **D-01:** Auto-capture applies to every newly created production `Campaign` record, regardless of whether it was created through the browser campaign form, the package API, or another Eloquent creation path. Capture is creation-only; campaign updates do not auto-capture.
- **D-02:** Auto-capture applies to all campaign modes, including campaigns saved as drafts, scheduled campaigns, and immediate-send campaigns.
- **D-03:** Sender capture is best-effort. A failure to persist a newly captured sender must not make the campaign creation fail or roll back.
- **D-04:** When best-effort capture fails, the campaign remains successful but the application must provide a non-blocking warning and log the failure cause.
#### Duplicate behavior
- **D-05:** A normalized pair that already exists is a no-op for auto-capture. The existing sender label and record remain untouched; auto-capture never overwrites user-managed label metadata.
- **D-06:** Concurrent campaigns with the same normalized pair must converge to one sender while both campaign creations succeed. The losing duplicate race is an expected no-op, not a user-facing warning.
- **D-07:** If a sender is deleted, a later campaign using the same normalized pair may create a new sender again. No deleted-pair history is required.
- **D-08:** Deduplication is a sender-library side effect only. It must not rewrite the campaign's submitted `from_name` or `from_email` values to match the existing sender's canonical values.
#### Historical campaign and message integrity
- **D-09:** Editing or deleting a saved sender must not change the stored sender values on the campaign row or on any already-created message snapshot, including draft, queued, sending, and sent messages.
- **D-10:** If messages for an existing campaign are generated after the saved sender is edited or deleted, those messages must use the campaign's original stored From Name/From Email values, not current sender-library data.
- **D-11:** Direct edits to a campaign's own From Name/From Email fields remain allowed and authoritative. The isolation guarantee applies specifically to saved-sender CRUD changes, not explicit campaign edits.
- **D-12:** A sender with historical campaign or message usage may still be deleted through the normal existing delete flow; historical records must remain readable and operational.
#### Carried forward from earlier phases
- Sender normalization remains centralized in `App\Models\Sender::normalizeInput`: trim all fields and lowercase From Email.
- Sender uniqueness remains workspace-scoped on the normalized `(workspace_id, from_name, from_email)` pair; label is not part of the duplicate key.
- Senders remain a shared library for active workspace members and every sender record operation remains current-workspace scoped.
- The campaign picker remains unnamed and convenience-only; package `from_name` and `from_email` fields remain the campaign submission contract.
- The host/package boundary remains hard: use host seams and published/overridden views, with zero edits under `vendor/mettle/sendportal-core`.
### the agent's Discretion
- Choose the auto-captured sender label convention. Area 2 was intentionally not discussed; research and planning may select a sensible label derived from the campaign data without adding a new provenance field.
- Choose the exact host integration seam (Campaign model observer/listener versus a host-side campaign repository/wrapper) while covering all required production creation paths and preserving the zero-vendor-edit boundary. The roadmap explicitly leaves this as a plan-time decision.
- Choose the concrete transaction, unique-conflict, warning, logging, and test-fixture mechanics needed to implement the decisions above across the supported database drivers. These are implementation details, not additional product scope.
### Deferred Ideas (OUT OF SCOPE)
None — discussion stayed within phase scope. The auto-captured label and exact host implementation seam remain planning discretion, not deferred product capabilities.

Source: [VERIFIED: .planning/phases/07-sender-auto-capture-data-integrity/07-CONTEXT.md:16-44,140-143]
</user_constraints>

<phase_requirements>
## Phase Requirements
| ID | Description | Research support |
|---|---|---|
| SENDER-07 | New campaign sender pairs are stored once per workspace. [VERIFIED: .planning/REQUIREMENTS.md:18-22] | Observer, normalization, unique index, atomic insert, concurrency tests. |
| SENDER-09 | Sender CRUD does not retroactively alter campaign/message sender values. [VERIFIED: .planning/REQUIREMENTS.md:23-26] | Independent columns and `CreateMessages` snapshot contract. |
</phase_requirements>

## Summary

Use a host observer on the vendor `Campaign` model, registered from `AppServiceProvider::boot()`. Both package web/API stores call the tenant repository, which fills the vendor model and calls `save()`; the model lifecycle also covers future direct Eloquent creation paths. [VERIFIED: vendor/mettle/sendportal-core/src/Http/Controllers/Campaigns/CampaignsController.php:108-113; vendor/mettle/sendportal-core/src/Http/Controllers/Api/CampaignsController.php:39-50; vendor/mettle/sendportal-core/src/Repositories/BaseTenantRepository.php:228-279] [CITED: https://laravel.com/docs/11.x/eloquent#observers]

Recommended seam: `CampaignObserver` with `created` only, implementing `ShouldHandleEventsAfterCommit`, and delegating to a host `CaptureCampaignSender` service. Normalize a copy of campaign values, then use an atomic no-update insert against the existing composite unique index; catch/log failures so sender capture never rolls back campaign creation. [VERIFIED: app/Models/Sender.php:29-41; database/migrations/2026_08_05_000000_create_senders_table.php:12-22; vendor/laravel/framework/src/Illuminate/Database/Eloquent/Model.php:1318-1347] [CITED: https://laravel.com/docs/11.x/eloquent#observers]

Do not add a sender foreign key or edit vendor code. Campaign and message sender fields are already independent, and `CreateMessages` copies campaign values into message rows. [VERIFIED: vendor/mettle/sendportal-core/database/migrations/2017_04_28_223915_create_campaigns_table.php:20-39; vendor/mettle/sendportal-core/database/migrations/2019_07_10_194325_create_messages_table.php:16-39; vendor/mettle/sendportal-core/src/Pipelines/Campaigns/CreateMessages.php:171-217]

## Architectural Responsibility Map
| Capability | Primary tier | Secondary tier | Rationale |
|---|---|---|---|
| Creation interception | API / Backend | Frontend Server | Shared Eloquent `Campaign` lifecycle covers web, API, and direct saves. [VERIFIED: routes/web.php:126-130; routes/api.php:9-15] |
| Deduplication | Database / Storage | API / Backend | Normalized application key plus database unique index arbitrates races. [VERIFIED: app/Models/Sender.php:35-41; database/migrations/2026_08_05_000000_create_senders_table.php:20-22] |
| Historical snapshots | Database / Storage | API / Backend | Package stores and copies sender text fields; no live sender relation. [VERIFIED: vendor/mettle/sendportal-core/src/Pipelines/Campaigns/CreateMessages.php:173-184,202-216] |
| Failure visibility | Frontend Server / API | Backend logging | Existing web warning partial handles flash; API needs a host-only response signal. [VERIFIED: vendor/mettle/sendportal-core/resources/views/layouts/partials/warning.blade.php:1-9; app/Http/Kernel.php:31-46] |

## Standard Stack
| Component | Version | Use | Provenance |
|---|---|---|---|
| PHP | 8.4.23 local | Runtime target | [VERIFIED: AGENTS.md:5-16; environment probe] |
| Laravel / Eloquent | `^11.0`, installed `v11.55.0` | Events, DB, sessions, logging | [VERIFIED: composer.json:6-14; local vendor install probe] |
| SendPortal Core | `^3.0`, installed `v3.0.2` | Campaign/message models and pipeline | [VERIFIED: composer.json:6-14; local vendor install probe] |
| PHPUnit | `^10.5`, installed `10.5.64` | Feature/unit tests | [VERIFIED: composer.json:16-22; phpunit.xml.dist:2-20; local version probe] |

No new package is recommended; package-legitimacy audit and installation are not applicable. Do not modify `composer.json`, `composer.lock`, or `vendor/`.

## Architecture Patterns

### System Architecture Diagram
```text
web store ─┐
API store ─┼─> vendor Campaign::save() -> host created observer (after commit)
direct save┘                                  -> normalize copy -> atomic no-update insert
                                               ├─ new sender
                                               ├─ unique conflict: silent no-op
                                               └─ failure: log + non-blocking warning
stored campaign fields -> CreateMessages -> independent message sender snapshot
sender edit/delete ---------------------------------> no historical-row mutation
```

### Recommended Project Structure
New host symbols/paths are recommendations: [ASSUMED]
```text
app/Observers/CampaignObserver.php
app/Services/Senders/CaptureCampaignSender.php
app/Providers/AppServiceProvider.php
tests/Feature/Workspaces/CampaignSenderAutoCaptureTest.php
tests/Feature/Workspaces/CampaignSenderIntegrityTest.php
```

### Pattern 1: Observer and after-commit boundary
Register `Campaign::observe(CampaignObserver::class)` in the existing provider; implement only `created`. Laravel documents provider registration, distinct `created`/`updated` methods, and `ShouldHandleEventsAfterCommit`. [CITED: https://laravel.com/docs/11.x/eloquent#observers] The vendor model reaches Eloquent through `BaseModel`. [VERIFIED: vendor/mettle/sendportal-core/src/Models/BaseModel.php:3-8]

### Pattern 2: Normalized atomic insert
`Sender::normalizeInput()` returns trimmed label/name and lowercased email. [VERIFIED: app/Models/Sender.php:29-41] Insert those values plus `workspace_id`, `created_at`, and `updated_at` with `insertOrIgnore`; the exact unique key is `"$table->unique(['workspace_id', 'from_name', 'from_email']);"`. [VERIFIED: database/migrations/2026_08_05_000000_create_senders_table.php:12-22] Laravel warns that `insertOrIgnore` can suppress non-duplicate engine errors, so bound inputs and an absent-key diagnostic are required. [CITED: https://laravel.com/docs/11.x/queries#insert-statements]

### Pattern 3: Independent snapshots
`CreateMessages` uses `'from_name' => $campaign->from_name` and `'from_email' => $campaign->from_email` for immediate and draft messages. [VERIFIED: vendor/mettle/sendportal-core/src/Pipelines/Campaigns/CreateMessages.php:171-184,198-217]

### Anti-Patterns
- Controller-only capture misses API/direct creation; `saved`/`updated` capture violates creation-only scope. [VERIFIED: vendor/mettle/sendportal-core/src/Http/Controllers/Campaigns/CampaignsController.php:108-113; vendor/mettle/sendportal-core/src/Http/Controllers/Api/CampaignsController.php:39-50]
- Check-then-create or updating `upsert` can race or overwrite labels; use the unique index and no-update conflict behavior. [CITED: https://laravel.com/docs/11.x/queries#upserts]
- Mutating campaign fields or resolving messages through `Sender` breaks snapshot integrity. [VERIFIED: vendor/mettle/sendportal-core/src/Models/Campaign.php:17-35; vendor/mettle/sendportal-core/src/Pipelines/Campaigns/CreateMessages.php:173-184]

## Don't Hand-Roll
| Problem | Use instead | Why |
|---|---|---|
| Lifecycle coverage | Eloquent observer | One shared model boundary. [CITED: https://laravel.com/docs/11.x/eloquent#observers] |
| Concurrent deduplication | Composite unique index + atomic no-update insert | Cross-driver database arbitration. [VERIFIED: database/migrations/2026_08_05_000000_create_senders_table.php:20-22] |
| Historical data | Existing copied campaign/message fields | Safe sender deletion and later message generation. [VERIFIED: vendor/mettle/sendportal-core/src/Pipelines/Campaigns/CreateMessages.php:173-184,202-216] |
| Logging | `Log::warning()` with IDs/context | Existing project/Laravel channel contract. [VERIFIED: AGENTS.md:275-281; config/logging.php:20-47] [CITED: https://laravel.com/docs/11.x/logging] |

## Common Pitfalls
- Capture only `created`; never recapture on update. [CITED: https://laravel.com/docs/11.x/eloquent#observers]
- Treat duplicate affected-row `0` as an expected silent no-op, not a warning. [VERIFIED: .planning/phases/07-sender-auto-capture-data-integrity/07-CONTEXT.md:22-26]
- `insertOrIgnore` may hide MySQL non-duplicate errors; preserve the 255-character contracts and diagnose an absent normalized key. [VERIFIED: vendor/mettle/sendportal-core/src/Http/Requests/CampaignStoreRequest.php:22-39; database/migrations/2026_08_05_000000_create_senders_table.php:15-17] [CITED: https://laravel.com/docs/11.x/queries#insert-statements]
- Query-builder inserts need explicit timestamps. [VERIFIED: database/migrations/2026_08_05_000000_create_senders_table.php:12-18]
- API has no `web` session middleware; flash the existing package `warning` for web and define a host-only API response warning. [VERIFIED: app/Http/Kernel.php:31-46; routes/api.php:9-15; vendor/mettle/sendportal-core/resources/views/layouts/partials/warning.blade.php:1-9]
- Never edit `vendor/mettle/sendportal-core`. [VERIFIED: AGENTS.md:259-266]

## Code Examples
New observer/service symbols and warning copy are `[ASSUMED]`; verified model field names are quoted beside the examples. [VERIFIED: vendor/mettle/sendportal-core/src/Models/Campaign.php:17-35,66-82]
```php
// AppServiceProvider::boot()
Campaign::observe(CampaignObserver::class);

// CampaignObserver::created(Campaign $campaign)
try {
    $this->capture->handle($campaign); // normalize a copy; do not mutate campaign fields
} catch (Throwable $exception) {
    Log::warning('Automatic sender capture failed', [
        'campaign_id' => $campaign->id,
        'workspace_id' => $campaign->workspace_id,
        'exception' => $exception,
    ]);
    if (request()->hasSession()) {
        session()->flash('warning', 'The campaign was saved, but its sender could not be saved automatically.');
    }
}
```
The capture payload should use `Sender::normalizeInput()` and the verified keys `'workspace_id'`, `'label'`, `'from_name'`, `'from_email'`, `'created_at'`, and `'updated_at'`, then `DB::table('senders')->insertOrIgnore(...)`. [VERIFIED: app/Models/Sender.php:35-41; database/migrations/2026_08_05_000000_create_senders_table.php:12-22] [CITED: https://laravel.com/docs/11.x/queries#insert-statements]

## Project Constraints (from AGENTS.md)
- PHP 8.4 target; preserve Laravel 11/Core behavior; retain Composer platform/advisory protection. [VERIFIED: AGENTS.md:5-16]
- Host code/views only; zero vendor edits or duplicate package routes/models. [VERIFIED: AGENTS.md:259-266]
- Use PSR-4 classes, strict types, PHP-CS-Fixer, constructor injection, thin controllers, and focused `handle()` services. [VERIFIED: AGENTS.md:78-116,232-245]
- Catch only at recovery boundaries; use framework validation, transactions for real multi-write operations, and Laravel logging. [VERIFIED: AGENTS.md:117-146,275-281]

## Validation Architecture
| Property | Value |
|---|---|
| Framework/config | PHPUnit 10.5.64; `phpunit.xml.dist`; `Tests\TestCase` migrates and uses `RefreshDatabase`. [VERIFIED: phpunit.xml.dist:2-20; tests/TestCase.php:9-25] |
| Focused command | `DB_CONNECTION=sqlite DB_DATABASE=':memory:' vendor/bin/phpunit tests/Feature/Workspaces/CampaignSenderAutoCaptureTest.php tests/Feature/Workspaces/CampaignSenderIntegrityTest.php` |
| Full command | `vendor/bin/phpunit` |

### Concrete Test Matrix
| Behavior | Test/driver | Assertion |
|---|---|---|
| Direct Eloquent + update exclusion | Feature/SQLite | create captures one; update captures none. |
| Web and API stores | Feature/SQLite | both paths capture and preserve campaign From values. |
| Draft/scheduled/immediate | Feature/SQLite | all modes capture; status never gates capture. |
| Trim/lowercase + existing label | Feature/SQLite | one normalized pair; existing label unchanged. |
| Concurrent same pair | Integration/MySQL + PostgreSQL CI | both campaigns succeed; exactly one sender; no race warning. |
| Capture failure | Unit/feature/SQLite | campaign remains committed; warning log and non-blocking warning signal. |
| Sender edit/delete | Feature/SQLite | campaign and draft/queued/sending/sent message From fields unchanged. |
| Later messages/direct campaign edit | Feature/SQLite | pipeline reads stored campaign fields; explicit campaign edits remain authoritative. |
| Boundary/style | Shell | no vendor diff; `php -l`, fixer dry-run, and `git diff --check` pass. |

CI already runs MySQL and PostgreSQL suites. [VERIFIED: .github/workflows/ci.yml:17-77]

## Security Domain
Security enforcement is enabled at ASVS level 1. [VERIFIED: .planning/config.json:21-31]
| ASVS | Control |
|---|---|
| V2/V3 | Reuse existing authenticated/verified routes; web warning is a fixed flash message, not sender data. [VERIFIED: routes/web.php:126-130; app/Http/Kernel.php:31-40] |
| V4 | Use trusted campaign `workspace_id`; do not accept request sender/workspace IDs. [VERIFIED: vendor/mettle/sendportal-core/src/Repositories/BaseTenantRepository.php:324-337] |
| V5 | Reuse campaign limits and `Sender::normalizeInput`; bound derived label. [VERIFIED: vendor/mettle/sendportal-core/src/Http/Requests/CampaignStoreRequest.php:22-39; app/Models/Sender.php:35-41] |
| V6 | No new cryptographic material. [ASSUMED: phase scope] |

Use parameterized inserts, unique tenancy keys, and ID-based log context; avoid raw untrusted message text in logs. [CITED: https://laravel.com/docs/11.x/logging#contextual-information]

## Assumptions Log
| # | Assumption | Risk |
|---|---|---|
| A1 | New label = bounded trimmed campaign `name`, with deterministic fallback. [ASSUMED] | UX label requires confirmation. |
| A2 | Web uses `warning` flash; API uses a host-only response header/equivalent signal. [ASSUMED] | API warning contract may differ. |
| A3 | After-commit observer, not a queue/retry subsystem, is the failure-isolation boundary. [ASSUMED] | Tests must cover commit/rollback timing. |

## Open Questions
1. Lock A1's label/fallback convention; recommended: campaign name.
2. Lock A2's API warning transport; recommended: documented host response header preserving vendor JSON.

## Environment Availability
| Dependency | Available | Version / fallback |
|---|---|---|
| PHP, Composer, PHPUnit | ✓ | 8.4.23 / 2.10.2 / 10.5.64 |
| PDO MySQL, PostgreSQL, SQLite | ✓ | extensions loaded |
| MySQL/PostgreSQL services | ✗ locally not responding | CI jobs are the concurrency fallback. |

No new dependency blocks implementation; SQLite is sufficient for focused tests, but CI MySQL/PostgreSQL is required for cross-process proof. [VERIFIED: .github/workflows/ci.yml:17-77; environment probe]

## Sources
- Local primary: `app/Models/Sender.php`, `Workspace.php`, `AppServiceProvider.php`; vendor Campaign/Message/repository/controller/migration/pipeline sources. [VERIFIED: cited paths throughout]
- Planning primary: `07-CONTEXT.md`, `REQUIREMENTS.md`, `ROADMAP.md`, `STATE.md`. [VERIFIED: cited paths throughout]
- Official: [Eloquent observers](https://laravel.com/docs/11.x/eloquent#observers), [query inserts](https://laravel.com/docs/11.x/queries#insert-statements), [transactions](https://laravel.com/docs/11.x/database#database-transactions), [logging](https://laravel.com/docs/11.x/logging), [session flash](https://laravel.com/docs/11.x/session#flash-data).

## Metadata
**Confidence:** stack HIGH; architecture HIGH; concurrency MEDIUM until CI driver proof; API warning transport LOW/ASSUMED.  
**Research date:** 2026-08-06  
**Valid until:** 2026-09-05 or dependency change.
