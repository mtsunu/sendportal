---
phase: 01-constraint-resolution-and-security-control
plan: 03
subsystem: dependency-security-policy
tags: [composer, php-8.4, security-policy, policy-source, fail-closed]
requires:
  - phase: 01-02
    provides: "PHP ^8.2 compatibility metadata and the approved three-ID native advisory policy."
provides:
  - "Composer policy-source failures are fail-closed for update and install through ignore-unreachable: false."
  - "Fresh-home PHP 8.4 install/audit evidence with the approved Laravel/Core constraints and advisory boundary intact."
affects:
  - "Phase 2 lockfile review"
  - "Phase 3 PHP 8.4 CI and runtime validation"
tech-stack:
  added: []
  patterns:
    - "Use isolated Composer homes with policy and platform override variables removed for dependency evidence."
    - "Prove external-policy failure behavior with a disposable loopback HTTPS policy and proxy bypass."
key-files:
  created:
    - ".planning/phases/01-constraint-resolution-and-security-control/01-03-SUMMARY.md"
  modified:
    - "composer.json"
key-decisions:
  - "Set Composer policy.ignore-unreachable to false while preserving the exact D-02 advisory exception map."
  - "Keep PHP ^8.2, Laravel ^11.0, and SendPortal Core ^3.0 unchanged; temporary outage policies remain outside the repository."
requirements-completed: [COMP-03]
duration: 21min
completed: 2026-07-22
status: complete
---

# Phase 01 Plan 03: Fail-Closed Composer Policy Summary

**Composer now fails closed for unreachable policy sources while a clean PHP 8.4.23 install and locked audit continue to resolve Laravel v11.55.0 with SendPortal Core v3.0.2.**

## Performance

- **Duration:** 21 min
- **Completed:** 2026-07-22T16:57:01Z
- **Tasks:** 2
- **Files modified:** 1 production manifest, 1 plan summary

## Accomplishments

- Added only `config.policy.ignore-unreachable: false` beside the existing native advisory policy.
- Preserved `php: ^8.2`, `laravel/framework: ^11.0`, `mettle/sendportal-core: ^3.0`, `block: true`, `audit: fail`, and exactly the three documented D-02 `ignore-id` entries.
- Revalidated a fresh, script-enabled PHP 8.4.23 install with Composer 2.10.2, a configured `composer audit --locked`, and no repository lockfile.
- Proved both `composer update --dry-run` and `composer install` reject the disposable unreachable HTTPS custom-policy source with proxy bypass enforced.

## Verification Evidence

| Check | Result |
| --- | --- |
| Strict manifest validation and exact policy assertion | Passed; `ignore-unreachable` is boolean `false`, with the unchanged narrow three-ID advisory map. |
| Isolated standard install | Passed under PHP 8.4.23 / Composer 2.10.2 with a new empty `COMPOSER_HOME`, scrubbed policy/platform variables, scripts enabled, and no platform bypasses. |
| Selected packages | `laravel/framework v11.55.0`; `mettle/sendportal-core v3.0.2`. |
| Configured `composer audit --locked` | Passed; Composer reported only the three documented ignored Laravel advisories. |
| Unreachable-policy `update --dry-run` | Exited `100`; Composer reported `curl error 7` for `https://127.0.0.1:9/sendportal-policy-outage`. |
| Unreachable-policy `install` | Exited `100`; Composer reported `curl error 7` for the same deliberate loopback HTTPS endpoint. |
| Repository lockfile boundary | Passed; `composer.lock` remains absent from the repository. |

The update/install probes used distinct disposable copies and Composer homes. Each probe strictly validated before its operation, scrubbed both upper/lower-case proxy variables, and set `NO_PROXY`/`no_proxy` to `127.0.0.1,localhost`. The source failure was therefore local, deliberate, and not sent through an external proxy.

## Task Commits

1. **Task 1: End-to-end fail-closed Composer policy on the normal PHP 8.4 path** — `e7e441d` (`fix`)
2. **Task 2: Prove update and install reject an unreachable isolated policy source** — evidence-only; both disposable probes were removed and their results are recorded here.

## Files Created/Modified

- `composer.json` — completed the native Composer security policy with `ignore-unreachable: false`.
- `.planning/phases/01-constraint-resolution-and-security-control/01-03-SUMMARY.md` — isolated PHP 8.4 and outage-path proof.

## Decisions Made

- Use Composer's documented boolean `false` form so no operation silently accepts an unreachable repository or custom policy source.
- Retain the owner-approved advisory exception boundary exactly; this plan neither broadens exceptions nor changes Laravel, SendPortal Core, scripts, or platform settings.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Verification bug] Corrected a zsh-reserved status variable in the disposable outage harness**
- **Found during:** Task 2
- **Issue:** The plan's `status=$?` assignment is invalid under zsh because `status` is read-only, preventing the probe result from being checked.
- **Fix:** Used `exit_status` in the temporary-only harness, then required each operation to exit nonzero and match its own URL/transport-error stanza.
- **Files modified:** None; the correction existed only in the removed temporary directory.
- **Verification:** Both operations exited `100` with the expected loopback `curl error 7` stanza.

## Known Stubs

None.

## Next Phase Readiness

- Phase 2 can create and review the first committed lockfile while retaining this fail-closed policy and reassessing the three time-bounded advisory exceptions.

## Self-Check: PASSED

- `composer.json` and this summary exist.
- Task commit `e7e441d` exists in Git history.
- The exact policy/dependency assertion passed and the repository remains free of `composer.lock`.
