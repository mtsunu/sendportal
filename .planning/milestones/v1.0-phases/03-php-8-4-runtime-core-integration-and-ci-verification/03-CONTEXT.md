# Phase 3: PHP 8.4 Runtime, Core Integration, and CI Verification - Context

**Gathered:** 2026-07-25
**Status:** Ready for planning

<domain>
## Phase Boundary

Prove and continuously verify (in CI) that the exact committed PHP 8.4 dependency graph boots and passes both supported database test paths, and that the unchanged SendPortal Core provider/route-registration integration still boots — without changing any product behavior. This phase delivers: a script-enabled lockfile install + safe Laravel boot check on PHP 8.4 (RUNTIME-01), the existing PHPUnit suite passing on PHP 8.4 against MySQL and PostgreSQL (RUNTIME-02, RUNTIME-03), a Core provider/route boot proof (RUNTIME-04), a PHP 8.4 CI job that retains 8.2/8.3 coverage and consumes the committed lockfile (CI-01), and a CI path that fails on any of the five failure surfaces — Composer metadata, platform requirements, dependency audit, Laravel/Core boot, or either database-engine PHPUnit run (CI-02).

It does NOT: upgrade Laravel or re-resolve/upgrade the locked graph; add product features, tests of product behavior, or the v2 Core-behavior smoke test (HARD-02); change the guarded Composer command contract (`validate/audit/install/update`); or modify database engine versions. It builds directly on Phase 1 (guard, native advisory policy, PHP ^8.2 contract) and Phase 2 (committed `composer.lock`, install-consumes-lock contract, one-time review evidence for validate/platform/audit) — Phase 3 turns those one-time checks into permanent CI gates and adds the 8.4 runtime proof.

</domain>

<decisions>
## Implementation Decisions

The user delegated all decisions ("Delegate all" — follow the best minimal recommendation, consistent with Phases 1–2's honest/minimal/reproducible ethos). Each decision below is locked to that direction and to the Phase 1–2 carry-forward.

### PHP 8.4 CI job shape (CI-01)
- **D-01:** Add `"kirschbaumdevelopment/laravel-test-runner:8.4"` to the existing `ci.yml` matrix `container` list alongside the current `:8.2` and `:8.3` entries. The `:8.4` tag is confirmed to exist on the same image family already in use (Docker Hub `kirschbaumdevelopment/laravel-test-runner`, tags include 8.2/8.3/8.4/8.5). This is the minimal way to add a real PHP 8.4 job while retaining 8.2/8.3 — no `setup-php` divergence, no separate job definition. — **Reversibility:** reversible — a one-line matrix addition.

### Gate coverage — apply new gates uniformly, not 8.4-only (CI-01/CI-02)
- **D-02:** Add the new gate steps (Composer metadata validate, platform-reqs, dependency audit, Laravel/Core boot check) to the shared matrix steps so they run on **every** matrix version (8.2/8.3/8.4), not conditionally on 8.4 only. Rationale: a single matrix job definition with shared steps is simpler than bifurcating with `if: matrix.container == …` conditionals, AND it is strictly stronger — it satisfies CI-02's literal 8.4 requirement while also gating 8.2/8.3. It does not reduce existing coverage (CI-01's "retain 8.2/8.3"); it strengthens it. — **Reversibility:** reversible — CI step edits, cheap to scope down later.

### Failure surfacing — five independent, blocking CI steps (CI-02)
- **D-03:** Wire each of CI-02's five failure surfaces as its own CI step so a failure is independently attributable and fails the job (default GitHub Actions step-failure semantics):
  1. **Composer metadata** → `composer validate --strict` routed through `bin/composer-policy` (guarded route — `validate` is in the canonical set).
  2. **Platform requirements** → `composer check-platform-reqs --lock` run as a **direct** read-only Composer call. `check-platform-reqs` is NOT in the guard's canonical command set (`validate/audit/install/update`, confirmed at `tests/Composer/ComposerPolicyGuardTest.php:1375–1378`); it is a non-mutating diagnostic, so a direct call is acceptable and the guard contract stays unchanged. Do NOT add it to the guard.
  3. **Dependency audit** → `bin/composer-policy audit --locked` (guarded route; fail-closed on any un-ignored advisory, honoring the Phase-1/2 native policy and the three time-bounded `ignore-id` exceptions).
  4. **Laravel/Core boot** → see D-04/D-05.
  5. **Per-engine PHPUnit** → the existing separate MySQL and Postgres `vendor/bin/phpunit` steps (already independent in `ci.yml`).
- Keep the existing "Verify Composer policy routes" self-test step (`ComposerPolicyGuardTest.php` + `--route-audit`).

