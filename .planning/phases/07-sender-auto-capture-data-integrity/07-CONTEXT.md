# Phase 7: Sender Auto-Capture & Data Integrity - Context

**Gathered:** 2026-08-06
**Status:** Ready for planning

<domain>
## Phase Boundary

Automatically grow the current workspace's saved-sender library from every newly created campaign, including web, API, and other production Eloquent creation paths. Capture applies to draft, scheduled, and immediate-send campaign modes, deduplicates by the existing normalized From Name/From Email pair, and preserves campaign/message sender values when saved sender records are later edited or deleted. Campaign updates, provider/reply-to/default-sender features, and any edit to `vendor/mettle/sendportal-core` remain outside this phase.

</domain>

<decisions>
## Implementation Decisions

### Auto-capture coverage and failure handling
- **D-01:** Auto-capture applies to every newly created production `Campaign` record, regardless of whether it was created through the browser campaign form, the package API, or another Eloquent creation path. Capture is creation-only; campaign updates do not auto-capture.
- **D-02:** Auto-capture applies to all campaign modes, including campaigns saved as drafts, scheduled campaigns, and immediate-send campaigns.
- **D-03:** Sender capture is best-effort. A failure to persist a newly captured sender must not make the campaign creation fail or roll back.
- **D-04:** When best-effort capture fails, the campaign remains successful but the application must provide a non-blocking warning and log the failure cause.

### Duplicate behavior
- **D-05:** A normalized pair that already exists is a no-op for auto-capture. The existing sender label and record remain untouched; auto-capture never overwrites user-managed label metadata.
- **D-06:** Concurrent campaigns with the same normalized pair must converge to one sender while both campaign creations succeed. The losing duplicate race is an expected no-op, not a user-facing warning.
- **D-07:** If a sender is deleted, a later campaign using the same normalized pair may create a new sender again. No deleted-pair history is required.
- **D-08:** Deduplication is a sender-library side effect only. It must not rewrite the campaign's submitted `from_name` or `from_email` values to match the existing sender's canonical values.

### Historical campaign and message integrity
- **D-09:** Editing or deleting a saved sender must not change the stored sender values on the campaign row or on any already-created message snapshot, including draft, queued, sending, and sent messages.
- **D-10:** If messages for an existing campaign are generated after the saved sender is edited or deleted, those messages must use the campaign's original stored From Name/From Email values, not current sender-library data.
- **D-11:** Direct edits to a campaign's own From Name/From Email fields remain allowed and authoritative. The isolation guarantee applies specifically to saved-sender CRUD changes, not explicit campaign edits.
- **D-12:** A sender with historical campaign or message usage may still be deleted through the normal existing delete flow; historical records must remain readable and operational.

### Carried forward from earlier phases
- Sender normalization remains centralized in `App\\Models\\Sender::normalizeInput`: trim all fields and lowercase From Email.
- Sender uniqueness remains workspace-scoped on the normalized `(workspace_id, from_name, from_email)` pair; label is not part of the duplicate key.
- Senders remain a shared library for active workspace members and every sender record operation remains current-workspace scoped.
- The campaign picker remains unnamed and convenience-only; package `from_name` and `from_email` fields remain the campaign submission contract.
- The host/package boundary remains hard: use host seams and published/overridden views, with zero edits under `vendor/mettle/sendportal-core`.

### the agent's Discretion
- Choose the auto-captured sender label convention. Area 2 was intentionally not discussed; research and planning may select a sensible label derived from the campaign data without adding a new provenance field.
- Choose the exact host integration seam (Campaign model observer/listener versus a host-side campaign repository/wrapper) while covering all required production creation paths and preserving the zero-vendor-edit boundary. The roadmap explicitly leaves this as a plan-time decision.
- Choose the concrete transaction, unique-conflict, warning, logging, and test-fixture mechanics needed to implement the decisions above across the supported database drivers. These are implementation details, not additional product scope.

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Milestone and phase requirements
- `.planning/ROADMAP.md` §Phase 7: phase goal, dependencies, SENDER-07/SENDER-09 mapping, and success criteria.
- `.planning/REQUIREMENTS.md` §Campaign Integration, §Delivery Integrity, and §Out of Scope: SENDER-07/SENDER-09 acceptance and excluded sender capabilities.
- `.planning/PROJECT.md` §Current Milestone, §Context, and §Key Decisions: saved-sender scope, copied sender values, and the hard zero-vendor-edit boundary.
- `.planning/STATE.md` §Accumulated Context and §Blockers/Concerns: Phase 5/6 sender decisions and the remaining plan-time seam choice.

