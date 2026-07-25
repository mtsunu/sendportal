---
phase: 03-php-8-4-runtime-core-integration-and-ci-verification
verified: 2026-07-25T00:00:00Z
status: human_needed
score: 8/8 must-haves verified
behavior_unverified: 0
overrides_applied: 0
human_verification:
  - test: "Push this branch (or open a PR) so GitHub Actions actually runs the `kirschbaumdevelopment/laravel-test-runner:8.4` matrix job end-to-end, including the live MySQL and PostgreSQL service containers."
    expected: "All nine steps in the `:8.4` job (checkout, Verify Composer policy routes, Verify Composer manifest, Install composer dependencies, Check platform requirements, Audit dependencies, Verify Laravel boot and SendPortal Core route registration, Run Testsuite against MySQL, Run Testsuite against Postgres) complete with exit code 0 in the GitHub-hosted container/network environment, not just in the local dry-run."
    why_human: "The GitHub Actions hosted container image, Docker network topology, and live service-container wiring cannot be reproduced by local grep/dry-run checks or by this verifier; it requires an actual `git push` to trigger `on: push`, which is outside a read-only verification pass."
---

# Phase 3: PHP 8.4 Runtime, Core Integration, and CI Verification — Verification Report

**Phase Goal:** Operators have ongoing evidence that the locked PHP 8.4 application, including its existing SendPortal Core integration, boots and passes both supported database test paths.
**Verified:** 2026-07-25
**Status:** human_needed
**Re-verification:** No — initial verification

## Verdict

**PASS (structurally), pending one expected outstanding item: the live GitHub Actions `:8.4` job has not yet run on a real push.**

Every structural, textual, and locally-executable claim in the PLAN's `must_haves` and SUMMARY.md was independently re-derived from the actual `.github/workflows/ci.yml` file (not trusted from the SUMMARY), the actual guard/contract source files, and live command execution on this machine (PHP 8.4.23 / Composer 2.10.2). No gaps, no stubs, no error-suppression constructs, no scope creep into other files. The only item this verifier cannot close is the one the SUMMARY itself already flags: a live GitHub-hosted `:8.4` CI run has not been observed. That is an expected, correctly-classified outstanding item — not a phase defect — and is routed to human verification below.

## Goal Achievement

### Observable Truths

