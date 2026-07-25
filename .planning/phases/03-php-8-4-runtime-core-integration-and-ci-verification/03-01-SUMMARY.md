---
phase: 03-php-8-4-runtime-core-integration-and-ci-verification
plan: 01
subsystem: infra
tags: [github-actions, composer, ci, php-8.4, laravel, sendportal-core]

requires:
  - phase: 02-composer-dependency-graph-and-lockfile
    provides: "Locked, checksum-verified composer.lock and the bin/composer-policy fail-closed guard wrapper with a four-command canonical set (validate/audit/install/update) and native config.policy vulnerability-ignore block"
provides:
  - "A permanent :8.4 CI matrix job alongside the existing :8.2/:8.3 jobs, with script-enabled install and five independently-attributable failure gates"
affects: [ci, deployment, php-8.4-runtime]

tech-stack:
  added: []
  patterns:
    - "Guard-routed vs direct-vendored-phar Composer invocation split: mutating/audited commands (validate, install, audit) go through bin/composer-policy; the read-only check-platform-reqs diagnostic calls the vendored tools/composer/composer-2.10.2.phar directly to avoid both the guard's narrower canonical set and the container's unpinned system Composer."
    - "CI failure attribution via GitHub Actions' default fail-closed step/pipefail semantics only — no continue-on-error, no || true, no set +e anywhere in the workflow."

key-files:
  created: []
  modified:
    - ".github/workflows/ci.yml - added :8.4 matrix entry; removed --no-scripts from the install step; added Verify Composer manifest, Check platform requirements, Audit dependencies, and Verify Laravel boot and SendPortal Core route registration steps"

key-decisions:
  - "check-platform-reqs is invoked directly against tools/composer/composer-2.10.2.phar rather than through bin/composer-policy, since it sits outside the guard's four-command canonical set and would otherwise be rejected; this also avoids the CI container's unpinned system Composer (RESEARCH.md Pitfall 2)."
  - "The boot/Core-route proof is a single combined step (php artisan about; route:list | grep sendportal.dashboard) rather than two separate steps, per 03-CONTEXT.md D-05 Claude's Discretion — no env: block or .env/APP_KEY provisioning needed since none of the three commands (package:discover, about, route:list) resolve the encrypter."

requirements-completed: [RUNTIME-01, RUNTIME-02, RUNTIME-03, RUNTIME-04, CI-01, CI-02]

coverage:
  - id: D1
    description: "kirschbaumdevelopment/laravel-test-runner:8.4 added to the CI matrix alongside :8.2/:8.3 (CI-01)"
    requirement: "CI-01"
    verification:
      - kind: other
        ref: "grep -n 'laravel-test-runner:8.4' .github/workflows/ci.yml (local dry-run)"
        status: pass
    human_judgment: true
    rationale: "Local dry-run proves every command in the job body succeeds on real PHP 8.4.23; it does not prove the actual GitHub-hosted :8.4 container/service topology runs green until the workflow executes on GitHub Actions."
  - id: D2
    description: "Install step is script-enabled (--no-scripts removed), so Composer's post-autoload-dump hook runs artisan package:discover during install (RUNTIME-01)"
    requirement: "RUNTIME-01"
    verification:
      - kind: other
        ref: "php bin/composer-policy install -q --no-ansi --no-interaction --no-progress --prefer-dist (local dry-run, exit 0)"
        status: pass
    human_judgment: false
  - id: D3
    description: "php artisan about exits 0, proving a safe Laravel boot on PHP 8.4 (RUNTIME-01)"
    requirement: "RUNTIME-01"
    verification:
      - kind: other
        ref: "php artisan about (local dry-run, exit 0)"
        status: pass
    human_judgment: false
  - id: D4
    description: "php artisan route:list lists sendportal.dashboard, proving SendPortal Core's route-registration integration boots unchanged (RUNTIME-04)"
    requirement: "RUNTIME-04"
    verification:
      - kind: other
        ref: "php artisan route:list --no-ansi | grep -q sendportal.dashboard (local dry-run, exit 0)"
        status: pass
    human_judgment: false
  - id: D5
    description: "Existing MySQL (DB_CONNECTION=mysql) and PostgreSQL (DB_CONNECTION=pgsql) PHPUnit steps remain present and unmodified, still gating the job (RUNTIME-02, RUNTIME-03)"
    requirement: "RUNTIME-02"
    verification:
      - kind: other
        ref: "git diff --quiet HEAD -- .github/workflows/ci.yml scoped review: both PHPUnit steps' env: blocks byte-identical to pre-Task-1 file"
        status: pass
    human_judgment: false
  - id: D6
    description: "Five CI-02 failure surfaces (Composer metadata, platform requirements, dependency audit, Laravel/Core boot, per-engine PHPUnit) are each independent CI steps failing the job via GitHub Actions' default fail-closed semantics"
    requirement: "CI-02"
    verification:
      - kind: other
        ref: "awk structural order check (ORDER_OK) + prohibition grep (PROHIBITIONS_OK) + guard-untouched git diff (GUARD_UNTOUCHED), all in this SUMMARY"
        status: pass
    human_judgment: true
    rationale: "Local checks prove structure, ordering, and absence of bypass/suppression syntax; only the live GitHub Actions run can prove GitHub's actual step/pipefail behavior fails the job on each surface in the real hosted environment."