### Prior sender phases
- `.planning/phases/05-sender-identity-management/05-CONTEXT.md` — shared-member sender CRUD, workspace isolation, and sender-management boundaries.
- `.planning/phases/05-sender-identity-management/05-01-SUMMARY.md` — Sender model, workspace relation, normalization, and composite uniqueness implementation.
- `.planning/phases/05-sender-identity-management/05-02-SUMMARY.md` — relation-scoped sender mutations, deletion behavior, and normalized duplicate handling.
- `.planning/phases/06-campaign-form-sender-selection/06-CONTEXT.md` — unnamed change-only picker, editable package fields, and host-only campaign-view seam.
- `.planning/phases/06-campaign-form-sender-selection/06-02-SUMMARY.md` — published campaign overrides, empty sender handling, and zero-vendor-edit verification.

### Host sender implementation and seams
- `app/Models/Sender.php` — centralized normalization and sender model contract.
- `app/Models/Workspace.php` — current workspace `senders()` relation.
- `app/Providers/AppServiceProvider.php` — current workspace resolver, host package seams, and campaign view composer.
- `app/Providers/EventServiceProvider.php` — available host event-provider seam.
- `routes/web.php` — authenticated/verified/`RequireWorkspace` campaign and host route boundaries.
- `routes/api.php` — API workspace middleware and package API route boundary.
- `database/migrations/2026_08_05_000000_create_senders_table.php` — workspace foreign key and normalized identity unique index.
- `resources/views/vendor/sendportal/campaigns/partials/form.blade.php` — Phase 6 picker and the authoritative package From fields.
- `tests/Feature/Workspaces/CampaignSenderSelectionTest.php` — campaign-view tenancy, empty-state, and no-submitted-sender regression patterns.

### SendPortal Core campaign and message persistence
- `vendor/mettle/sendportal-core/src/Http/Controllers/Campaigns/CampaignsController.php` — package web campaign create/store/update path.
- `vendor/mettle/sendportal-core/src/Http/Controllers/Api/CampaignsController.php` — package API campaign create/store/update path.
- `vendor/mettle/sendportal-core/src/Repositories/BaseTenantRepository.php` — shared tenant-scoped Eloquent store/update implementation.
- `vendor/mettle/sendportal-core/src/Repositories/Campaigns/BaseCampaignTenantRepository.php` — campaign repository model binding and tenant behavior.
- `vendor/mettle/sendportal-core/src/Providers/SendportalAppServiceProvider.php` — package binding for `CampaignTenantRepositoryInterface`.
- `vendor/mettle/sendportal-core/src/Models/Campaign.php` — campaign `from_name`/`from_email` storage and model lifecycle contract.
- `vendor/mettle/sendportal-core/src/Models/Message.php` — independent message sender fields and message lifecycle contract.
- `vendor/mettle/sendportal-core/src/Pipelines/Campaigns/CreateMessages.php` — copies campaign sender values into draft/immediate message rows.
- `vendor/mettle/sendportal-core/src/Http/Controllers/Campaigns/CampaignDuplicateController.php` — duplicate flow, which pre-fills a new create form rather than directly persisting a campaign.
- `vendor/mettle/sendportal-core/database/migrations/2017_04_28_223915_create_campaigns_table.php` — independent campaign sender columns.
- `vendor/mettle/sendportal-core/database/migrations/2019_07_10_194325_create_messages_table.php` — independent message sender snapshot columns.

### Codebase architecture references
- `.planning/codebase/ARCHITECTURE.md` — host/package boundary, tenancy resolver, and service/provider integration patterns.
- `.planning/codebase/STACK.md` — Laravel 11, Eloquent, supported databases, and test/runtime constraints.
- `.planning/codebase/INTEGRATIONS.md` — SendPortal Core ownership and database/provider integration boundaries.

