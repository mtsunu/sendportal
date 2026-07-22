---
schema_version: 1
open_count: 1
waived_count: 0
fixed_count: 0
total_count: 1
last_updated: 2026-07-22T16:03:21.142Z
---

# Broken Windows Ledger

> Cross-phase defect register. `/gsd-ship` blocks while `open_count > 0`.
> Waive with `gsd-tools windows waive <id> "<reason>"` (reason required).
> Mark fixed with `gsd-tools windows fixed <id>`.

| id | phase | kind | file | line | description | status | reason | recorded_at | resolved_at |
|----|-------|------|------|------|-------------|--------|--------|-------------|-------------|
| 1 | 01 | deviation | .planning/phases/01-constraint-resolution-and-security-control/01-01-SUMMARY.md |  | Temporary candidate ignore-id ordering was canonicalized to correct a strict verification-array comparison artifact. | open |  | 2026-07-22T16:03:21.142Z |  |

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
  }
]
````
