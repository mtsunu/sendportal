# Roadmap: SendPortal PHP 8.4 Compatibility

## Milestones

- ✅ **v1.0 PHP 8.4 Compatibility** — Phases 1-3 (shipped 2026-07-25)
- ✅ **v1.1 SES Sending Reliability** — Phase 4 (shipped 2026-07-25)
- 🚧 **v1.2 Saved Sender Identities** — Phases 5-7 (in progress)

## Phases

<details>
<summary>✅ v1.0 PHP 8.4 Compatibility (Phases 1-3) — SHIPPED 2026-07-25</summary>

- [x] Phase 1: Constraint Resolution and Security Control (12/12 plans) — completed 2026-07-24, verified passed (7/7 truths)
- [x] Phase 2: Reproducible Dependency Snapshot (2/2 plans) — completed 2026-07-24
- [x] Phase 3: PHP 8.4 Runtime, Core Integration, and CI Verification (1/1 plan) — completed 2026-07-25

Full details archived in `.planning/milestones/v1.0-ROADMAP.md`.

</details>

<details>
<summary>✅ v1.1 SES Sending Reliability (Phase 4) — SHIPPED 2026-07-25</summary>

- [x] Phase 4: Coordinated SES rate limiting + 2 bug fixes (1/1 plan) — completed 2026-07-25, verified VERIFIED-WITH-CAVEATS (5/5 criteria; 3 CI/live-SES checks deferred to CI/prod)

Full details archived in `.planning/milestones/v1.1-ROADMAP.md`.

</details>

### 🚧 v1.2 Saved Sender Identities (In Progress)

**Milestone Goal:** Operators can save a sender identity (label + From Name + From Email) once and reuse it via a dropdown when creating a campaign, instead of retyping it every time.

- [x] **Phase 5: Sender Identity Management** - Operators can create, list, edit, and delete workspace-scoped saved sender identities (2 plans) (completed 2026-08-06)
- [x] **Phase 6: Campaign Form Sender Selection** - Users can pick a saved sender from a dropdown on the campaign form, delivered with zero `vendor/` edits (completed 2026-08-06)
- [ ] **Phase 7: Sender Auto-Capture & Data Integrity** - New senders are captured automatically (deduplicated) from campaign creation, and historical campaigns are immune to later sender edits/deletes

## Phase Details

### Phase 5: Sender Identity Management

**Goal**: Operators can fully manage a per-workspace library of saved sender identities (label, From Name, From Email) through a dedicated Senders page.
**Depends on**: Nothing (first phase of this milestone; builds on existing workspace/tenancy infrastructure from v1.0/v1.1)
**Requirements**: SENDER-01, SENDER-02, SENDER-03, SENDER-04, SENDER-05
**Success Criteria** (what must be TRUE):

  1. User can create a new saved sender identity (label, From Name, From Email) scoped to the current workspace.
  2. User can open a dedicated Senders page, reachable from app navigation, listing all saved senders for the current workspace.
  3. User can edit an existing sender's label, From Name, and From Email, and the change is reflected in the Senders list.
  4. User can delete a saved sender.
  5. Invalid sender input (missing label/From Name, malformed From Email) is rejected with validation errors, and a user cannot view, edit, or delete another workspace's senders.

**Plans**: 2/2 plans executed
Plans:

- [x] 05-01-PLAN.md — Establish the workspace-scoped sender create/list tracer slice
- [x] 05-02-PLAN.md — Complete sender edit/delete, validation, and isolation coverage

**UI hint**: yes

### Phase 6: Campaign Form Sender Selection

**Goal**: Users can pick a saved sender when creating a campaign, with the selection auto-filling the From Name/From Email fields — delivered with zero edits to `vendor/mettle/sendportal-core`.
**Depends on**: Phase 5
**Requirements**: SENDER-06, SENDER-08
**Success Criteria** (what must be TRUE):

  1. The campaign create/edit form shows a dropdown listing the current workspace's saved senders.
  2. Selecting a sender from the dropdown auto-fills the From Name and From Email fields, and both fields remain manually editable afterward.
  3. No sender is pre-selected by default when the campaign form loads.
  4. The dropdown is surfaced via a published/overridden package view plus a host-side service-provider seam — zero lines changed in `vendor/mettle/sendportal-core` (same discipline as the v1.1 `ThrottledSesAdapter` override).

**Plans**: 2/2 plans executed
Plans:

- [x] 06-01-PLAN.md — Establish the create-form sender-selection tracer and host package-view seam
- [x] 06-02-PLAN.md — Complete edit, empty-state, escaping, tenancy, and upgrade-boundary coverage

**UI hint**: yes

### Phase 7: Sender Auto-Capture & Data Integrity

**Goal**: The sender library grows automatically from campaign activity without duplicate entries, and previously created or sent campaigns are unaffected by later sender edits or deletes.
**Depends on**: Phase 5, Phase 6 (reuses the vendor-boundary integration seam established there; the exact auto-capture hook — Campaign model observer vs. host wrapper — is a plan-time decision, not fixed at roadmap level)
**Requirements**: SENDER-07, SENDER-09
**Success Criteria** (what must be TRUE):

  1. Creating a campaign with a From Name/From Email pair that is not already saved automatically stores it as a new sender for the workspace.
  2. Creating a campaign with a From Name/From Email pair matching an existing sender does not create a duplicate.
  3. Editing a saved sender's label/From Name/From Email does not change the From Name/From Email already stored on previously created or sent campaigns.
  4. Deleting a saved sender does not alter or break previously created or sent campaigns.

**Plans**: 2 plans

Plans:
**Wave 1**

- [ ] 07-01-PLAN.md — Build the after-commit Campaign auto-capture tracer with normalization, atomic deduplication, and route/race coverage

**Wave 2** *(blocked on Wave 1 completion)*

- [ ] 07-02-PLAN.md — Add nonblocking API failure warnings and prove campaign/message snapshot integrity after sender edits or deletes

## Progress

| Phase | Milestone | Plans Complete | Status | Completed |
|-------|-----------|----------------|--------|-----------|
| 1. Constraint Resolution and Security Control | v1.0 | 12/12 | Complete | 2026-07-24 |
| 2. Reproducible Dependency Snapshot | v1.0 | 2/2 | Complete | 2026-07-24 |
| 3. PHP 8.4 Runtime, Core Integration, and CI Verification | v1.0 | 1/1 | Complete | 2026-07-25 |
| 4. Coordinated SES rate limiting + 2 bug fixes | v1.1 | 1/1 | Complete | 2026-07-25 |
| 5. Sender Identity Management | v1.2 | 2/2 | Complete    | 2026-08-06 |
| 6. Campaign Form Sender Selection | v1.2 | 2/2 | Complete    | 2026-08-06 |
| 7. Sender Auto-Capture & Data Integrity | v1.2 | 0/TBD | Not started | - |
