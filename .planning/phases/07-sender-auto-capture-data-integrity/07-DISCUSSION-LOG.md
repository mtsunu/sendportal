# Phase 7: Sender Auto-Capture & Data Integrity - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-08-06
**Phase:** 7-Sender Auto-Capture & Data Integrity
**Areas discussed:** Capture coverage, Duplicate behavior, Historical lifecycle

---

## Capture coverage

Research context used: [Laravel Eloquent model events](https://laravel.com/docs/11.x/eloquent#events).

### Q1 — Campaign creation paths

| Option | Description | Selected |
|--------|-------------|----------|
| Every new campaign | Capture UI, API, and other Eloquent creation paths; creation only, not updates. | ✓ |
| Browser form only | API-created campaigns do not auto-capture. | |
| Other | Free-form in-scope choice. | |

**User's choice:** Every newly created campaign path.
**Notes:** This includes web, API, and other production Eloquent creation paths; campaign updates remain outside auto-capture.

### Q2 — Campaign modes

| Option | Description | Selected |
|--------|-------------|----------|
| All modes | Capture drafts, scheduled campaigns, and immediate-send campaigns. | ✓ |
| Delivery-intended only | Exclude campaigns saved only as drafts. | |
| Immediate sends only | Exclude drafts and scheduled campaigns. | |
| Other | Free-form in-scope choice. | |

**User's choice:** All campaign creation modes.

### Q3 — Capture failure policy

| Option | Description | Selected |
|--------|-------------|----------|
| Strict capture | Fail or roll back campaign creation if the new sender cannot be persisted. | |
| Best effort | Keep the campaign and report/log the capture failure. | ✓ |
| Other | Free-form in-scope policy. | |

**User's choice:** Best effort.
**Notes:** Sender capture must not block a successful campaign save.

### Q4 — Capture failure visibility

| Option | Description | Selected |
|--------|-------------|----------|
| Non-blocking warning plus logging | Save the campaign, inform the user that automatic sender saving failed, and log the cause. | ✓ |
| Logging only | Save the campaign without a user-facing warning. | |
| Background retry | Save the campaign and retry capture asynchronously. | |
| Other | Free-form in-scope policy. | |

**User's choice:** Non-blocking warning plus logging.

---

## Duplicate behavior

Research context used: [Laravel Query Builder upserts](https://laravel.com/docs/11.x/queries#upserts) and the existing sender composite unique index.

### Q1 — Existing label

| Option | Description | Selected |
|--------|-------------|----------|
| No-op and preserve label | Auto-capture never overwrites a user-managed existing sender label. | ✓ |
| Refresh existing label | Update the label from the campaign-derived label. | |
| Other | Free-form in-scope policy. | |

**User's choice:** No-op and preserve the existing label.

### Q2 — Concurrent duplicates

| Option | Description | Selected |
|--------|-------------|----------|
| Converge silently to one sender | Both campaigns succeed; the second capture is an expected no-op. | ✓ |
| Warn on the losing capture | Both campaigns succeed, but the race is reported as a best-effort capture failure. | |
| Other | Free-form in-scope policy. | |

**User's choice:** Converge silently to one sender.

### Q3 — Recreating deleted identities

| Option | Description | Selected |
|--------|-------------|----------|
| Create it again | A later campaign repopulates the library when the pair no longer exists. | ✓ |
| Do not recreate it | Remember deleted pairs through additional deletion-history behavior. | |
| Other | Free-form in-scope policy. | |

**User's choice:** Recreate a sender after deletion when later campaign activity uses the pair.

### Q4 — Campaign values on duplicate match

| Option | Description | Selected |
|--------|-------------|----------|
| Preserve campaign values | Deduplicate only the sender side effect; keep exactly what the user submitted. | ✓ |
| Canonicalize campaign values | Replace them with the existing sender's normalized values. | |
| Other | Free-form in-scope policy. | |

**User's choice:** Preserve the campaign's submitted From Name/From Email values.

---

## Historical lifecycle

Research context used: [Laravel database transactions](https://laravel.com/docs/11.x/database#database-transactions) and the existing independent campaign/message sender columns.

### Q1 — Records protected by integrity guarantee

| Option | Description | Selected |
|--------|-------------|----------|
| Campaign plus every existing message snapshot | Cover campaign rows and draft, queued, sending, and sent message rows. | ✓ |
| Campaign plus sent-message history | Pending messages are covered indirectly through unchanged campaign values. | |
| Other | Free-form in-scope coverage boundary. | |

**User's choice:** The user first stated, "campaign yang sudah dibuat ataupun email yang sudah dikirim tidak terpengaruh dengan data sender yang diubah". After clarification, the selected boundary is the campaign plus every already-created message snapshot, including draft/queued/sending/sent rows.

### Q2 — In-flight campaigns

| Option | Description | Selected |
|--------|-------------|----------|
| Original campaign values | Messages generated later for an existing campaign use the campaign's stored sender values. | ✓ |
| Current sender values | Only already-created messages remain unchanged. | |
| Other | Free-form in-scope policy. | |

**User's choice:** Later messages from an existing campaign must use the original campaign values.

### Q3 — Direct campaign edits

| Option | Description | Selected |
|--------|-------------|----------|
| Keep direct campaign edits allowed | Explicit campaign edits remain authoritative; sender CRUD changes remain isolated. | ✓ |
| Freeze campaign sender values | Freeze From Name/From Email after campaign creation. | |
| Other | Free-form in-scope policy. | |

**User's choice:** Keep direct campaign edits allowed.

### Q4 — Deletion with history

| Option | Description | Selected |
|--------|-------------|----------|
| Delete normally | Preserve the existing delete flow; historical campaign/message values remain independent. | ✓ |
| Additional historical-usage warning | Deletion remains allowed but shows extra context. | |
| Block deletion | Retain a sender while historical usage exists. | |
| Other | Free-form in-scope policy. | |

**User's choice:** Delete normally; historical usage must not block sender deletion.

---

## the agent's Discretion

- Auto-captured sender label convention; Area 2 was not selected for discussion.
- Exact host seam for all creation paths: model observer/listener versus host repository/wrapper.
- Concrete database-conflict handling, warning copy, logging channel, and test-fixture mechanics.

## Deferred Ideas

None — discussion stayed within phase scope.
