# Phase 3: PHP 8.4 Runtime, Core Integration, and CI Verification - Research

**Researched:** 2026-07-25
**Domain:** CI wiring for a PHP 8.4 runtime/boot proof on an existing container-matrix GitHub Actions workflow (Composer gates + Laravel/SendPortal Core boot proof)
**Confidence:** HIGH

## Summary

All seven CONTEXT.md decisions (D-01..D-07) are locked; this research does not revisit them. Its job was to de-risk the five execution unknowns the planner must not get wrong. The single highest-value finding: **the D-04 "known integration wrinkle" is not actually a wrinkle for the steps this phase adds.** Running `composer install` with scripts enabled (`package:discover`), `php artisan about`, and `php artisan route:list` were all executed live in this repository against the exact committed `composer.lock`, real PHP 8.4.23, and Composer 2.10.2 — with **no `.env` file and no `APP_KEY` set at all** — and every one of them exited `0`. Laravel's `MissingAppKeyException` is only thrown when the encrypter is actually *resolved* (e.g., by code that calls `encrypt()`/`Crypt::`), not merely by booting the framework or listing routes. None of `package:discover`, `about`, or `route:list` resolve the encrypter. This means the planner can add the RUNTIME-01/04 proof steps with **zero new env-provisioning steps** — no `.env` copy, no `key:generate`, no inline `APP_KEY` — unless the planner independently decides one is wanted for other reasons (e.g., consistency with the PHPUnit steps' env block, which already carries `APP_KEY` via `phpunit.xml.dist` and is unaffected).

Second finding: `composer check-platform-reqs --lock` was run locally against the committed lock under real PHP 8.4.23 and returned all-`success` — including for `mettle/sendportal-core`, whose own `composer.json` declares `"php": "^8.2|^8.3"`. This is not a mismatch: Composer's `|`-separated caret ranges are a **union**, not an intersection — `^8.2|^8.3` resolves to `>=8.2.0 <8.3.0 OR >=8.3.0 <9.0.0`, i.e. effectively `>=8.2.0 <9.0.0`, which includes 8.4.x. The planner does not need to worry that the package's own constraint string looks 8.4-unaware; the effective constraint already permits 8.4, and this was confirmed by direct execution, not by parsing.

Third finding: `composer validate --strict` and `composer audit --locked` (both direct and through `bin/composer-policy`) were run locally against the current `composer.json`/`composer.lock` and both pass cleanly (audit: the three time-bounded `ignore-id` PKSA advisories print with rationale and exit `0`; nothing new or un-ignored is present).

Fourth, a concrete, minimal, top-of-file SendPortal Core route was located for the RUNTIME-04 proof: `sendportal.dashboard` (`GET /`, registered first in `Sendportal\Base\Routes\WebRoutes::sendportalWebRoutes()`). `php artisan route:list --no-ansi | grep -q sendportal.dashboard` was verified to find it (confirmed live), and it appears in `route:list` output regardless of the `auth`/`RequireWorkspace` middleware wrapping it (route registration is listed at boot time; middleware only gates request handling, not `route:list` visibility) — so no authenticated session or DB connection is needed for this proof.

Fifth, GitHub Actions' default shell for Linux `run:` steps is `bash --noprofile --norc -eo pipefail {0}` (confirmed against official GitHub docs) — `pipefail` is **on by default**. A plain `php artisan route:list --no-ansi | grep -q sendportal.dashboard` step, with no `|| true` and no explicit shell override, will correctly fail the job if either `artisan` crashes or `grep` finds no match. The planner does not need to add any extra pipefail wiring; it only needs to avoid explicitly disabling it or appending `|| true`.

**Primary recommendation:** Implement D-01..D-07 exactly as locked; add the RUNTIME-01/04 boot/route-proof steps with **no env-provisioning step** (verified unnecessary); assert the Core route via a plain `grep -q sendportal.dashboard` pipeline (verified to work, and safe under GitHub Actions' default `pipefail`); run `check-platform-reqs --lock` directly (not through the guard, confirmed correct per the guard's own test-authoritative command list) after the script-enabled install.

## Architectural Responsibility Map

| Capability | Primary Tier | Secondary Tier | Rationale |
|------------|-------------|----------------|-----------|
| PHP 8.4 matrix job provisioning | CI/Infra (GitHub Actions container matrix) | — | Adding `:8.4` to `ci.yml`'s `container` list is a CI configuration change, not application code |
| Composer metadata/platform/audit gates | CI/Infra | Dependency management (Composer, `bin/composer-policy`) | Read-only/guarded Composer commands run as CI steps against the committed lock; owned by the Phase 1/2 guard contract, invoked here as permanent gates |
| Script-enabled install & package discovery | CI/Infra | Backend/Framework (Laravel service-provider discovery) | Composer's `post-autoload-dump` hook invokes `artisan package:discover`, which is a framework-boot-time operation but is triggered by the CI install step |
| Laravel boot proof (`artisan about`) | Backend/Framework | CI/Infra | Exercises the full Laravel service-provider boot chain; the CI step is just the trigger |
| SendPortal Core route-registration proof (`artisan route:list`) | Backend/Framework (host + Core route delegation) | CI/Infra | Verifies `Sendportal::webRoutes()`/`apiRoutes()` registration, a host↔Core integration boundary, not new product behavior |
| PHPUnit suite (MySQL / PostgreSQL) | Database / Backend | CI/Infra | Existing test suite; unchanged by this phase except for running under the new 8.4 matrix entry |
| Failure gating / job-level fail-closed semantics | CI/Infra (GitHub Actions step + shell semantics) | — | Relies on GitHub Actions' default step-failure behavior and default `bash -eo pipefail` shell; no custom gating logic needed |

