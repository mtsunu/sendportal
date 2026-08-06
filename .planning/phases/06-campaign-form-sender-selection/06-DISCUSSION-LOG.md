# Phase 6: Campaign Form Sender Selection - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-08-06
**Phase:** 6-Campaign Form Sender Selection
**Areas discussed:** Dropdown behavior

---

## Dropdown behavior

| Option | Description | Selected |
|--------|-------------|----------|
| Native select | Existing Bootstrap/package form style, blank placeholder, and change handler. | ✓ |
| Custom searchable picker | Richer search, but new JavaScript/accessibility behavior. | |
| Radio cards | Visible sender details, but more space and less suitable for growth. | |

| Option | Description | Selected |
|--------|-------------|----------|
| Label plus email | `Label — from@example.com`; identifies and disambiguates the sender. | ✓ |
| Label only | Compact, but similar labels are harder to distinguish. | |
| Name plus email | Clear identity, but hides the user-defined label. | |

| Option | Description | Selected |
|--------|-------------|----------|
| Replace both fields | Selecting a sender always copies both values; fields remain editable. | ✓ |
| Fill only blank fields | Preserves edits, but can create a mixed identity. | |
| Ask before replacing | Protects edits, but adds confirmation complexity. | |

| Option | Description | Selected |
|--------|-------------|----------|
| Disabled picker plus link | Explains the empty state and links to Senders. | |
| Hide the picker | Compact, but hides discoverability. | |
| Enabled empty picker | Blank enabled control; manual fields remain the path. | ✓ |

**User's choice:** Native select; Label plus email; Replace both fields; Enabled empty picker.
**Notes:** The initial selection remains blank to preserve the no-default requirement. Exact placeholder wording and minimal DOM/JavaScript details are left to implementation discretion.

---

## the agent's Discretion

- Exact blank-option wording and the minimal JavaScript implementation.

## Deferred Ideas

None.