| # | Truth | Status | Evidence |
|---|-------|--------|----------|
| 1 | Pushing a commit runs a `:8.4` matrix job alongside retained `:8.2`/`:8.3`, every step identical per matrix entry | ✓ VERIFIED | `.github/workflows/ci.yml` lines 7-11: `strategy.matrix.container` contains `"kirschbaumdevelopment/laravel-test-runner:8.2"`, `":8.3"`, `":8.4"` as three literal array entries. Single shared `steps:` block (not conditional per version) means all matrix entries run identical steps — confirmed by reading the full file, no `if: matrix.container == ...` conditionals present anywhere. |
| 2 | Install step runs with Composer scripts enabled, completing `artisan package:discover` via `post-autoload-dump` | ✓ VERIFIED | `Install composer dependencies` step's `run:` value is `php bin/composer-policy install -q --no-ansi --no-interaction --no-progress --prefer-dist` — no `--no-scripts` flag present. Confirmed via direct grep: `grep -q -- '--no-scripts' .github/workflows/ci.yml` finds nothing. `composer.json`'s `scripts.post-autoload-dump` hook (`@php artisan package:discover --ansi`) therefore executes on every install. |
| 3 | `php artisan about` exits 0, proving safe Laravel boot | ✓ VERIFIED | Present as first command in the `Verify Laravel boot and SendPortal Core route registration` step. Independently re-ran locally on real PHP 8.4.23/Laravel 11.55.0: exit 0, full "Environment" table printed correctly. |
| 4 | `php artisan route:list` lists `sendportal.dashboard`, proving Core route-registration integration | ✓ VERIFIED | Present as second command in the same step, piped to `grep -q sendportal.dashboard`. Independently re-ran locally: `route:list --no-ansi` output contains `GET\|HEAD / sendportal.dashboard › Sendportal\Base › DashboardController...` — genuine SendPortal Core route, not fabricated. |
| 5 | Existing MySQL (`DB_CONNECTION=mysql`) and PostgreSQL (`DB_CONNECTION=pgsql`) PHPUnit steps remain present, unmodified, still gate the job | ✓ VERIFIED | `Run Testsuite against MySQL` (`env: DB_CONNECTION: mysql, DB_HOST: mysql`) and `Run Testsuite against Postgres` (`env: DB_CONNECTION: pgsql, DB_HOST: postgres`) both present, both still `vendor/bin/phpunit`, both still last two steps in the job. `mysql:5.7` / `postgres` service blocks (ports, health-checks, credentials) byte-identical to what a pre-phase file would show — no reordering, no removed lines. |
| 6 | Each of the five CI-02 failure surfaces is its own independent CI step, fails the job via GitHub Actions' default fail-closed semantics | ✓ VERIFIED | Five distinct, sequential steps confirmed by direct read of the file: `Verify Composer manifest` (Composer metadata) → `Check platform requirements` (platform reqs) → `Audit dependencies` (dependency audit) → `Verify Laravel boot and SendPortal Core route registration` (Laravel/Core boot) → `Run Testsuite against MySQL` / `Run Testsuite against Postgres` (per-engine PHPUnit). No step has `continue-on-error:`, no `\|\| true`, no `set +e` — grep for all four confirmed zero matches (see Anti-Patterns below). GitHub Actions' documented default (`bash -eo pipefail`, non-zero step exit fails the job) is the only failure-propagation mechanism present. |
| 7 | `check-platform-reqs` runs directly against the vendored `tools/composer/composer-2.10.2.phar`, not through `bin/composer-policy`, and the guard's four-command canonical set is unchanged | ✓ VERIFIED | `Check platform requirements` step's `run:` value is exactly `php tools/composer/composer-2.10.2.phar check-platform-reqs --lock --no-interaction` — direct phar invocation, no `bin/composer-policy` wrapper. Read `tools/composer/ComposerPolicyCommandContract.php` line 14: `private const ALLOWED_COMMANDS = ['validate', 'audit', 'install', 'update'];` — `check-platform-reqs` is genuinely outside this set, and this constant is untouched (see Key Link Verification). Independently re-ran `php tools/composer/composer-2.10.2.phar check-platform-reqs --lock --no-interaction` locally: exit 0, all 26 platform requirements report `success`. |
| 8 | CI trigger remains `on: push`; MySQL/PostgreSQL service image versions unchanged | ✓ VERIFIED | Line 1 of `.github/workflows/ci.yml`: `on: push` — unchanged, no new trigger added. `mysql:5.7` and `postgres` (untagged, matching pre-phase convention) service images confirmed unchanged by direct read. |

**Score:** 8/8 truths verified (0 present-but-behavior-unverified)

### Required Artifacts

| Artifact | Expected | Status | Details |
|----------|----------|--------|---------|
| `.github/workflows/ci.yml` | Extended in place: `:8.4` matrix entry, script-enabled install, four new gate steps, unchanged MySQL/Postgres PHPUnit + `on: push` | ✓ VERIFIED | Read the full 68-line file directly. Exact step order confirmed: checkout → `Verify Composer policy routes` → `Verify Composer manifest` → `Install composer dependencies` → `Check platform requirements` → `Audit dependencies` → `Verify Laravel boot and SendPortal Core route registration` → `Run Testsuite against MySQL` → `Run Testsuite against Postgres`. Matches the task-provided ordering spec exactly. |

### Key Link Verification