## Package Legitimacy Audit

**N/A for this phase.** Phase 3 introduces no new Composer or other package dependencies — it only adds CI workflow steps (`.github/workflows/ci.yml`) that invoke commands (`composer validate`, `composer check-platform-reqs`, `bin/composer-policy audit`, `php artisan about`, `php artisan route:list`) already present in the repository's tooling. No `npm install`/`composer require` of new packages occurs in this phase. The Package Legitimacy Gate protocol therefore does not apply; no packages were checked and none require disposition.

## Standard Stack

This phase does not introduce a new technology stack — it operates entirely within the stack already locked in Phase 1/2. The table below documents the **exact verified versions in play**, not new adoptions.

### Core (existing, verified in this session)

| Component | Version | Purpose | Verification |
|-----------|---------|---------|---------------|
| PHP | 8.4.23 | Target runtime under test | `[VERIFIED: local execution, php -v]` |
| Composer (guard-vendored) | 2.10.2 | Guarded validate/audit/install route (`bin/composer-policy`) | `[VERIFIED: bin/composer-policy source, composer-2.10.2.phar.sha256]` |
| Composer (container system binary) | "latest stable at image build time" (unpinned) | Direct, non-guarded `check-platform-reqs --lock` call | `[CITED: github.com/kirschbaum-development/laravel-test-runner-container Dockerfile]` — see Pitfall 2 |
| Laravel Framework | v11.55.0 (locked) | Application framework under boot/route proof | `[VERIFIED: local execution, php artisan about]` |
| `kirschbaumdevelopment/laravel-test-runner` | `:8.4` tag | CI container image for the new matrix entry | `[CITED: hub.docker.com/r/kirschbaumdevelopment/laravel-test-runner/tags]` |
| PHPUnit | 10.5 (root), 11.0 (sendportal-core dev) | Existing test runner, unchanged | `[VERIFIED: composer.json require-dev]` |

### Supporting

| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| `mettle/sendportal-core` | v3.0.2 (locked) | Core email-marketing domain; route delegation proof target | Already installed; read-only reference this phase |

### Alternatives Considered

None — CONTEXT.md's decisions (D-01..D-07) already selected the minimal path (extend existing matrix/steps rather than a parallel job or new tooling). No alternative stack choices are in scope.

**Installation:** No new installation. Existing gate: `php bin/composer-policy install -q --no-ansi --no-interaction --no-progress --prefer-dist` with `--no-scripts` **dropped** per D-04.

**Version verification:**
```bash
composer --version                     # confirmed locally: Composer version 2.10.2
php -v                                 # confirmed locally: PHP 8.4.23 (cli)
php artisan about | grep -i "laravel version"   # confirmed: 11.55.0
```

## Architecture Patterns

### System Architecture Diagram

```
GitHub Actions push trigger (on: push)
        │
        ▼
matrix job × 3  ["...test-runner:8.2", ":8.3", ":8.4"]  ── all steps below run identically per matrix entry (D-02)
        │
        ├─▶ [checkout]
        │
        ├─▶ Verify Composer policy routes (self-test)
        │        php tests/Composer/ComposerPolicyGuardTest.php
        │        php tests/Composer/ComposerPolicyGuardTest.php --route-audit
        │        └─ fails job on nonzero exit ─────────────────────► CI-02 surface (guard contract)
        │
        ├─▶ GATE 1 — Composer metadata (D-03.1)
        │        php bin/composer-policy validate --strict
        │        └─ fails job on nonzero exit ─────────────────────► CI-02 surface #1
        │
        ├─▶ Install (script-enabled, D-04)
        │        php bin/composer-policy install ... --prefer-dist  (NO --no-scripts)
        │        triggers composer.json scripts.post-autoload-dump:
        │            Illuminate\Foundation\ComposerScripts::postAutoloadDump
        │            @php artisan package:discover --ansi   ◄── RUNTIME-01 discovery proof
        │        └─ fails job on nonzero exit (install OR package:discover failure)
        │
        ├─▶ GATE 2 — Platform requirements (D-03.2, direct, non-guarded)
        │        composer check-platform-reqs --lock
        │        └─ fails job on nonzero exit ─────────────────────► CI-02 surface #2
        │
        ├─▶ GATE 3 — Dependency audit (D-03.3)
        │        php bin/composer-policy audit --locked
        │        └─ fails job on nonzero exit (unignored advisory) ─► CI-02 surface #3
        │
        ├─▶ GATE 4 — Laravel/Core boot (D-05, D-03.4)
        │        php artisan about                       ── RUNTIME-01 safe boot check
        │        php artisan route:list --no-ansi | grep -q sendportal.dashboard   ── RUNTIME-04 Core proof
        │        └─ fails job on nonzero exit (boot crash OR route missing) ───────► CI-02 surface #4
        │
        ├─▶ Run Testsuite against MySQL  (unchanged, D-06)
        │        vendor/bin/phpunit   DB_CONNECTION=mysql DB_HOST=mysql
        │        └─ fails job on nonzero exit ─────────────────────► CI-02 surface #5a (RUNTIME-02)
        │
        └─▶ Run Testsuite against Postgres  (unchanged, D-06)
                 vendor/bin/phpunit   DB_CONNECTION=pgsql DB_HOST=postgres
                 └─ fails job on nonzero exit ─────────────────────► CI-02 surface #5b (RUNTIME-03)
```

