---
schema_version: 1
open_count: 8
waived_count: 0
fixed_count: 1
total_count: 9
last_updated: 2026-08-06T08:26:09.606Z
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
| 4 | 01 | deviation | tests/Composer/ComposerPolicyGuardTest.php |  | Independent review found and fixed per-segment route fail-open and post-probe manifest race gaps | fixed |  | 2026-07-23T07:04:54.593Z | 2026-07-23T07:05:19.932Z |
| 5 | 05 | unrun-verify | .planning/phases/05-sender-identity-management/deferred-items.md |  | Default MySQL PHPUnit and php-cs-fixer commands unavailable in this environment; SQLite focused sender suite passes | open |  | 2026-08-05T14:46:07.538Z |  |
| 6 | 05 | deviation | .planning/phases/05-sender-identity-management/deferred-items.md |  | Full SQLite suite has one unrelated pre-existing SetupTest failure | open |  | 2026-08-05T14:46:07.598Z |  |
| 7 | 05 | unrun-verify | vendor/bin/php-cs-fixer |  | PHP-CS-Fixer verification could not run because vendor/bin/php-cs-fixer is unavailable locally. | open |  | 2026-08-05T14:54:43.209Z |  |
| 8 | 05 | unmet-truth | tests/Feature/Setup/SetupTest.php | 54 | Full SQLite suite retains unrelated pre-existing SetupTest failure: expected 5 but received 0. | open |  | 2026-08-05T14:54:43.269Z |  |
| 9 | 06 | unrun-verify | manual browser campaign create/edit |  | Manual browser verification was not run in this environment; create/edit selection, blank reset, post-selection editing, zero-sender fallback, responsive layout, and keyboard focus remain for end-of-phase human verification. | open |  | 2026-08-06T08:26:09.606Z |  |

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
  },
  {
    "id": 4,
    "kind": "deviation",
    "phase": "01",
    "file": "tests/Composer/ComposerPolicyGuardTest.php",
    "line": null,
    "description": "Independent review found and fixed per-segment route fail-open and post-probe manifest race gaps",
    "status": "fixed",
    "reason": "",
    "recorded_at": "2026-07-23T07:04:54.593Z",
    "resolved_at": "2026-07-23T07:05:19.932Z"
  },
  {
    "id": 5,
    "kind": "unrun-verify",
    "phase": "05",
    "file": ".planning/phases/05-sender-identity-management/deferred-items.md",
    "line": null,
    "description": "Default MySQL PHPUnit and php-cs-fixer commands unavailable in this environment; SQLite focused sender suite passes",
    "status": "open",
    "reason": "",
    "recorded_at": "2026-08-05T14:46:07.538Z",
    "resolved_at": null
  },
  {
    "id": 6,
    "kind": "deviation",
    "phase": "05",
    "file": ".planning/phases/05-sender-identity-management/deferred-items.md",
    "line": null,
    "description": "Full SQLite suite has one unrelated pre-existing SetupTest failure",
    "status": "open",
    "reason": "",
    "recorded_at": "2026-08-05T14:46:07.598Z",
    "resolved_at": null
  },
  {
    "id": 7,
    "kind": "unrun-verify",
    "phase": "05",
    "file": "vendor/bin/php-cs-fixer",
    "line": null,
    "description": "PHP-CS-Fixer verification could not run because vendor/bin/php-cs-fixer is unavailable locally.",
    "status": "open",
    "reason": "",
    "recorded_at": "2026-08-05T14:54:43.209Z",
    "resolved_at": null
  },
  {
    "id": 8,
    "kind": "unmet-truth",
    "phase": "05",
    "file": "tests/Feature/Setup/SetupTest.php",
    "line": 54,
    "description": "Full SQLite suite retains unrelated pre-existing SetupTest failure: expected 5 but received 0.",
    "status": "open",
    "reason": "",
    "recorded_at": "2026-08-05T14:54:43.269Z",
    "resolved_at": null
  },
  {
    "id": 9,
    "kind": "unrun-verify",
    "phase": "06",
    "file": "manual browser campaign create/edit",
    "line": null,
    "description": "Manual browser verification was not run in this environment; create/edit selection, blank reset, post-selection editing, zero-sender fallback, responsive layout, and keyboard focus remain for end-of-phase human verification.",
    "status": "open",
    "reason": "",
    "recorded_at": "2026-08-06T08:26:09.606Z",
    "resolved_at": null
  }
]
````