### Script-enabled install & package discovery (RUNTIME-01)
- **D-04:** For the CI install step, **drop `--no-scripts`** so Composer runs the `post-autoload-dump` script (`@php artisan package:discover --ansi`, confirmed in `composer.json` scripts). This is the faithful reading of RUNTIME-01 ("a script-enabled install completes Laravel package discovery") — it exercises real package discovery / SendPortal Core provider registration during install rather than bolting on a separate artisan call. Keep `--prefer-dist --no-interaction --no-progress` etc. and keep consuming the committed lockfile (`install`, not `update`). — **Reversibility:** reversible — a flag change on one step.
  - **Known integration wrinkle for the planner:** running `artisan` during install requires a bootable environment. `phpunit.xml.dist` hardcodes `APP_KEY` for the test suite, but a bare `install` in an existing checkout does NOT run `post-root-package-install`/`key:generate` (those are root-install / create-project scripts). The 8.4 boot/artisan steps therefore need explicit env setup in CI (e.g. `cp .env.example .env && php artisan key:generate`, or an inline `APP_KEY`). Verify `package:discover` succeeds under the test-runner image's default env; adjust env provisioning as needed. This is an execution detail, not a scope change.

### Boot + Core integration proof — artisan CLI in CI, no product/test code change (RUNTIME-01, RUNTIME-04)
- **D-05:** Prove boot and Core route registration with **read-only artisan CLI checks run as CI steps**, adding NO product code and NO new PHPUnit test:
  - **Safe boot check (RUNTIME-01):** `php artisan about` (boots the full framework + all service providers, read-only, no side effects) — a non-zero exit fails the job. This is the "safe boot check" a script-enabled install feeds into.
  - **Core provider + route-registration proof (RUNTIME-04):** `php artisan route:list` and assert that SendPortal Core routes are present (grep for a known sendportal route/name registered via `Sendportal::webRoutes()`/`apiRoutes()`), proving the host↔Core route delegation boots without asserting any product behavior.
  - Rationale for CLI-over-test: the existing PHPUnit suite (RUNTIME-02/03) already exercises boot heavily on every run; a durable `about` + `route:list` gate is the minimal addition and keeps "no product-behavior change." The v2 Core-behavior smoke test (HARD-02) is explicitly deferred and must not be pulled in here. — **Reversibility:** reversible — CI steps only.

### Database matrix unchanged (RUNTIME-02, RUNTIME-03)
- **D-06:** Keep the existing `mysql:5.7` and `postgres` service containers and the two separate PHPUnit steps (`DB_CONNECTION=mysql` / `pgsql`) exactly as they are — they already satisfy RUNTIME-02/03 and CI-02's per-engine requirement once they run under the new 8.4 matrix entry. Do NOT change database engine versions (out of scope; preserve existing behavior). — **Reversibility:** reversible — no change is the default.

### CI trigger unchanged
- **D-07:** Keep `on: push` as the CI trigger. No new triggers or scheduling — outside this phase's scope.

### Claude's Discretion
- Exact ordering of the gate steps within the job, exact flag strings, and whether validate/platform/audit run before or after install (natural order: guard-route self-test → validate → install (script-enabled) → check-platform-reqs → audit → boot check → route proof → MySQL PHPUnit → Postgres PHPUnit).
- Exact env-provisioning mechanism for the artisan boot/route steps (`.env` copy + `key:generate` vs inline `APP_KEY`), chosen to work under the test-runner image.
- The exact known sendportal route/name asserted in the `route:list` proof, and the assertion mechanism (grep exit code vs `--json` parse).
- Whether the boot/route checks are separate steps or a single combined "Boot & Core integration" step, provided each failure surface still fails the job.
- Where any per-run evidence summary is recorded (the HARD-01 evidence summary is v2/deferred — do not add it here).

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Milestone contract
- `.planning/ROADMAP.md` — Phase 3 goal, requirements (RUNTIME-01…04, CI-01, CI-02), dependency on Phase 2, and the five success criteria (esp. criterion 5: the five CI failure surfaces).
- `.planning/REQUIREMENTS.md` — RUNTIME-01…04, CI-01, CI-02 exact wording; the non-bypass safeguards; and the v2/out-of-scope items (HARD-01 evidence summary, HARD-02 Core smoke test) that must NOT be pulled forward.
- `.planning/PROJECT.md` — runtime target (PHP 8.4 supported install), dependency-safety constraint (no disabling platform/audit), preserve Laravel 11/Core integration, reproducibility (install the committed lock).
- `.planning/STATE.md` — accumulated Phase 1–2 decisions the CI wiring must honor (guard wrapper, native policy, Composer floor, lockfile provenance/content-hash).

### Phase 1–2 carry-forward (MUST honor)
- `.planning/phases/01-constraint-resolution-and-security-control/01-CONTEXT.md` — PHP 8.2–8.4 contract (8.4 primary), Composer-native advisory policy, guard design, preserve Laravel 11 / SendPortal Core.
- `.planning/phases/02-reproducible-dependency-snapshot/02-CONTEXT.md` — committed lockfile as the install contract; the one-time review checks (validate --strict / check-platform-reqs / audit --locked) that Phase 3 now makes permanent CI gates; explicit statement that the PHP 8.4 CI job + permanent gating belong to Phase 3.
- `.planning/research/SUMMARY.md` — real PHP 8.4 resolution findings and pitfalls (if present).