duration: ~10min
completed: 2026-07-25
status: complete
---

# Phase 3 Plan 1: PHP 8.4 CI Matrix + Boot/Core-Route Verification Summary

**Extended `.github/workflows/ci.yml` with a `:8.4` matrix job, script-enabled install, and five independently-attributable CI-02 gate steps (manifest validation, platform requirements, dependency audit, Laravel/SendPortal-Core boot proof) — all verified locally against the real committed lockfile and real PHP 8.4.23.**

## Performance

- **Duration:** ~10 min
- **Completed:** 2026-07-25
- **Tasks:** 2
- **Files modified:** 1 (`.github/workflows/ci.yml`)

## Accomplishments

- Added `"kirschbaumdevelopment/laravel-test-runner:8.4"` to `strategy.matrix.container`, retaining `:8.2` and `:8.3`.
- Removed `--no-scripts` from the `Install composer dependencies` step so Composer's `post-autoload-dump` hook runs `artisan package:discover --ansi` during install.
- Added `Verify Laravel boot and SendPortal Core route registration` step (`php artisan about` then `php artisan route:list --no-ansi | grep -q sendportal.dashboard`), proving both a safe Laravel boot and that SendPortal Core's route registration is intact on PHP 8.4.
- Added `Verify Composer manifest` (`php bin/composer-policy validate --strict --no-interaction`), routed through the guard's canonical command set.
- Added `Check platform requirements` (`php tools/composer/composer-2.10.2.phar check-platform-reqs --lock --no-interaction`), calling the vendored checksum-verified phar directly since `check-platform-reqs` is outside the guard's four-command canonical set.
- Added `Audit dependencies` (`php bin/composer-policy audit --locked --no-interaction`), enforcing the already-committed `config.policy` block (`block: true`, `audit: "fail"`, three time-bounded ignore-id exceptions) as a permanent CI gate.
- Final step order confirmed: checkout → `Verify Composer policy routes` → `Verify Composer manifest` → `Install composer dependencies` → `Check platform requirements` → `Audit dependencies` → `Verify Laravel boot and SendPortal Core route registration` → `Run Testsuite against MySQL` → `Run Testsuite against Postgres`.

## Task Commits

Each task was committed atomically:

1. **Task 1: PHP 8.4 matrix + script-enabled install + boot/Core-route proof** - `f722d24` (feat)
2. **Task 2: Composer metadata/platform/audit gates + final assembly check** - `84e1732` (feat)

**Plan metadata:** committed separately per `<final_commit>` protocol.

## Files Created/Modified

- `.github/workflows/ci.yml` - extended in place: `:8.4` matrix entry, script-enabled install step, four new gate steps (`Verify Composer manifest`, `Check platform requirements`, `Audit dependencies`, `Verify Laravel boot and SendPortal Core route registration`); MySQL/PostgreSQL service blocks, both PHPUnit steps, and the `on: push` trigger left byte-unchanged.

## Decisions Made

- `check-platform-reqs` calls `tools/composer/composer-2.10.2.phar` directly (not through `bin/composer-policy`) because it sits outside the guard's canonical command set (`validate`/`audit`/`install`/`update`) and would be rejected if routed through it; this also avoids the CI container's unpinned system Composer binary (RESEARCH.md Pitfall 2).
- Boot and Core-route verification is one combined step rather than two, per 03-CONTEXT.md D-05's discretion grant, since both commands are cheap, read-only, and logically one "does the app still boot and register routes" proof.
- No `.env` file or `APP_KEY` provisioning was added to the new boot/route step — verified locally that `package:discover`, `php artisan about`, and `php artisan route:list` all succeed with `config('app.key')` resolving to `NULL`, since none of the three resolve the encrypter (RESEARCH.md Pitfall 1).

