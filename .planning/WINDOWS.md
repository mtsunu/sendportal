---
schema_version: 1
open_count: 3
waived_count: 0
fixed_count: 0
total_count: 3
last_updated: 2026-07-22T16:58:53.336Z
---

# Broken Windows Ledger

> Cross-phase defect register. `/gsd-ship` blocks while `open_count > 0`.
> Waive with `gsd-tools windows waive <id> "<reason>"` (reason required).
> Mark fixed with `gsd-tools windows fixed <id>`.

| id | phase | kind | file | line | description | status | reason | recorded_at | resolved_at |
|----|-------|------|------|------|-------------|--------|--------|-------------|-------------|
| 1 | 01 | deviation | .planning/phases/01-constraint-resolution-and-security-control/01-01-SUMMARY.md |  | Temporary candidate ignore-id ordering was canonicalized to correct a strict verification-array comparison artifact. | open |  | 2026-07-22T16:03:21.142Z |  |
| 2 | 01 | deviation | .planning/phases/01-constraint-resolution-and-security-control/01-02-SUMMARY.md |  | Ignore-free reporting audit exits 1 when it reports the three approved advisories; exact-ID parser treats that as required negative evidence. | open |  | 2026-07-22T16:10:45.008Z |  |
| 3 | 01 | deviation | .planning/phases/01-constraint-resolution-and-security-control/01-03-PLAN.md |  | Task 2 outage harness used exit_status because zsh reserves status as read-only. | open |  | 2026-07-22T16:58:53.336Z |  |

````json
[
  {
    "id": 1,
    "kind": "deviation",
    "phase": "01",
    "file": ".planning/phases/01-constraint-resolution-and-security-control/01-01-SUMMARY.md",
    "line": null,
    "description": "Temporary candidate ignore-id ordering was canonicalized to correct a strict verification-array comparison artifact.",
    "status": "open",
    "reason": "",
    "recorded_at": "2026-07-22T16:03:21.142Z",
    "resolved_at": null
  },
  {
    "id": 2,
    "kind": "deviation",
    "phase": "01",
    "file": ".planning/phases/01-constraint-resolution-and-security-control/01-02-SUMMARY.md",
    "line": null,
    "description": "Ignore-free reporting audit exits 1 when it reports the three approved advisories; exact-ID parser treats that as required negative evidence.",
    "status": "open",
    "reason": "",
    "recorded_at": "2026-07-22T16:10:45.008Z",
    "resolved_at": null
  },
  {
    "id": 3,
    "kind": "deviation",
    "phase": "01",
    "file": ".planning/phases/01-constraint-resolution-and-security-control/01-03-PLAN.md",
    "line": null,
    "description": "Task 2 outage harness used exit_status because zsh reserves status as read-only.",
    "status": "open",
    "reason": "",
    "recorded_at": "2026-07-22T16:58:53.336Z",
    "resolved_at": null
  }
]
````
