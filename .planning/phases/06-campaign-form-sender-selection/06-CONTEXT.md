# Phase 6: Campaign Form Sender Selection - Context

**Gathered:** 2026-08-06
**Status:** Ready for planning

<domain>
## Phase Boundary

Add a saved-sender picker to the SendPortal Core campaign create/edit form. Selecting a workspace sender must populate From Name and From Email while keeping both fields editable, with no default sender selected. The feature must be delivered through host-side seams and published/overridden package views without modifying `vendor/mettle/sendportal-core`.

</domain>

<decisions>
## Implementation Decisions

### Dropdown behavior
- **D-01:** Use a native single-select dropdown styled consistently with the existing package/Bootstrap form controls rather than a custom searchable widget.
- **D-02:** Render each sender option as `Label — from@example.com`, using the user-defined label plus email to distinguish entries.
- **D-03:** Selecting a sender replaces both From Name and From Email values. The two inputs remain manually editable after autofill.
- **D-04:** When no saved senders exist, keep an enabled blank picker with no special disabled-state or empty-state link; the ordinary manual From Name and From Email inputs remain the usable path.
- **D-05:** Preserve a blank option as the initial selection so the campaign create form has no pre-selected sender. The dropdown is a convenience selector, not a submitted campaign identity field.

### the agent's Discretion
- Exact placeholder wording for the blank option, provided it is visibly unselected and does not submit a sender identity.
- The minimal JavaScript mechanism and DOM hooks used to copy selected sender values into the existing inputs.

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Milestone and phase requirements
- `.planning/PROJECT.md` — v1.2 saved-sender goal, hard host/package boundary, no-default policy, and sender identity scope.
- `.planning/REQUIREMENTS.md` §Campaign Integration and §Out of Scope — SENDER-06/SENDER-08 acceptance and excluded provider/reply-to/default capabilities.
- `.planning/ROADMAP.md` §Phase 6: Campaign Form Sender Selection — phase goal, dependencies, requirements, and success criteria.
- `.planning/STATE.md` §Accumulated Context — prior sender normalization, workspace-scoping, and zero-vendor-edit decisions.

### Prior sender implementation
- `.planning/phases/05-sender-identity-management/05-01-SUMMARY.md` — sender model, workspace relation, package-layout view pattern, and host sidebar seam.
- `.planning/phases/05-sender-identity-management/05-02-SUMMARY.md` — relation-scoped sender access, shared-member behavior, and normalization contract.
- `app/Models/Sender.php` — sender fields and trim/lowercase-email normalization.
- `app/Models/Workspace.php` — current workspace `senders()` relation.
- `app/Providers/AppServiceProvider.php` — existing host-side service-provider seams and SendPortal resolver setup.

### Campaign package integration
- `vendor/mettle/sendportal-core/resources/views/campaigns/create.blade.php` — package create wrapper and shared form inclusion.
- `vendor/mettle/sendportal-core/resources/views/campaigns/edit.blade.php` — package edit wrapper and shared form inclusion.
- `vendor/mettle/sendportal-core/resources/views/campaigns/partials/form.blade.php` — existing From Name/From Email fields, package form components, and JavaScript stack.
- `vendor/mettle/sendportal-core/src/Http/Controllers/Campaigns/CampaignsController.php` — package-owned create/edit methods and view data currently passed to the form.
- `vendor/mettle/sendportal-core/src/Http/Requests/CampaignStoreRequest.php` — existing campaign field contract; sender picker must not replace the required editable From fields.
- `vendor/mettle/sendportal-core/src/SendportalBaseServiceProvider.php` — package view namespace and published view override path.
- `vendor/mettle/sendportal-core/src/View/Components/SelectField.php` — existing package select component behavior.
- `https://developer.mozilla.org/en-US/docs/Web/HTML/Reference/Elements/select` — native select accessibility, blank option, and change-event behavior.

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `App\Models\Sender` and `Workspace::senders()` — query the current workspace's saved sender collection and retain the established normalization/tenancy model.
- `x-sendportal.select-field`, `x-sendportal.text-field`, and the existing campaign form partial — preserve package form markup and Bootstrap-compatible rendering.
- Existing `@push('js')` block in the campaign form — suitable integration point for the small selection-to-input autofill handler.

### Established Patterns
- Host views are rendered in the package layout and host fragments are injected through `AppServiceProvider`; Phase 5 explicitly preserved this pattern with zero vendor changes.
- Package views are published to `resources/views/vendor/sendportal`, allowing host overrides of `sendportal::campaigns.create`, `edit`, and `partials.form` while keeping vendor files untouched.
- Workspace data is resolved through the authenticated user's active workspace relation for host-owned records; campaign routes are already protected by `auth`, `verified`, and `RequireWorkspace`.
- The package campaign request still requires `from_name` and `from_email`; the picker should only copy values client-side and should not introduce a new required request field.

### Integration Points
- `vendor/mettle/sendportal-core/src/Http/Controllers/Campaigns/CampaignsController.php` owns campaign create/edit and does not provide sender data, so the plan must establish a narrowly targeted host view-data seam.
- `vendor/mettle/sendportal-core/resources/views/campaigns/partials/form.blade.php` is included by both create and edit wrappers, making it the shared UI integration point.
- `app/Providers/AppServiceProvider.php` is the existing host bootstrap seam for package integration and can register the selected view-data mechanism without a vendor edit.
- `resources/views/vendor/sendportal/campaigns/...` is the host override destination created by the package's `sendportal-views` publishing contract.

</code_context>

<specifics>
## Specific Ideas

- The selected option format is explicitly `Label — from@example.com`.
- Choosing a sender is intentionally an overwrite action for both editable From fields; users can then adjust either field manually.
- An empty sender library should not block campaign creation or imply a missing configuration; the picker remains blank and enabled while manual fields continue to work.
- The phase discussion used MDN's native `<select>` guidance as the accessibility and behavior reference.

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope.

</deferred>

---

*Phase: 6-Campaign Form Sender Selection*
*Context gathered: 2026-08-06*
