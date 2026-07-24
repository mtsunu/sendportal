---
phase: 02-reproducible-dependency-snapshot
plan: 01
subsystem: infra
tags: [composer, lockfile, php84, dependency-management, reproducibility, gitignore]

# Dependency graph
requires:
  - phase: 01-constraint-resolution-and-security-control
    provides: "Guarded Composer wrapper (bin/composer-policy), SHA-256-verified Composer 2.10.2 phar, native advisory policy in composer.json, Phase-1-approved PHP 8.4 dependency resolution (candidate composer.lock)"
provides:
  - "Tracked, drift-proven composer.lock (the reproducibility contract for local/CI/deployment installs)"
  - ".gitignore with the composer.lock ignore entry removed"
  - "Proof that the committed lock is the install contract (validate --strict + lock-consuming install)"
affects: [02-02, phase-03-ci, deployment]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Freeze-only lock normalization via guarded `update --lock` (regenerates lock metadata without re-resolving package versions)"
    - "Zero-drift provenance proof: name->version diff between out-of-repo baseline and regenerated lock must be empty"

key-files:
  created:
    - composer.lock
  modified:
    - .gitignore

key-decisions:
  - "Used guarded `update --lock` instead of full guarded `update` to satisfy the freeze-only / zero-drift invariant (a full update re-resolved and bumped aws/aws-sdk-php)."

patterns-established:
  - "Zero-drift gate: name->version set of the regenerated lock must be byte-identical to the preserved Phase-1 candidate before commit."
  - "Guarded normalization: lock provenance is proven through bin/composer-policy only, with no bypass flags."

requirements-completed: [DEPS-01]

coverage:
  - id: D1
    description: "composer.lock is git-tracked (removed from .gitignore, staged and committed)"
    requirement: "DEPS-01"
    verification:
      - kind: automated
        ref: "git check-ignore composer.lock (EXIT 1) && git ls-files composer.lock -> composer.lock"
        status: pass
    human_judgment: false
  - id: D2
    description: "composer.json <-> composer.lock synchronized under strict validation"
    requirement: "DEPS-01"
    verification:
      - kind: automated
        ref: "php bin/composer-policy validate --strict --no-interaction -> './composer.json is valid', EXIT 0"
        status: pass
    human_judgment: false
  - id: D3
    description: "Regenerated lock has zero version drift from the Phase-1 candidate (freeze only)"
    verification:
      - kind: automated
        ref: "diff of '\"name\"|\"version\"' lines (scratch baseline vs composer.lock) empty; content-hash 41abd56c5581800607cc9d3c28862a76"
        status: pass
    human_judgment: false
  - id: D4
    description: "Committed lock is consumed by a guarded install without fresh resolution (DEPS-04 mechanism proof)"
    verification:
      - kind: automated
        ref: "php bin/composer-policy install --prefer-dist --no-interaction -> 'Installing dependencies from lock file', 'Nothing to install, update or remove', EXIT 0"
        status: pass
    human_judgment: false

# Metrics
duration: 3min
completed: 2026-07-24
status: complete
---

# Phase 2 Plan 01: Reproducible Dependency Snapshot Summary

**Froze the Phase-1-approved PHP 8.4 dependency graph into a git-tracked composer.lock, proven drift-free (content-hash 41abd56c5581800607cc9d3c28862a76) and json<->lock synchronized, consumed by a guarded install without re-resolution.**

## Performance

- **Duration:** 3 min
- **Started:** 2026-07-24T19:38:12Z
- **Completed:** 2026-07-24T19:41:41Z
- **Tasks:** 1
- **Files modified:** 2

## Accomplishments
- Removed the `composer.lock` ignore entry from `.gitignore` (D-04) so the reviewed lock is tracked.
- Normalized the Phase-1 candidate lock through the guarded path and proved **zero version drift** — the name->version set is byte-identical to the preserved out-of-repo baseline.
- Proved `composer.json` <-> `composer.lock` synchronization: `validate --strict` EXIT 0 (`./composer.json is valid`).
- Proved the committed lock is the install contract: guarded `install` consumes it with "Installing dependencies from lock file" / "Nothing to install, update or remove" (no fresh resolution), EXIT 0.
- Confirmed content-hash unchanged at `41abd56c5581800607cc9d3c28862a76` (composer.json untouched this plan).

## Task Commits

Each task was committed atomically:

1. **Task 1: Freeze, track, and validate the lockfile end-to-end** - `40466bf` (feat)