### Official framework guidance used during discussion
- `https://laravel.com/docs/11.x/eloquent#events` — Eloquent model lifecycle events and observers.
- `https://laravel.com/docs/11.x/queries#upserts` — unique-index-backed upsert and duplicate matching semantics.
- `https://laravel.com/docs/11.x/database#database-transactions` — transaction commit and rollback behavior for Eloquent/database operations.

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `App\\Models\\Sender::normalizeInput()` — reuse the established trim/lowercase normalization before lookup or capture.
- `Workspace::senders()` — relation-first workspace scoping for lookup and creation; it keeps the sender library inside the active tenancy boundary.
- `AppServiceProvider` and `EventServiceProvider` — host-side bootstrap seams that can register an observer/listener or wrapper without editing the package.
- `Sendportal\\Base\\Models\\Campaign` and `Sendportal\\Base\\Models\\Message` — existing independent sender columns already provide the historical value storage needed by SENDER-09.
- `CreateMessages` — existing package pipeline that copies campaign From Name/From Email into message rows; use it as the integrity contract rather than introducing a sender foreign key.
- Phase 6 published campaign views and `CampaignSenderSelectionTest` — host-only package override and rendered-contract test patterns that must remain unchanged except for phase-scoped additions.

### Established Patterns
- Campaign web and API controllers both use `CampaignTenantRepositoryInterface`, whose base tenant repository persists the guarded package `Campaign` model with `workspace_id`.
- Sender records use a composite workspace/name/email database unique index in addition to normalized application input; concurrency handling must preserve the database guarantee across MySQL/PostgreSQL.
- Historical campaign/message sender values are copied text fields, not live relations to `App\\Models\\Sender`; sender CRUD therefore must not be allowed to rewrite them.
- Workspace-aware routes and package campaign routes already run behind the current workspace resolver and `RequireWorkspace`; new capture logic must use the campaign's trusted workspace context rather than request-supplied sender/workspace IDs.
- The package source is an upgrade boundary. Host-side listeners, model hooks, wrappers, or service-provider bindings must leave `vendor/mettle/sendportal-core` byte-for-byte unchanged.

### Integration Points
- Package web `CampaignsController@store`, package API `CampaignsController@store`, and any other production Eloquent `Campaign::create/save` path are the required capture coverage surface.
- `CampaignTenantRepositoryInterface` binding in `SendportalAppServiceProvider` is the alternative host-wrapper seam; it must be evaluated against both web and API consumers.
- `AppServiceProvider::boot()` / `EventServiceProvider` are the alternative model-event registration seams; the chosen approach must avoid coupling capture to only the rendered campaign form.
- `sendportal_campaigns.from_name/from_email` and `sendportal_messages.from_name/from_email` are the two persistence surfaces that the historical-integrity tests must inspect.
- Existing sender CRUD delete routes remain usable after capture; no foreign key from campaigns/messages to `senders` should be introduced for this phase.

</code_context>

<specifics>
## Specific Ideas

- The user explicitly wants all campaign creation paths and all campaign modes covered, but campaign updates are not auto-capture triggers.
- Auto-capture is a convenience feature and must not block a successful campaign save; capture failures should be visible without turning the campaign request into a failure.
- A duplicate pair must preserve the existing sender label, converge safely under concurrent creation, and never rewrite the campaign's own submitted sender values.
- The user stated: "campaign yang sudah dibuat ataupun email yang sudah dikirim tidak terpengaruh dengan data sender yang diubah". This was clarified to include campaign rows and every already-created message snapshot, including draft/queued/sending/sent states.
- Direct campaign edits remain allowed, while saved-sender edit/delete operations must not alter campaign or message sender values. Historical sender usage must not block normal sender deletion.

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope. The auto-captured label and exact host implementation seam remain planning discretion, not deferred product capabilities.

</deferred>

---

*Phase: 7-Sender Auto-Capture & Data Integrity*
*Context gathered: 2026-08-06*