### Files this phase touches or verifies
- `.github/workflows/ci.yml` — the primary edit target: add `:8.4` to the matrix (D-01), add the four new gate steps uniformly (D-02/D-03), make the install step script-enabled (D-04), add the boot/route-proof steps (D-05); keep MySQL/Postgres services + the two PHPUnit steps (D-06) and `on: push` (D-07).
- `composer.json` — `scripts.post-autoload-dump` runs `@php artisan package:discover --ansi` (enables D-04); `config.policy` advisory block (audit gate context); `require.php = ^8.2`. Read-only reference — no constraint changes in this phase.
- `composer.lock` — the committed snapshot the 8.4 job must install and check (`validate --strict`, `check-platform-reqs --lock`, `audit --locked`). Do not regenerate/re-resolve.
- `bin/composer-policy` — the guarded wrapper; validate/audit/install route through it, `check-platform-reqs` does NOT (not in its canonical set — `tests/Composer/ComposerPolicyGuardTest.php:1375–1378`). Do not modify the guard contract.
- `phpunit.xml.dist` — hardcoded `APP_KEY` + testing env used by the PHPUnit steps; relevant to the artisan boot-step env wrinkle (D-04 note). Read-only reference.
- `tests/Composer/ComposerPolicyGuardTest.php`, `tests/Composer/ComposerPolicyLivePackagistTest.php` — the existing CI self-test steps that must stay wired; also the authoritative source for the guard's allowed-command set.
- `README.md` §"Dependency management" — install-contract docs from Phase 2; touch only if CI changes require a documentation sync (minimal).

No external (third-party) specifications were referenced. Use current official docs for `composer validate --strict`, `composer check-platform-reqs`, `composer audit --locked`, and `artisan about` / `route:list` semantics.

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `.github/workflows/ci.yml`: already a container-based matrix (`kirschbaumdevelopment/laravel-test-runner:8.2`/`:8.3`) with MySQL 5.7 + Postgres services, a guard-route self-test step, a guarded `install --no-scripts` step, and separate MySQL/Postgres PHPUnit steps. Extend this file in place — add `:8.4`, add gate steps, flip the install to script-enabled. Don't rewrite.
- `bin/composer-policy`: the Phase-1 guarded wrapper (fail-closed; canonical commands `validate/audit/install/update`). Reuse for validate/audit/install gates.
- `composer.json` `scripts.post-autoload-dump` → `artisan package:discover`: reused for RUNTIME-01 by dropping `--no-scripts` (D-04).
- `phpunit.xml.dist`: provides the testing DB/APP_KEY env the existing PHPUnit steps rely on.

### Established Patterns
- Native Composer advisory policy lives in `composer.json` `config.policy`; the audit gate is `audit --locked` through the guard (no `--no-audit`/bypass flags — the guard rejects them).
- Install consumes the committed lockfile (`install`, never `update`) on every path — the 8.4 job must keep this.
- Host/Core boundary is fixed (`mettle/sendportal-core ^3.0`, Laravel `^11.0`); Phase 3 verifies boot/integration, it does not modify product code or routes.
- Read-only diagnostics (`check-platform-reqs`, `artisan about`/`route:list`) run directly; only mutating/audit commands are guard-routed.

### Integration Points
- `ci.yml` matrix `container` list ↔ the PHP version under test (add `:8.4`).
- `composer.lock` ↔ the three gate commands (validate/platform/audit) run against it in CI.
- Composer `post-autoload-dump` ↔ `artisan package:discover` ↔ SendPortal Core provider discovery (the RUNTIME-01/04 boot chain).
- `artisan route:list` ↔ `Sendportal::webRoutes()`/`apiRoutes()` registration (RUNTIME-04 proof).
- Artisan CLI steps ↔ CI env provisioning (`.env`/`APP_KEY`) — the one real wiring wrinkle to solve during execution.

</code_context>

<specifics>
## Specific Ideas

The user delegated all four presented gray areas to the recommended minimal path (consistent with Phase 2). The owner-relevant emphasis: honor the five distinct CI failure surfaces of CI-02 as independently attributable steps, and read RUNTIME-01 faithfully — a genuinely script-enabled install that runs package discovery (drop `--no-scripts`), not a cosmetic separate artisan call. Prefer artisan CLI boot/route proofs over new product/test code so "no product-behavior change" is preserved and the deferred v2 Core smoke test (HARD-02) is not pulled forward.

</specifics>

<deferred>
## Deferred Ideas

- **HARD-01 — concise dependency-upgrade evidence summary in CI** (PHP/Composer versions, audit result, DB-matrix outcomes): v2. Do not add an evidence-summary artifact in this phase.
- **HARD-02 — tenant-safe SendPortal Core behavior smoke test** (one representative package flow under PHP 8.4): v2. Phase 3's Core proof is boot/route-registration only, not behavior.
- **HARD-03 — static analysis + application coverage-config repair**: separate quality milestone.
- **Laravel major-version / security modernization**: separate milestone (already deferred in PROJECT.md / STATE.md).
- **Changing database engine versions (MySQL 5.7 / Postgres) or CI triggers**: not in scope — preserve existing behavior.

None outside these — discussion stayed within phase scope.

</deferred>

---

*Phase: 3-PHP 8.4 Runtime, Core Integration, and CI Verification*
*Context gathered: 2026-07-25*