## Deviations from Plan

None - plan executed exactly as written. Both tasks (`tdd="false"`) required no RED test commit per the TDD-mode-off exemption for config-only, non-behavior-adding CI edits; `is_behavior_adding=false` for this plan (no `<behavior>` block, no source files modified — only `.github/workflows/ci.yml`).

## Local Verification Results

All commands run on real PHP 8.4.23 + Composer 2.10.2 (vendored phar `tools/composer/composer-2.10.2.phar`), exact plan `<verify>` sequences:

**Task 1 tracer verify:**
```
git ls-files composer.lock            -> composer.lock (tracked)
grep 'laravel-test-runner:8.4'        -> found (line 11)
! grep -- '--no-scripts'              -> absent (removed)
php bin/composer-policy install ...   -> exit 0
php artisan about                     -> exit 0
php artisan route:list | grep sendportal.dashboard -> exit 0
=> TRACER_OK
```

**Task 2 full dry-run (all six commands, in workflow order):**
| # | Command | Exit code |
|---|---------|-----------|
| 1 | `php bin/composer-policy validate --strict --no-interaction` | 0 |
| 2 | `php bin/composer-policy install -q --no-ansi --no-interaction --no-progress --prefer-dist` | 0 |
| 3 | `php tools/composer/composer-2.10.2.phar check-platform-reqs --lock --no-interaction` | 0 (all platform requirements satisfied on PHP 8.4.23) |
| 4 | `php bin/composer-policy audit --locked --no-interaction` | 0 (3 pre-approved ignore-id advisories reported, none blocking per committed `config.policy`) |
| 5 | `php artisan about` | 0 |
| 6 | `php artisan route:list --no-ansi \| grep -q sendportal.dashboard` | 0 |

=> `FULL_DRY_RUN_OK`

**Structural order check (awk):** `ORDER_OK` — `Verify Composer policy routes` < `Verify Composer manifest` < `Install composer dependencies` < `Check platform requirements` < `Audit dependencies` < `Verify Laravel boot and SendPortal Core route registration` < `Run Testsuite against MySQL`.

**Prohibition check:** `PROHIBITIONS_OK` — no `--ignore-platform-req`, no `--no-audit`, no `continue-on-error`, no `|| true`, no `set +e` anywhere in `.github/workflows/ci.yml`.

**Guard-untouched check:** `GUARD_UNTOUCHED` — `git diff --quiet HEAD -- bin/composer-policy tools/composer/ComposerPolicyCommandContract.php composer.json composer.lock` returned clean; none of the four guarded/reference files were modified by this plan.

## Issues Encountered

None.

## Outstanding Real-CI Proof

Local reproduction covers every command's correctness and the file's structure, but the actual `kirschbaumdevelopment/laravel-test-runner:8.4` **GitHub Actions** job — with its own container-provided PHP/extension baseline, Docker network, and live MySQL/PostgreSQL service containers — has not yet been observed running. Per the plan's `<verification>` section, the first live `:8.4` job run on GitHub Actions (green, including both PHPUnit steps against the real service containers) is the outstanding proof required before `/gsd-verify-work` fully closes out RUNTIME-01..04/CI-01/CI-02. This requires a push to the repository's default branch (or a PR) to trigger `on: push`.

## User Setup Required

None - no external service configuration required. The workflow change takes effect automatically on the next push; no secrets, environment variables, or dashboard configuration are needed.

## Next Phase Readiness

- `.github/workflows/ci.yml` now runs `:8.2`/`:8.3`/`:8.4` matrix jobs with script-enabled install and five independently-attributable fail-closed gates.
- All requirements for this plan (RUNTIME-01..04, CI-01, CI-02) are locally proven; final closure is gated on observing the live `:8.4` GitHub Actions run go green (see Outstanding Real-CI Proof above).
- No blockers for closing out Phase 3 beyond that live-CI observation, which is outside this executor's ability to trigger without a push.

---
*Phase: 03-php-8-4-runtime-core-integration-and-ci-verification*
*Completed: 2026-07-25*

## Self-Check: PASSED

- FOUND: `.github/workflows/ci.yml`
- FOUND: `.planning/phases/03-php-8-4-runtime-core-integration-and-ci-verification/03-01-SUMMARY.md`
- FOUND: commit `f722d24`
- FOUND: commit `84e1732`
