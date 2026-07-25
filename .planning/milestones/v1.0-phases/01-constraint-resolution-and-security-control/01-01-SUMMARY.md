---
phase: 01-constraint-resolution-and-security-control
plan: 01
subsystem: dependency-resolution
tags: [composer, php-8.4, packagist, laravel-11, security-policy]
requires: []
provides:
  - "Clean-environment PHP 8.4 / Composer 2.10.2 dry-run solver evidence for the approved three-advisory exception."
  - "Proof that a stable tagged Laravel 11 candidate resolves without modifying repository dependency artifacts."
affects:
  - "01-02 manifest policy application"
  - "Phase 2 lockfile review"
tech-stack:
  added: []
  patterns:
    - "Run exploratory Composer solves in a copied temporary directory with a fresh COMPOSER_HOME and an explicitly scrubbed policy/platform environment."
key-files:
  created:
    - ".planning/phases/01-constraint-resolution-and-security-control/01-01-SUMMARY.md"
  modified: []
key-decisions:
  - "Used the verified Herd Composer 2.10.2 binary under PHP 8.4.23 for all recorded Composer evidence."
  - "The temporary candidate retained native blocking/audit policy and limited ignore-id to the owner-approved three advisory IDs."
patterns-established:
  - "Composer solver evidence must use real PHP, a fresh COMPOSER_HOME, and no inherited Composer policy or platform override variables."
requirements-completed: [COMP-01, COMP-03]
coverage:
  - id: D1
    description: "A PHP 8.4 Composer 2.10.2 isolated dry-run resolves a stable Laravel 11 graph under the exact approved advisory exception."
    requirement: COMP-01
    verification:
      - kind: other
        ref: "fresh-COMPOSER_HOME composer update --dry-run --prefer-dist --no-interaction --no-scripts --no-progress"
        status: pass
    human_judgment: false
  - id: D2
    description: "The temporary policy permits only the three D-02 advisory IDs and leaves repository dependency files unchanged."
    requirement: COMP-03
    verification:
      - kind: other
        ref: "temporary composer.json exact-policy PHP assertion and repository non-mutation assertion"
        status: pass
    human_judgment: false
duration: 6min
completed: 2026-07-22
status: complete
---

# Phase 01 Plan 01: Isolated PHP 8.4 Composer Solver Evidence Summary

**A fresh-COMPOSER_HOME PHP 8.4.23 / Composer 2.10.2 probe resolved stable Laravel v11.55.0 under the exact three-ID native advisory policy without changing repository dependency artifacts.**

## Performance

- **Duration:** 6 min
- **Started:** 2026-07-22T15:56:00Z
- **Completed:** 2026-07-22T16:01:41Z
- **Tasks:** 1
- **Files modified:** 1

## Accomplishments

- Verified the real runtime was PHP 8.4.23 and used `/Users/meigire/Library/Application Support/Herd/bin/composer` at Composer 2.10.2 for every recorded Composer command.
- Copied the repository to an operating-system temporary directory, created an empty `COMPOSER_HOME`, and invoked Composer with `COMPOSER`, `COMPOSER_HOME`, `COMPOSER_POLICY`, `COMPOSER_NO_BLOCKING`, `COMPOSER_NO_SECURITY_BLOCKING`, `COMPOSER_IGNORE_PLATFORM_REQ`, and `COMPOSER_IGNORE_PLATFORM_REQS` unset before setting only the fresh `COMPOSER_HOME`.
- Validated the temporary candidate and completed `composer update --dry-run --prefer-dist --no-interaction --no-scripts --no-progress` successfully; it selected tagged `laravel/framework v11.55.0` across 144 planned installs and did not select `11.x-dev`.
- Confirmed the repository still has no `composer.lock` or `vendor/` directory after the probe.

## Solver Evidence

The copied manifest changed only the experimental candidate: PHP became `^8.2`, Roave was removed, Laravel remained `^11.0`, SendPortal Core remained `^3.0`, `minimum-stability` was `stable`, and `prefer-stable` remained enabled. It added native `config.policy.advisories` with `block: true`, `audit: fail`, and exactly these `ignore-id` entries:

