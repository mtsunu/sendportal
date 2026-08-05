# Requirements: SendPortal — v1.2 Saved Sender Identities

**Defined:** 2026-07-25
**Core Value:** Operators can save a sender identity once and reuse it when creating campaigns, instead of retyping From Name / From Email every time.

## Milestone v1.2 Requirements

Requirements for the Saved Sender Identities milestone. Each maps to a roadmap phase.

### Sender Management

- [ ] **SENDER-01**: User can create a saved sender identity with a label, a From Name, and a From Email, scoped to the current workspace.
- [ ] **SENDER-02**: User can open a dedicated Senders page (reachable from the app navigation) that lists all saved sender identities for the current workspace.
- [x] **SENDER-03**: User can edit an existing saved sender's label, From Name, and From Email.
- [x] **SENDER-04**: User can delete a saved sender.
- [x] **SENDER-05**: Sender input is validated (label and From Name required; From Email must be a valid email address) and senders are workspace-isolated — a user cannot view, edit, or use another workspace's senders.

### Campaign Integration

- [ ] **SENDER-06**: When creating a campaign, the user can pick a saved sender from a dropdown that auto-fills the From Name and From Email fields; both fields remain manually editable and no sender is pre-selected by default.
- [ ] **SENDER-07**: Creating a campaign with a From Name / From Email pair that is not already saved automatically stores it as a new sender for the workspace, deduplicated so an identical pair is never stored twice.

### Delivery Integrity

- [ ] **SENDER-08**: The saved-sender UI on the campaign form is delivered with zero edits to `vendor/mettle/sendportal-core` — surfaced via a published/overridden package view and host-side seams (the same discipline as v1.1's `ThrottledSesAdapter`).
- [ ] **SENDER-09**: Editing or deleting a saved sender does not retroactively alter the From Name / From Email of campaigns already created or sent (sender values are copied onto each message at send time).

## Future Requirements

Deferred to a future release. Tracked but not in the current roadmap.

### Sender Enhancements

- **SENDER-F01**: A saved sender can also bundle a default Email Service (SES/Mailgun provider), so selecting a sender preselects the sending provider.
- **SENDER-F02**: A saved sender can store a separate Reply-To address (requires extending the campaign/message send path, which has no Reply-To field today).
- **SENDER-F03**: A workspace can mark one sender as the default, auto-filled on new campaigns.
- **SENDER-F04**: Sender verification status surfaced from the provider (e.g. SES verified-identity check) shown alongside each sender.

## Out of Scope

Explicitly excluded. Documented to prevent scope creep.

| Feature | Reason |
|---------|--------|
| Editing `vendor/mettle/sendportal-core` to add sender UI | Hard project boundary — the package must stay upgradable; extend via published views + host seams. |
| Bundling Email Service / provider into a sender | Deferred to SENDER-F01 — keeps v1.2 scope to identity fields only. |
| Reply-To on senders | Deferred to SENDER-F02 — campaign/message has no Reply-To field today; adding it expands the send path. |
| Default-sender auto-selection | Deferred to SENDER-F03 — v1.2 selection is always manual via the dropdown. |
| Sender domain / DNS (SPF/DKIM) verification | Provider-side concern (SES/Mailgun), not identity storage. |
| Sharing senders across workspaces | Violates the per-workspace tenancy boundary. |

## Traceability

Which phases cover which requirements. Populated during roadmap creation.

| Requirement | Phase | Status |
|-------------|-------|--------|
| SENDER-01 | Phase 5 | Pending |
| SENDER-02 | Phase 5 | Pending |
| SENDER-03 | Phase 5 | Complete |
| SENDER-04 | Phase 5 | Complete |
| SENDER-05 | Phase 5 | Complete |
| SENDER-06 | Phase 6 | Pending |
| SENDER-07 | Phase 7 | Pending |
| SENDER-08 | Phase 6 | Pending |
| SENDER-09 | Phase 7 | Pending |

**Coverage:**

- Milestone v1.2 requirements: 9 total
- Mapped to phases: 9/9 ✓
- Unmapped: 0 ✓

---
*Requirements defined: 2026-07-25*
*Last updated: 2026-07-25 after roadmap creation (Phases 5-7 mapped, 9/9 coverage)*