| From | To | Via | Status | Details |
|------|----|----|--------|---------|
| `strategy.matrix.container` | every step in job body | shared (non-conditional) `steps:` block | ✓ WIRED | No `if:` conditionals gating any step by matrix version — confirmed by full-file read. |
| Script-enabled `install` step | `artisan package:discover` | `composer.json` `scripts.post-autoload-dump` | ✓ WIRED | `grep -n post-autoload-dump composer.json` → `"post-autoload-dump": ["@php artisan package:discover --ansi"]` (verified present, unmodified — `composer.json` is one of the four files confirmed untouched by this phase's commits). |
| `artisan route:list` output | `Sendportal::webRoutes()` registration in `mettle/sendportal-core` | `sendportal.dashboard` route name | ✓ WIRED | Live local run: `route:list` genuinely emits `sendportal.dashboard` mapped to `Sendportal\Base\DashboardController` — real package route, not a placeholder string match. |
| `check-platform-reqs` / `audit --locked` / `validate --strict` | committed `composer.lock` / `composer.json` | no re-resolution | ✓ WIRED | All three commands verified to consume `--lock`/`--locked`/read committed manifest only — no `update` command anywhere in the file (`grep -c 'bin/composer-policy update' .github/workflows/ci.yml` → 0). |
| `bin/composer-policy`'s four-command canonical set | `check-platform-reqs` routing | direct vendored-phar invocation, bypassing the guard | ✓ WIRED | `ComposerPolicyCommandContract::ALLOWED_COMMANDS = ['validate', 'audit', 'install', 'update']` (line 14) — confirmed by direct file read, and confirmed byte-unchanged since before this phase's commits (`git diff` empty against `f722d24^`). |

### Guard/Contract File Integrity (Prohibition Check)

| File | Expected | Status | Evidence |
|------|----------|--------|----------|
| `bin/composer-policy` | Untouched by phase 3 commits | ✓ VERIFIED | `git diff f722d24^ HEAD -- bin/composer-policy` → empty. |
| `tools/composer/ComposerPolicyCommandContract.php` | Untouched by phase 3 commits | ✓ VERIFIED | Same diff command — empty; `ALLOWED_COMMANDS` confirmed unchanged. |
| `composer.json` | Untouched by phase 3 commits | ✓ VERIFIED | Same diff command — empty; `config.policy` block (`block: true`, `audit: "fail"`, 3 `ignore-id` exceptions) confirmed present and unmodified. |
| `composer.lock` | Untouched by phase 3 commits | ✓ VERIFIED | Same diff command — empty. |
| Commits `f722d24`, `84e1732` | Touch only `.github/workflows/ci.yml` | ✓ VERIFIED | `git show --stat` on both commits: each shows exactly one file changed (`.github/workflows/ci.yml`), no other files. |
| Commit `fd5d3d0` | Docs/planning-only completion commit | ✓ VERIFIED | `git show --stat fd5d3d0`: touches only `.planning/REQUIREMENTS.md`, `.planning/ROADMAP.md`, `.planning/STATE.md`, and the `03-01-SUMMARY.md` — no source or CI files. |

### Anti-Patterns Found

| File | Line | Pattern | Severity | Impact |
|------|------|---------|----------|--------|
| — | — | `continue-on-error`, `\|\| true`, `set +e`, `--ignore-platform-req`, `--no-audit` | n/a | None found — all five prohibited constructs searched for directly with `grep -n -E` against the full file; zero matches (grep exit code 1 in all cases). |
| — | — | `TBD`, `FIXME`, `XXX`, `TODO`, `HACK`, `PLACEHOLDER`, "coming soon", "not yet implemented" | n/a | None found in `.github/workflows/ci.yml`. |

No blockers, no warnings, no debt markers.

### Requirements Coverage

| Requirement | Source Plan | Description | Status | Evidence |
|-------------|------------|-------------|--------|----------|
| RUNTIME-01 | 03-01-PLAN.md | A normal, script-enabled lockfile install completes Laravel package discovery and a safe Laravel boot check on PHP 8.4 | ✓ SATISFIED | Truths #2, #3 above; live local re-run of `php artisan about` confirms exit 0 on real PHP 8.4.23. |
| RUNTIME-02 | 03-01-PLAN.md | The existing PHPUnit suite passes on PHP 8.4 against MySQL | ✓ SATISFIED (structurally) / pending live-CI proof | Truth #5 above — `Run Testsuite against MySQL` step present, unmodified, now executes under the `:8.4` matrix entry. The actual pass/fail on a live `:8.4` GitHub-hosted container has not been observed (see Human Verification). |
| RUNTIME-03 | 03-01-PLAN.md | The existing PHPUnit suite passes on PHP 8.4 against PostgreSQL | ✓ SATISFIED (structurally) / pending live-CI proof | Same as RUNTIME-02, for the Postgres step. |
| RUNTIME-04 | 03-01-PLAN.md | PHP 8.4 validation proves the existing SendPortal Core provider/route-registration integration still boots without product-behavior change | ✓ SATISFIED | Truth #4 above; live local re-run genuinely found `sendportal.dashboard` in `route:list` output — this is Core's real route, not a stub string. |
| CI-01 | 03-01-PLAN.md | CI includes a PHP 8.4 job using the committed lockfile and retains existing PHP 8.2/8.3 coverage | ✓ SATISFIED | Truth #1 above; matrix array contains all three versions; install steps consume `composer.lock` only (`install`, never `update`). |
| CI-02 | 03-01-PLAN.md | The PHP 8.4 CI job fails on invalid Composer metadata, platform requirement failures, dependency audit failures, Laravel boot failures, or PHPUnit failures for either database engine | ✓ SATISFIED | Truth #6 above; five independent steps confirmed, no error-suppression construct found anywhere in the file. |

No orphaned requirements — `.planning/REQUIREMENTS.md` maps exactly RUNTIME-01..04, CI-01, CI-02 to Phase 3, and all six appear in `03-01-PLAN.md`'s `requirements:` frontmatter.

### Behavioral Spot-Checks

| Behavior | Command | Result | Status |
|----------|---------|--------|--------|
| Laravel boots on real PHP 8.4.23 | `php artisan about` | Exit 0; printed Laravel 11.55.0 / PHP 8.4.23 / Composer 2.10.2 environment table | ✓ PASS |
| SendPortal Core route registration intact | `php artisan route:list --no-ansi \| grep -q sendportal.dashboard` | Exit 0; found `GET\|HEAD / sendportal.dashboard › Sendportal\Base › DashboardController` | ✓ PASS |
| Composer manifest validates | `php bin/composer-policy validate --strict --no-interaction` | Exit 0; `./composer.json is valid` | ✓ PASS |
| Platform requirements satisfied on PHP 8.4 | `php tools/composer/composer-2.10.2.phar check-platform-reqs --lock --no-interaction` | Exit 0; all 26 platform requirement rows report `success` | ✓ PASS |
| `--no-scripts` genuinely removed from install step | `grep -q -- '--no-scripts' .github/workflows/ci.yml` (inverted) | No match — confirmed absent | ✓ PASS |
| No error-suppression construct anywhere in file | `grep -n -E 'continue-on-error\|\|\| true\|set \+e\|--ignore-platform-req\|--no-audit' .github/workflows/ci.yml` | No matches | ✓ PASS |

Note: `composer install` (full dependency install) and `composer audit` were **not** independently re-run by this verifier per the read-only constraint on this pass ("do not run composer install or mutate the tree"); the SUMMARY's locally-reported exit-0 results for those two commands were not independently re-executed, but the CI step text, guard routing, and the four-command canonical-set contract that governs them were all independently verified from source.

### Probe Execution

Not applicable — this phase has no `scripts/*/tests/probe-*.sh` files and none are referenced in the PLAN/SUMMARY. Skipped.

## Deferred / Known Outstanding Item

**The actual `kirschbaumdevelopment/laravel-test-runner:8.4` GitHub Actions job has not yet been observed running on GitHub's hosted infrastructure.** All verification above (both by the executor per SUMMARY.md and independently re-confirmed by this verifier) was performed via local command execution against the real committed lockfile and a local PHP 8.4.23 install — not the actual GitHub-hosted container, Docker network, and live MySQL/PostgreSQL service-container wiring that `on: push` will trigger.

This is correctly classified as an **expected outstanding real-CI proof, not a phase defect**:
- The file-level change is complete, structurally correct, and matches every `must_haves` truth.
- No further code change is required to close this out — only observing a live push-triggered run.
- The SUMMARY.md itself explicitly and honestly flags this same gap (see "Outstanding Real-CI Proof" section), which is good practice, not evidence-hiding.

This routes to Human Verification below (per the decision tree: an unresolved, environment-dependent item routes the phase to `human_needed`, not `gaps_found` — nothing failed, nothing is missing from the codebase).

## Human Verification Required

### 1. Live GitHub Actions `:8.4` run

**Test:** Push this branch (or open a PR against the default branch) to trigger `on: push`, then observe the `kirschbaumdevelopment/laravel-test-runner:8.4` job in the GitHub Actions run.
**Expected:** All nine steps (checkout, Verify Composer policy routes, Verify Composer manifest, Install composer dependencies, Check platform requirements, Audit dependencies, Verify Laravel boot and SendPortal Core route registration, Run Testsuite against MySQL, Run Testsuite against Postgres) report green/exit 0, including both PHPUnit runs against the live MySQL and PostgreSQL service containers.
**Why human:** GitHub's hosted container image, Docker networking, and live service-container behavior cannot be reproduced by static analysis, grep, or a local dry-run — this requires an actual push and observation of the Actions run, which is outside a read-only verification pass.

## Gaps Summary

No structural, wiring, or scope-creep gaps found. Every `must_haves` truth, artifact, key link, and prohibition in `03-01-PLAN.md` frontmatter is genuinely present in `.github/workflows/ci.yml`, in the correct order, with no error-suppression, no stub, and no scope creep into `bin/composer-policy`, `tools/composer/ComposerPolicyCommandContract.php`, `composer.json`, or `composer.lock`. The single open item — a live GitHub Actions `:8.4` run — is an expected, correctly-self-reported outstanding proof that requires a push, not a code fix, and is why overall status is `human_needed` rather than `passed`.

---

_Verified: 2026-07-25_
_Verifier: Claude (gsd-verifier)_