| Advisory ID | Recorded reason |
| --- | --- |
| `PKSA-m5cs-t1y6-qpcs` | Internal-only application; residual risk accepted by the project owner on 2026-07-22. Expires at Phase 2 lockfile review or when a compatible stable SendPortal Core permits a Laravel upgrade, whichever occurs first. |
| `PKSA-3r5d-mb8f-1qw9` | Internal-only application; residual risk accepted by the project owner on 2026-07-22. Expires at Phase 2 lockfile review or when a compatible stable SendPortal Core permits a Laravel upgrade, whichever occurs first. |
| `PKSA-mdq4-51ck-6kdq` | Internal-only application; residual risk accepted by the project owner on 2026-07-22. Expires at Phase 2 lockfile review or when a compatible stable SendPortal Core permits a Laravel upgrade, whichever occurs first. |

The temporary-manifest assertion passed: it found no `config.platform`, no legacy `config.audit.advisories` or `config.audit.ignore`, and no package-wide or severity-wide ignore surfaces.

| Evidence command | Result |
| --- | --- |
| `php /Users/meigire/Library/Application Support/Herd/bin/composer validate --strict --no-check-publish` with the scrubbed environment | Exit 0; `./composer.json is valid` |
| `composer show laravel/framework --all --no-interaction` with the scrubbed environment | Exit 0; latest discovered tagged Laravel 11 was `v11.55.0` |
| `composer update --dry-run --prefer-dist --no-interaction --no-scripts --no-progress` with the scrubbed environment | Exit 0; 144 installs; `laravel/framework (v11.55.0)`; no `11.x-dev` selection |
| `composer prohibits laravel/framework v11.55.0 --tree` with the scrubbed environment | Exit 1 with `No dependencies installed`; expected for this uninstalled, lockfile-free dry-run copy, while the successful solver output is the applicable candidate-selection evidence |
| Repository `composer.lock` / `vendor/` assertions | Both absent after the temporary probe |

## Task Commits

The plan has one evidence-only task. Its repository artifact is captured by the plan metadata commit created after state tracking is updated.

## Files Created/Modified

- `.planning/phases/01-constraint-resolution-and-security-control/01-01-SUMMARY.md` - Reproducible isolated solver evidence and the D-02 policy record.

## Decisions Made

- Used the verified Herd Composer 2.10.2 executable instead of the older PATH Composer 2.9.5; it runs under the actual PHP 8.4.23 runtime and meets the Composer 2.10+ policy requirement.
- Treated the successful dry-run solver result as pre-lockfile evidence only. It neither accepts nor writes a dependency graph; Plan 01-02 remains responsible for applying the reviewed repository policy and performing the isolated installation evidence.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Verification bug] Canonicalized temporary `ignore-id` insertion order**
- **Found during:** Task 1 (Trace the exact D-02 policy through an isolated PHP 8.4 solver)
- **Issue:** The plan's strict PHP array comparison sorts the actual advisory keys but compares against the original insertion order. The three correct IDs therefore failed `===` solely because their source order was not lexical.
- **Fix:** Sorted the three IDs before creating the temporary candidate's `ignore-id` map. This changed no IDs, reasons, repository files, or policy semantics and let the required exact-key assertion verify the intended set.
- **Files modified:** None; the adjustment existed only in the removed temporary candidate.
- **Verification:** The temporary exact-policy assertion passed, followed by successful Composer validation and dry-run resolution.
- **Committed in:** Plan metadata commit.

---

**Total deviations:** 1 auto-fixed (1 Rule 1 verification bug).
**Impact on plan:** The correction only removes an ordering artifact in disposable probe data; all D-01 through D-03 controls remain intact.

## Issues Encountered

- The ordinary PATH Composer was 2.9.5. The verified Herd Composer 2.10.2 binary was used instead, as required for `config.policy` evidence.
- The initial sandbox DNS probe could not resolve Packagist. The authorized network-enabled probe reached Packagist successfully before the solver ran.

## Known Stubs

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Plan 01-02 can apply the exact approved policy to the repository manifest and produce the next-stage isolated installation evidence.
- No dependency graph or lockfile was accepted in this plan; Phase 2 retains responsibility for the reviewed committed lockfile.

## Self-Check: PASSED

- Summary file exists at the declared path.
- The recorded PHP 8.4.23 / Composer 2.10.2 probe, stable Laravel `v11.55.0` result, exact three IDs, and repository non-mutation assertions all passed.

---
*Phase: 01-constraint-resolution-and-security-control*
*Completed: 2026-07-22*