## Files Created/Modified
- `composer.lock` - The frozen, drift-proven, git-tracked dependency snapshot (391 KB, now tracked for the first time).
- `.gitignore` - Removed the `composer.lock` ignore line so the reviewed lock can be committed.

## Verification Evidence (captured on PHP 8.4.23 + Composer 2.10.2)

| Check | Command | Result |
|-------|---------|--------|
| Not ignored | `git check-ignore composer.lock` | EXIT 1 (non-zero) |
| Tracked | `git ls-files composer.lock` | `composer.lock` |
| Strict validate | `php bin/composer-policy validate --strict --no-interaction` | `./composer.json is valid`, EXIT 0 |
| Zero drift | `diff` of name/version lines (baseline vs regenerated) | empty (EXIT 0) |
| Content hash | `grep '"content-hash"' composer.lock` | `41abd56c5581800607cc9d3c28862a76` |
| Lock-consuming install | `php bin/composer-policy install --prefer-dist --no-interaction` | `Installing dependencies from lock file` / `Nothing to install, update or remove`, EXIT 0 |

## Decisions Made
- **Guarded `update --lock` over full guarded `update` for freeze-only normalization.** The plan's primary mechanism (D-03, RESEARCH Pattern 2) was `update --prefer-dist`, but on this real run a full guarded `update` re-resolved and upgraded `aws/aws-sdk-php` (3.388.13 -> 3.389.0) — genuine re-resolution drift published between Phase 1 and now. The freeze-only / zero-drift invariant (prohibition line 27, D-03) takes precedence over the exact mechanism. Restoring the preserved Phase-1 candidate and normalizing via the guarded `update --lock` re-writes lock metadata to sync with composer.json **without** re-resolving versions, producing a byte-identical name->version set. Still fully guarded, no bypass flags.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Full guarded `update` re-resolved and drifted; switched to guarded `update --lock`**
- **Found during:** Task 1 (Freeze, track, and validate the lockfile end-to-end)
- **Issue:** `php bin/composer-policy update --prefer-dist --no-interaction` upgraded `aws/aws-sdk-php` (3.388.13 -> 3.389.0), a newer release published since Phase 1. This is re-resolution drift and violates the plan's freeze-only / zero-drift hard gate (prohibition line 27; acceptance criterion "name->version diff empty"). Committing it would propagate an unreviewed version to CI and deployment.
- **Fix:** Restored the preserved out-of-repo Phase-1 candidate lock (aws/aws-sdk-php 3.388.13) and normalized it via the guarded `php bin/composer-policy update --lock --no-interaction`, which re-writes lock metadata to sync with composer.json without re-resolving package versions ("Nothing to modify in lock file"). This freezes the exact Phase-1 graph while still routing through the Phase-1 guard.
- **Verification:** name->version diff (baseline vs regenerated) is empty; content-hash unchanged; `validate --strict` EXIT 0; guarded `install` consumes lock with no re-resolution.
- **Committed in:** `40466bf` (Task 1 commit)
- **Contract compliance:** The guard's command contract stops parsing at the first canonical command and passes trailing flags through; `--lock` is not in the guard's bypass-flag deny list (`--working-dir`, `-d`, `--ignore-platform-req(s)`, `--no-blocking`, `--no-security-blocking`, `--no-audit`), so this remains within the guarded, non-bypass path.

---

**Total deviations:** 1 auto-fixed (1 blocking)
**Impact on plan:** The deviation preserves the plan's stated invariant (freeze only, zero drift) against a real re-resolution hazard. No scope creep; composer.json untouched, no bypass flags, no CI changes.

## Issues Encountered
- None beyond the drift documented above, which was resolved within the guarded path.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- The tracked, drift-proven `composer.lock` is committed and is now the install contract for Plan 02 (advisory refresh + documentation) and downstream Phase 3 CI/deployment work.
- Plan 02 will edit `composer.json` (advisory `ignore-id` reasons/expiry) and the guard's `$rationale` constant in lockstep, then re-capture the three review checks; that edit will change the content-hash by design and must be re-synced via the guarded path.
- No blockers.

## Self-Check: PASSED
- FOUND: composer.lock (tracked, `git ls-files composer.lock` -> composer.lock)
- FOUND: .gitignore (composer.lock entry removed)
- FOUND commit: 40466bf

---
*Phase: 02-reproducible-dependency-snapshot*
*Completed: 2026-07-24*