### Recommended Step Ordering (Claude's Discretion resolved)

CONTEXT.md leaves exact ordering to discretion but proposes: guard self-test → validate → install (script-enabled) → check-platform-reqs → audit → boot check → route proof → MySQL PHPUnit → Postgres PHPUnit. This research confirms that ordering is technically sound:

- `validate --strict` needs no `vendor/` (works before install).
- `check-platform-reqs --lock` and `audit --locked` both work directly against `composer.lock` and **do not require `vendor/`** to be installed (`--locked`/`--lock` mode reads the lock file, not installed packages) — `[CITED: getcomposer.org/doc/03-cli.md operating on --locked/--lock modes]`. They *could* run before install too, but running them after install (as proposed) is equally valid and keeps a single, readable top-to-bottom narrative: validate metadata → install → verify what got installed is platform-safe and audit-clean → prove it boots → prove tests pass. No functional reason to reorder.
- `package:discover` (triggered by the script-enabled install) and the boot/route proof steps require **no env provisioning** (see Pitfall 1) — no ordering dependency on an `.env`/`key:generate` step because none is needed.

### Pattern: Non-mutating diagnostic run directly, mutating/audited commands run through the guard

`check-platform-reqs` is confirmed **not** in `ComposerPolicyCommandContract::ALLOWED_COMMANDS` (`validate`, `audit`, `install`, `update` only) — `[VERIFIED: tools/composer/ComposerPolicyCommandContract.php:15, cross-checked against tests/Composer/ComposerPolicyGuardTest.php:1374-1388]`. Calling it directly (`composer check-platform-reqs --lock`, using whatever `composer` binary is on the container's `PATH`) is consistent with the existing guard design intent: the guard exists to prevent *mutating* or *audit-bypassing* Composer invocations from skipping the security/reproducibility contract; a read-only diagnostic has no such risk surface. Do not add `check-platform-reqs` to the guard's command contract — that would be scope creep into "modify the guard contract," which CONTEXT.md explicitly rules out.

### Anti-Patterns to Avoid

- **Adding an `.env` copy / `key:generate` step "just in case":** Verified unnecessary for `package:discover`, `about`, and `route:list` (see Pitfall 1). Adding one is not wrong, but it is an unrequested extra step outside the locked decisions' "minimal" ethos, and it introduces a place where a typo (`.env.example` missing, `key:generate --ansi` failing on a read-only filesystem, etc.) could become a spurious new failure surface not among CI-02's five.
- **Piping the route-list assertion through `|| true` or `set +e`:** Would silently defeat CI-02 surface #4 (Core boot proof). GitHub Actions' default `bash -eo pipefail` already gives correct fail-closed behavior for a plain pipeline — do not add anything that weakens it.
- **Routing `check-platform-reqs` through `bin/composer-policy`:** Not supported by the guard's command contract (would `reject('Composer command rejected.')` and always fail the job) — must be called directly against the container's system Composer.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Detecting whether the app can boot on PHP 8.4 | A custom PHP script that requires bootstrap files and checks for fatal errors | `php artisan about` | Already exercises the full service-provider boot chain (including SendPortal Core's own service providers) as a first-class Artisan command; read-only, well-defined exit-code contract |
| Proving Core routes are registered | A new PHPUnit feature test hitting a Core route | `php artisan route:list` + grep | CONTEXT.md D-05 explicitly rules out adding new PHPUnit/product test code for this phase (preserves "no product-behavior change" and avoids pulling in HARD-02's deferred behavior smoke test) |
| Detecting platform incompatibility | Manually diffing installed `ext-*` versions against `composer.lock` requirements | `composer check-platform-reqs --lock` | Composer already performs the exact aggregate-constraint check across every locked package; verified locally to correctly resolve union-of-caret-range constraints (e.g., sendportal-core's `^8.2|^8.3`) |
| Detecting vulnerable/blocked dependencies | A custom advisory-fetching script | `composer audit --locked` via the existing native `config.policy` block | Already wired in Phase 1/2 with `block: true`, `audit: "fail"`, and three time-bounded `ignore-id` exceptions; re-implementing this would duplicate an already-working, already-reviewed control |

**Key insight:** Every one of this phase's five failure surfaces already has a first-party tool that does exactly the check needed (Composer's own `validate`/`check-platform-reqs`/`audit`, Artisan's own `about`/`route:list`, PHPUnit itself). The entire phase is CI wiring of existing capabilities — there is no custom logic to write, only CI YAML.

## Common Pitfalls

### Pitfall 1: Assuming the RUNTIME-01/04 steps need `.env`/`APP_KEY` provisioning
**What goes wrong:** CONTEXT.md D-04 flags this as the "highest-risk item" and suggests `.env` copy + `key:generate` or an inline `APP_KEY` may be needed for the artisan boot/route steps.
**Why it happens:** `phpunit.xml.dist` hardcodes `APP_KEY` for the PHPUnit steps, and it's easy to assume any other `artisan` invocation needs the same. In fact, `MissingAppKeyException` is thrown lazily, only when `Illuminate\Encryption\Encrypter` is actually resolved from the container (e.g. session/cookie encryption during a real HTTP request, or explicit `encrypt()`/`Crypt::` calls) — not during framework boot, not during `package:discover`, not during `route:list`.
**How to avoid:** This session verified directly, in this repository, against the real committed lock: `composer install`'s script-enabled `package:discover` step, `php artisan about`, and `php artisan route:list` **all exit `0` with no `.env` file and `config('app.key')` resolving to `NULL`.** `[VERIFIED: local execution, this repository, PHP 8.4.23]`. The planner should add **no env-provisioning step** for these three commands specifically. If a *future* boot-proof step is added that touches session/cookie encryption, revisit this.
**Warning signs:** If a future `about`/`route:list`-style check starts failing only in CI (not locally), suspect the container image's PHP build has a different default timezone/locale causing an unrelated warning-as-error, not a missing `APP_KEY`.

### Pitfall 2: Assuming Composer version parity between the guard and the direct `check-platform-reqs` call
**What goes wrong:** `bin/composer-policy` always uses the repository's vendored, checksum-verified `composer-2.10.2.phar` (`[VERIFIED: bin/composer-policy:6-10, COMPOSER_RELEASE constant]`). The direct, non-guarded `composer check-platform-reqs --lock` call (D-03.2) instead resolves whatever `composer` binary is on the container's `PATH` — which the `laravel-test-runner` image installs via the official Composer installer **without pinning a version** (`[CITED: github.com/kirschbaum-development/laravel-test-runner-container, 8.4/Dockerfile]`).
**Why it happens:** The image is rebuilt periodically and its bundled Composer tracks "latest stable at build time," which could in principle diverge from 2.10.2 in behavior for edge cases.
**How to avoid:** This is a low-severity risk in practice — `check-platform-reqs` is a stable, long-standing Composer 2.x command and both Composer versions are well past the PHP-8.4-aware baseline (Composer added PHP 8.4 platform recognition well before 2.10). No mitigation is strictly required, but the planner should have the first live 8.4 CI run treated as the actual proof (not just local reproduction), since this is the one place where CI's Composer binary could differ from what was verified locally in this research session.
**Warning signs:** `check-platform-reqs` succeeding locally but failing in the actual `:8.4` CI job would point here first.

### Pitfall 3: Treating `route:list` route visibility as an authorization check
**What goes wrong:** It's tempting to assume that because `sendportal.dashboard` sits behind `auth`, `verified`, and `RequireWorkspace` middleware, some authenticated request or session/DB state is needed to "prove" it's really wired up.
**Why it happens:** Conflating route *registration* (a boot-time, static operation) with route *handling* (a request-time operation gated by middleware).
**How to avoid:** `route:list` lists every registered route regardless of middleware — confirmed live: `sendportal.dashboard` appears in `route:list --no-ansi` output with no session, no DB connection, and no auth performed. `[VERIFIED: local execution]`. This is exactly what RUNTIME-04 asks for ("provider and route-registration integration still boots," not a behavior test) — do not add authentication scaffolding to "make the proof more real."
**Warning signs:** A plan task that tries to log in a test user or hit the route via HTTP before asserting `route:list` output is over-scoped relative to D-05.

### Pitfall 4: Forgetting `--lock` / `--locked` flags and silently checking the wrong artifact
**What goes wrong:** `composer check-platform-reqs` (no `--lock`) checks the **currently installed** `vendor/` packages, not the lock file; `composer audit` (no `--locked`) similarly can behave differently pre/post-install. Omitting these flags could make a gate pass or fail based on installation state rather than the actual committed, reviewed lock — silently changing what's being verified.
**Why it happens:** The flags are easy to drop when copy-pasting commands, and both variants "work" without error either way, so nothing visibly breaks — it just stops checking what CI-02 requires (the *locked* graph).
**How to avoid:** D-03 already specifies `check-platform-reqs --lock` and `audit --locked` explicitly — the planner's task text and any verification step should assert the literal flag is present in the diff, not just that "the check runs."
**Warning signs:** A future refactor of the CI step that "simplifies" the command by dropping flags.

### Pitfall 5: Assuming GitHub Actions doesn't have `pipefail` by default
**What goes wrong:** Assuming a pipeline like `artisan route:list | grep pattern` needs manual `set -o pipefail` or `${PIPESTATUS[0]}` checking to correctly propagate `artisan`'s exit code through `grep`.
**Why it happens:** Plain `bash` scripts run locally (e.g. via a Makefile) do NOT have `pipefail` on by default, so developers reasonably import that assumption into CI.
**How to avoid:** GitHub Actions' documented default shell for Linux `run:` steps is `bash --noprofile --norc -eo pipefail {0}` `[CITED: docs.github.com/en/actions/using-workflows/workflow-syntax-for-github-actions]` — `pipefail` is already on. A plain `php artisan route:list --no-ansi | grep -q sendportal.dashboard` step correctly fails the job if `artisan` crashes OR if `grep` finds nothing. No extra plumbing needed.
**Warning signs:** A plan step that explicitly sets `shell: sh` or manually re-adds `set -eo pipefail` — redundant with the default, and `sh` may not even support `pipefail`, which would be a regression.

## Code Examples

### RUNTIME-01: script-enabled install (D-04) — flag diff from current `ci.yml:47`
```yaml
# Source: current .github/workflows/ci.yml + composer.json scripts.post-autoload-dump
# Before:
- name: Install composer dependencies
  run: php bin/composer-policy install -q --no-ansi --no-interaction --no-scripts --no-progress --prefer-dist
# After (drop --no-scripts only):
- name: Install composer dependencies
  run: php bin/composer-policy install -q --no-ansi --no-interaction --no-progress --prefer-dist
```
`--no-scripts` is a guard-recognized `VALUELESS_GLOBAL_OPTIONS` entry `[VERIFIED: tools/composer/ComposerPolicyCommandContract.php:26-35]` — its presence or absence does not affect whether the guard accepts the command; dropping it purely changes whether Composer's `scripts.post-autoload-dump` (→ `artisan package:discover --ansi`) runs.

### GATE 2: platform requirements — direct, non-guarded call verified locally
```bash
# Source: local execution, this repository, PHP 8.4.23, committed composer.lock
composer check-platform-reqs --lock
# Output (abbreviated, all lines "success"):
#   php                  8.4.23   success
#   ext-mbstring          *       success provided by symfony/polyfill-mbstring
#   ext-ctype             *       success provided by symfony/polyfill-ctype
#   ... (21 more lines, all success)
```

### GATE 4: boot + Core route proof — verified end-to-end, no env provisioning
```bash
# Source: local execution, this repository, no .env, no APP_KEY, caches cleared
php artisan config:clear && php artisan route:clear   # simulate fresh checkout (no stale cache)
php artisan about                                     # exit 0
php artisan route:list --no-ansi | grep -q 'sendportal.dashboard'   # exit 0 (route found)
echo $?   # 0
```

### The route target (RUNTIME-04)
```php
// Source: vendor/mettle/sendportal-core/src/Routes/WebRoutes.php:43-51
public function sendportalWebRoutes(): callable
{
    return function () {
        $this->name('sendportal.')->namespace('\Sendportal\Base\Http\Controllers')->group(static function (
            Router $appRouter
        ) {
            // Dashboard.
            $appRouter->get('/', 'DashboardController@index')->name('dashboard');
            // ... (campaigns, messages, email_services, tags, templates, subscribers)
        });
    };
}
```
Registered in `routes/web.php:108-112` (`Route::middleware(['auth', 'verified', RequireWorkspace::class])->group(static function () { Sendportal::webRoutes(); });`). Full route name resolves to `sendportal.dashboard`.

## State of the Art

| Old Approach (current `ci.yml`) | Current/Target Approach (this phase) | When Changed | Impact |
|--------------------------------|----------------------------------------|---------------|--------|
| Container matrix: 8.2, 8.3 only | Container matrix: 8.2, 8.3, **8.4** | This phase (D-01) | CI-01 |
| Install with `--no-scripts` (package discovery skipped in CI) | Install without `--no-scripts` (script-enabled, exercises `package:discover`) | This phase (D-04) | RUNTIME-01 |
| No platform/audit/boot gates beyond the guard self-test | Four new gate steps (validate/platform/audit/boot+route) on every matrix entry | This phase (D-02/D-03) | CI-02 |
| No explicit Core route-registration proof | `artisan route:list` assertion against `sendportal.dashboard` | This phase (D-05) | RUNTIME-04 |
| DB matrix / trigger | Unchanged (`mysql:5.7`, `postgres`, `on: push`) | N/A (explicitly out of scope) | RUNTIME-02/03 preserved |

**Deprecated/outdated:** None — this phase strengthens an existing, already-currently-maintained CI workflow; nothing here replaces a legacy pattern.

## Assumptions Log

| # | Claim | Section | Risk if Wrong |
|---|-------|---------|---------------|
| A1 | The `laravel-test-runner:8.4` container's bundled system Composer (unpinned, "latest stable at build time") behaves identically to the locally-verified 2.10.2 for `check-platform-reqs --lock` purposes | Pitfall 2, Standard Stack | Low — if wrong, the first live CI run on `:8.4` would surface a platform-check discrepancy not reproducible locally; mitigated by treating the first CI run as the real proof, not local reproduction alone |
| A2 | No SendPortal Core or host service-provider `boot()` method added in a future change will resolve the encrypter or open a DB connection eagerly (which would silently invalidate Pitfall 1's "no env provisioning needed" finding) | Pitfall 1 | Low for this phase (verified against the current codebase state) — would need re-verification if `AppServiceProvider::boot()` or a Core provider changes |

**If this table is empty:** N/A — two low-risk assumptions logged above; neither blocks planning, both are flagged for the planner's awareness.

## Open Questions

1. **Does the `laravel-test-runner:8.4` image's default `php.ini` (e.g., `memory_limit`, `error_reporting`) differ from `:8.2`/`:8.3` in a way that surfaces new PHP 8.4 deprecation warnings as visible noise (not failures) during `composer install` or PHPUnit?**
   - What we know: The image ships the extensions needed by the current lock (confirmed via local `check-platform-reqs --lock` extension list cross-referenced against the image's documented extension list).
   - What's unclear: PHP 8.4 introduces new deprecation notices (e.g., around implicitly nullable parameters) that could appear as warnings in `laravel/framework` v11.55.0 or `mettle/sendportal-core` v3.0.2 dependencies not yet updated for 8.4. These would not fail the job (warnings, not fatals) but could produce noisy CI output.
   - Recommendation: Not a planning blocker — CI-02's failure surfaces are about exit codes, not warning noise. If the planner wants a clean-output verification, add it as an optional post-execution manual review item, not a new automated gate (adding one would be scope creep beyond the five listed CI-02 surfaces).

2. **Should the boot/route-proof step assertion use `grep -q` (verified working) or `--json` + a JSON tool?**
   - What we know: `grep -q sendportal.dashboard` against `route:list --no-ansi` output was verified to work and requires no extra tooling (`jq` availability in the container was not independently confirmed).
   - What's unclear: Whether `jq` ships in `laravel-test-runner` images (not confirmed either way).
   - Recommendation: Use `grep -q` — it needs no unconfirmed extra dependency and was directly verified end-to-end in this session. This is within Claude's Discretion per CONTEXT.md; research recommends `grep -q` as the safer default.

## Environment Availability

| Dependency | Required By | Available (local) | Version | Fallback |
|------------|------------|-----------|---------|----------|
| PHP 8.4 | RUNTIME-01..04, CI-01 | ✓ | 8.4.23 | — |
| Composer (vendored, guard) | validate/audit/install gates | ✓ | 2.10.2 (checksum-verified) | — |
| Composer (system, container) | direct `check-platform-reqs --lock` | Not locally probed (container-specific) | "latest stable" per image build | See Pitfall 2 / A1 — no fallback needed, low risk |
| Docker / `kirschbaumdevelopment/laravel-test-runner:8.4` | CI-01 matrix entry | Not locally available (Docker not invoked this session) | Confirmed to exist on Docker Hub | — |
| MySQL 5.7 / PostgreSQL service containers | RUNTIME-02/03 | Unchanged from existing `ci.yml`; not modified this phase | — | — |
| `mettle/sendportal-core` (installed) | RUNTIME-04 route proof | ✓ (vendor/ present in this checkout) | v3.0.2 | — |

**Missing dependencies with no fallback:** None — every dependency this phase touches is either already verified working locally (PHP 8.4.23, guard's Composer 2.10.2, sendportal-core v3.0.2) or is a well-established, existing GitHub Actions container/service pattern already in production use for 8.2/8.3.

**Missing dependencies with fallback:** None required.

## Validation Architecture

### Test Framework

| Property | Value |
|----------|-------|
| Framework | PHPUnit 10.5 (root `composer.json`), config `phpunit.xml.dist` |
| Config file | `phpunit.xml.dist` (hardcoded `APP_KEY`, `APP_ENV=testing`, `DB_DATABASE=sendportal_testing`, driver env vars) |
| Quick run command | `vendor/bin/phpunit --filter=<TestName>` (existing suite; no new tests added this phase) |
| Full suite command | `vendor/bin/phpunit` (run twice in CI: once per `DB_CONNECTION`) |

This phase adds **no new PHPUnit tests** (per D-05, product-behavior tests and the HARD-02 Core smoke test are explicitly out of scope). Validation for RUNTIME-01/04 and CI-02 surfaces 1-4 is via **CI step exit codes**, not PHPUnit assertions.

### Phase Requirements → Validation Map

| Req ID | Behavior | Validation Type | Command | Proven This Session? |
|--------|----------|-----------|-------------------|-------------|
| RUNTIME-01 | Script-enabled install completes package discovery + safe boot on 8.4 | CI step (non-PHPUnit) | `php bin/composer-policy install ... ` (no `--no-scripts`) then `php artisan about` | ✅ `[VERIFIED: local execution]` |
| RUNTIME-02 | Existing PHPUnit suite passes on 8.4 against MySQL | Automated (existing) | `vendor/bin/phpunit` with `DB_CONNECTION=mysql` | Unchanged from existing CI; not re-run in this research session (no local MySQL container invoked) |
| RUNTIME-03 | Existing PHPUnit suite passes on 8.4 against PostgreSQL | Automated (existing) | `vendor/bin/phpunit` with `DB_CONNECTION=pgsql` | Unchanged from existing CI; not re-run in this research session |
| RUNTIME-04 | Core provider/route-registration boots on 8.4, no behavior change | CI step (non-PHPUnit) | `php artisan route:list --no-ansi \| grep -q sendportal.dashboard` | ✅ `[VERIFIED: local execution]` |
| CI-01 | CI includes 8.4 job, retains 8.2/8.3 | Static config check | `container:` matrix list in `ci.yml` includes all three tags | Trivially verifiable by reading the committed YAML after the edit |
| CI-02 | CI fails on any of 5 failure surfaces | CI step exit-code semantics | Each gate is its own `run:` step; GitHub Actions' default `bash -eo pipefail` + default step-failure behavior fails the job on any nonzero exit | ✅ `[CITED: docs.github.com — default shell/step-failure semantics]` |

### Sampling Rate
- **Per task commit:** Not applicable in the PHPUnit sense — this phase's "tests" are CI step exit codes. A task-level dry run should be: run each new/modified command locally against PHP 8.4 (as done in this research session) before committing the YAML.
- **Per wave merge:** Full CI run on the actual GitHub Actions runner (the real proof for Pitfall 2's A1 assumption) — cannot be fully substituted by local reproduction.
- **Phase gate:** A green run of the full `:8.4` matrix job (all 8 steps) in GitHub Actions, plus retained-green `:8.2`/`:8.3` jobs, before `/gsd-verify-work`.

### Wave 0 Gaps
None — existing test infrastructure (PHPUnit suite, `ComposerPolicyGuardTest.php`, `bin/composer-policy`) already covers everything this phase needs. No new test files, fixtures, or framework installs are required. The "tests" this phase adds are CI YAML steps invoking already-existing first-party CLI diagnostics (`composer validate/check-platform-reqs/audit`, `artisan about/route:list`), not new automated test code.

## Security Domain

### Applicable ASVS Categories

| ASVS Category | Applies | Standard Control |
|---------------|---------|-----------------|
| V2 Authentication | No | Unchanged — this phase adds no auth code |
| V3 Session Management | No | Unchanged |
| V4 Access Control | No | Unchanged — route proof only checks registration, not access control behavior |
| V5 Input Validation | No | No new input surface |
| V6 Cryptography | No | `APP_KEY`/encryption unchanged; confirmed not needed for the new proof steps (Pitfall 1) |
| V14 Configuration/Deployment (supply chain, CI) | Yes | `composer validate --strict`, `check-platform-reqs --lock`, and `audit --locked` (native `config.policy` block with `block: true`, `audit: "fail"`) now run as **permanent** CI gates on every push, not just at Phase 1/2 review time |

### Known Threat Patterns for this stack

| Pattern | STRIDE | Standard Mitigation |
|---------|--------|---------------------|
| Vulnerable/malicious dependency silently introduced in a future PR | Tampering / Elevation of Privilege | `composer audit --locked` gate now runs on every push (this phase), fail-closed via native `config.policy` (`block: true`, `audit: "fail"`), already proven in Phase 1/2 |
| Dependency graph drift (installed ≠ committed lock) reaching production undetected | Tampering | `composer install` (never `update`) as the only install path (Phase 2 contract), now additionally gated by `check-platform-reqs --lock` and `validate --strict` on every CI run |
| Platform-incompatible package silently breaking production on a PHP version bump | Denial of Service | `check-platform-reqs --lock` gate proves the exact locked graph is platform-compatible with 8.4 before merge, on every push |

No new attack surface is introduced by this phase — it exclusively strengthens existing Phase 1/2 supply-chain controls by making them permanent, continuously-run CI gates rather than one-time review checks. `security_enforcement` is satisfied by this reinforcement; no new ASVS gaps are opened.

## Project Constraints (from CLAUDE.md / AGENTS.md)

- This phase's only edit target is `.github/workflows/ci.yml` (YAML), plus possibly a minimal `README.md` §"Dependency management" sync (discretion). **No PHP source files are authored in this phase** — the AGENTS.md PHP naming/style/`declare(strict_types=1)` conventions therefore do not apply to this phase's primary artifact.
- **Host/package boundary (AGENTS.md "Architectural Constraints"):** "Do not duplicate package routes or tenant models in this repository." The RUNTIME-04 proof reads Core's already-registered routes via `artisan route:list`; it must not add any new route definitions.
- **Dependency safety (AGENTS.md "Constraints"):** "Do not disable Composer platform checks or silently drop vulnerability protection." All four new gate steps (validate/platform/audit/boot) are additive hardening, consistent with this constraint. `check-platform-reqs` is called directly (not through the guard) only because it is a non-mutating diagnostic already outside the guard's `validate/audit/install/update` canonical set — this is not a weakening of the guard, per the guard's own test-authoritative command list.
- **Reproducibility (AGENTS.md "Constraints"):** "Commit composer.lock once a valid graph is resolved." This phase does not regenerate or re-resolve `composer.lock` — every gate operates against the already-committed lock (`install`, `check-platform-reqs --lock`, `audit --locked`).
- **PHP-CS-Fixer / `.editorconfig` (AGENTS.md "Code Style"):** Applies to any PHP files touched; this phase does not touch PHP files, so no formatting-tool run is required for the primary deliverable. If the `README.md` sync task is taken, standard Markdown conventions (not PHP-CS-Fixer) apply.
- **CI workflow governance:** `.github/workflows/format.yml` is a separate PR-formatting automation; `.github/workflows/ci.yml` is this phase's primary edit target. No overlap/conflict between the two workflows exists.

## Phase Requirements

| ID | Description | Research Support |
|----|-------------|------------------|
| RUNTIME-01 | Script-enabled lockfile install completes package discovery + safe boot check on PHP 8.4 | Verified locally: script-enabled install + `artisan package:discover` + `artisan about` all exit 0 with **no env provisioning** required (Pitfall 1, Code Examples) |
| RUNTIME-02 | Existing PHPUnit suite passes on PHP 8.4 against MySQL | Existing `ci.yml` MySQL PHPUnit step, unchanged (D-06); runs under new `:8.4` matrix entry (Validation Architecture map) |
| RUNTIME-03 | Existing PHPUnit suite passes on PHP 8.4 against PostgreSQL | Existing `ci.yml` Postgres PHPUnit step, unchanged (D-06); runs under new `:8.4` matrix entry |
| RUNTIME-04 | SendPortal Core provider/route-registration integration boots without behavior change | Concrete route target identified and verified: `sendportal.dashboard` via `route:list` grep, no auth/DB/env needed (Pitfall 3, Code Examples) |
| CI-01 | CI includes a PHP 8.4 job using the committed lockfile, retains 8.2/8.3 | `:8.4` tag confirmed to exist on Docker Hub (Standard Stack); matrix-list addition per D-01 |
| CI-02 | CI fails on any of 5 failure surfaces | GitHub Actions default `bash -eo pipefail` + default step-failure semantics confirmed (Pitfall 5, Validation Architecture map); each surface mapped to an independent CI step per D-03 |

## Sources

### Primary (HIGH confidence — local, direct verification against this repository)
- Local execution: `composer check-platform-reqs --lock` against committed `composer.lock`, real PHP 8.4.23 — all platform requirements pass, including the union-of-caret-ranges resolution for `mettle/sendportal-core`'s `^8.2|^8.3` constraint.
- Local execution: `composer validate --strict` — passes cleanly.
- Local execution: `composer audit --locked` (direct and via `bin/composer-policy`) — passes with the three expected, rationale-documented `ignore-id` advisories.
- Local execution: script-enabled `composer dump-autoload` (triggers `post-autoload-dump` → `artisan package:discover --ansi`) under a clean shell environment with no `.env`/`APP_KEY` — exits 0.
- Local execution: `php artisan about` and `php artisan route:list --no-ansi | grep sendportal.dashboard`, both under a clean environment (no `.env`, `config('app.key')` confirmed `NULL` via tinker) with config/route caches cleared to simulate a fresh checkout — both exit 0, route found.
- `tools/composer/ComposerPolicyCommandContract.php` — authoritative source for the guard's canonical command set (`validate`, `audit`, `install`, `update` only).
- `vendor/mettle/sendportal-core/src/Routes/WebRoutes.php`, `src/Routes/ApiRoutes.php`, `src/Services/Sendportal.php` — Core route registration source, confirming `sendportal.dashboard` as the RUNTIME-04 proof target.
- `bin/composer-policy` — guard implementation, confirms vendored Composer 2.10.2 checksum-pinned distribution used for guarded commands.

### Secondary (MEDIUM confidence — official documentation, cross-checked)
- [GitHub Actions workflow syntax docs](https://docs.github.com/en/actions/using-workflows/workflow-syntax-for-github-actions) — default shell `bash --noprofile --norc -eo pipefail {0}` for Linux `run:` steps; default step-failure behavior.
- [Docker Hub — kirschbaumdevelopment/laravel-test-runner tags](https://hub.docker.com/r/kirschbaumdevelopment/laravel-test-runner/tags) — confirms `:8.4` tag exists, pushed ~2 months ago, both amd64/arm64.
- [github.com/kirschbaum-development/laravel-test-runner-container](https://github.com/kirschbaum-development/laravel-test-runner-container) — 8.4 Dockerfile confirms PHP 8.4, unpinned "latest stable" Composer install, and the extension set (mbstring, MySQL, PostgreSQL, SQLite3, XML, XSL, Zip, cURL, SOAP, GMP, bcmath, Intl, IMAP, Bz2, Redis) matching what `check-platform-reqs --lock` requires.
- [getcomposer.org/doc/03-cli.md](https://getcomposer.org/doc/03-cli.md) — general command reference for `validate`, `audit`, `check-platform-reqs`.
- [PHP.Watch — New `composer audit` Command](https://php.watch/articles/composer-audit) — confirms `--locked` audits the lock file directly, no install required.

### Tertiary (LOW confidence)
- None used as load-bearing claims in this research — all execution-risk findings were directly verified locally in this session rather than relying on unverified web search summaries.

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — every version/behavior claim was either verified by direct local execution against the real committed lock and real PHP 8.4.23, or cited from official documentation/vendor sources.
- Architecture: HIGH — the CI step flow directly mirrors the already-locked CONTEXT.md decisions; no design exploration was needed, only execution-risk verification.
- Pitfalls: HIGH — all five pitfalls were resolved by direct command execution in this repository, not speculation.

**Research date:** 2026-07-25
**Valid until:** 30 days (stable CI-wiring domain; re-verify sooner only if `laravel-test-runner` images are rebuilt with a materially different Composer/extension baseline, or if `laravel/framework`/`mettle/sendportal-core` are upgraded before this phase executes).
