# Phase 3: PHP 8.4 Runtime, Core Integration, and CI Verification - Pattern Map

**Mapped:** 2026-07-25
**Files analyzed:** 1 (modified, in place — no new files created)
**Analogs found:** 1 / 1 (self-analog: existing steps within the same file)

## Scope Note

This phase creates **no new files**. Per CONTEXT.md and RESEARCH.md, the entire
deliverable is an in-place edit to `.github/workflows/ci.yml` (plus a possible,
discretionary, minimal `README.md` §"Dependency management" sync — not a new
file, an edit to prose in an existing doc). There is therefore no "new file →
external analog" mapping to perform. Instead, the most useful pattern map is
**the existing steps in `ci.yml` itself**, which the new steps must match in
style, plus the guard/manifest patterns in `bin/composer-policy` and
`composer.json` that the new gate steps invoke. This document maps each new
CI step to its closest in-repo precedent.

## File Classification

| Modified File | Role | Data Flow | Closest Analog | Match Quality |
|----------------|------|-----------|-----------------|---------------|
| `.github/workflows/ci.yml` (matrix `container` list — add `:8.4`) | config | batch (CI job config) | `.github/workflows/ci.yml:7-11` (existing `:8.2`/`:8.3` entries) | exact — literal sibling entry in the same array |
| `.github/workflows/ci.yml` (install step — drop `--no-scripts`) | config/CI-step | batch | `.github/workflows/ci.yml:46-47` (current install step) | exact — same step, flag diff only |
| `.github/workflows/ci.yml` (new step: `composer validate --strict`) | config/CI-step | request-response (CLI invocation, exit-code gate) | `.github/workflows/ci.yml:42-45` ("Verify Composer policy routes" self-test step) | role-match — same "guard-routed CLI check that fails the job on nonzero exit" shape |
| `.github/workflows/ci.yml` (new step: `check-platform-reqs --lock`) | config/CI-step | request-response | `.github/workflows/ci.yml:46-47` (install step, direct Composer invocation pattern) minus guard wrapper | role-match — direct (non-guarded) Composer CLI call, same `run:` step shape |
| `.github/workflows/ci.yml` (new step: `bin/composer-policy audit --locked`) | config/CI-step | request-response | `.github/workflows/ci.yml:46-47` (install step — guard-routed `bin/composer-policy` invocation) | exact — same guard-wrapper invocation convention (`php bin/composer-policy <command> <flags>`) |
| `.github/workflows/ci.yml` (new step: `artisan about` + `artisan route:list \| grep`) | config/CI-step | request-response | `.github/workflows/ci.yml:48-57` (existing PHPUnit steps — "run one command, let nonzero exit fail the job" convention) | role-match — same "single `run:` command, default GH Actions fail-closed semantics" pattern |

## Pattern Assignments

### `.github/workflows/ci.yml` — matrix `container` list (add `:8.4`)

**Analog:** the file's own existing matrix array, `.github/workflows/ci.yml:7-11`

**Current pattern:**
```yaml
    strategy:
      matrix:
        container: [
          "kirschbaumdevelopment/laravel-test-runner:8.2",
          "kirschbaumdevelopment/laravel-test-runner:8.3"
        ]
```

**Apply pattern:** append a third array entry, same string-literal image-tag
convention, same trailing-comma-then-newline formatting as the existing two
entries:
```yaml
        container: [
          "kirschbaumdevelopment/laravel-test-runner:8.2",
          "kirschbaumdevelopment/laravel-test-runner:8.3",
          "kirschbaumdevelopment/laravel-test-runner:8.4"
        ]
```
No other part of the `container:`/`name:` block (`.github/workflows/ci.yml:13-16`)
needs to change — both already reference `${{ matrix.container }}` generically
and will pick up the new entry automatically.

---

### `.github/workflows/ci.yml` — install step (drop `--no-scripts`)

**Analog:** the step itself, `.github/workflows/ci.yml:46-47`

**Current:**
```yaml
      - name: Install composer dependencies
        run: php bin/composer-policy install -q --no-ansi --no-interaction --no-scripts --no-progress --prefer-dist
```

**Apply pattern:** same step name, same guard-wrapper invocation
(`php bin/composer-policy install ...`), same flag set minus `--no-scripts`:
```yaml
      - name: Install composer dependencies
        run: php bin/composer-policy install -q --no-ansi --no-interaction --no-progress --prefer-dist
```
This is confirmed safe by the guard contract: `--no-scripts` is a
`VALUELESS_GLOBAL_OPTIONS` entry recognized by
`tools/composer/ComposerPolicyCommandContract.php:26-35` (per RESEARCH.md) —
its absence does not change whether the guard accepts the command, it only
lets Composer's `post-autoload-dump` script run (see `composer.json:63-66`
below).

---

### `.github/workflows/ci.yml` — new step: Composer metadata validate

**Analog:** the existing "Verify Composer policy routes" self-test step,
`.github/workflows/ci.yml:42-45`

**Current self-test step pattern (structurally what to copy — named step,
guard-routed CLI calls, no `env:` block needed, relies on GH Actions' default
`bash -eo pipefail` to fail the job):**
```yaml
      - name: Verify Composer policy routes
        run: |
          php tests/Composer/ComposerPolicyGuardTest.php
          php tests/Composer/ComposerPolicyGuardTest.php --route-audit
```

**New step to add (per CONTEXT.md D-03.1), same shape, guard-routed:**
```yaml
      - name: Verify Composer manifest
        run: php bin/composer-policy validate --strict
```
`validate` is confirmed in the guard's canonical command set
(`ComposerPolicyCommandContract`, cross-referenced in
`bin/composer-policy:395-399` — `ComposerPolicyCommandContract::decide($arguments)`
gates every invocation; unauthorized commands hit `reject('Composer command
rejected.')` at `bin/composer-policy:398`).

---

### `.github/workflows/ci.yml` — new step: platform requirements (direct, non-guarded)

**Analog:** the install step's direct-CLI-invocation shape,
`.github/workflows/ci.yml:46-47`, but WITHOUT the `bin/composer-policy` wrapper
— per CONTEXT.md D-03.2, `check-platform-reqs` is confirmed absent from the
guard's canonical set (`validate/audit/install/update`; see
`bin/composer-policy:403,422` where only `install`/`update` trigger the
manifest-policy check, and RESEARCH.md's citation of
`tests/Composer/ComposerPolicyGuardTest.php:1375-1388`).

**New step (per CONTEXT.md D-03.2 / RESEARCH.md Code Examples):**
```yaml
      - name: Check platform requirements
        run: composer check-platform-reqs --lock
```
Do NOT route this through `php bin/composer-policy` — it would hit
`reject('Composer command rejected.')` (`bin/composer-policy:398`, guard
contract). This is a deliberate exception to the "wrap with bin/composer-policy"
convention, justified because the command is read-only/non-mutating.

---

### `.github/workflows/ci.yml` — new step: dependency audit

**Analog:** the install step's guard-wrapper invocation convention,
`.github/workflows/ci.yml:46-47`

**Pattern to copy:** `php bin/composer-policy <command> <flags>`, exactly like
the install step:
```yaml
      - name: Install composer dependencies
        run: php bin/composer-policy install -q --no-ansi --no-interaction --no-progress --prefer-dist
```

**New step (per CONTEXT.md D-03.3):**
```yaml
      - name: Audit dependencies
        run: php bin/composer-policy audit --locked
```
`audit` is in the guard's canonical set. The advisory policy this gate
enforces is the native `config.policy` block already committed in
`composer.json:30-41` (`block: true`, `audit: "fail"`, three time-bounded
`ignore-id` exceptions) — no new policy configuration is needed, this step
just makes the existing Phase 1/2 policy a permanent CI gate.

---

### `.github/workflows/ci.yml` — new step: Laravel/Core boot + route-registration proof

**Analog:** the existing PHPUnit steps' "single command, `env:` block if
needed, rely on default GH Actions fail-closed semantics" shape,
`.github/workflows/ci.yml:48-57`:
```yaml
      - name: Run Testsuite against MySQL
        run: vendor/bin/phpunit
        env:
          DB_CONNECTION: mysql
          DB_HOST: mysql
```

**New step (per CONTEXT.md D-05 / RESEARCH.md Code Examples, verified locally
to need NO `env:` block — no `.env`, no `APP_KEY` required for these three
commands):**
```yaml
      - name: Verify Laravel boot and SendPortal Core route registration
        run: |
          php artisan about
          php artisan route:list --no-ansi | grep -q sendportal.dashboard
```
No `env:` block needed (contrast with the PHPUnit steps above, which do need
one) — confirmed by RESEARCH.md Pitfall 1: `MissingAppKeyException` is only
thrown when the encrypter is resolved, which neither `about` nor `route:list`
does. Do NOT append `|| true` or set `shell: sh` — GitHub Actions' default
`bash --noprofile --norc -eo pipefail {0}` already fails the job correctly on
either command failing (RESEARCH.md Pitfall 5).

---

## Shared Patterns

### Guard-wrapper invocation convention
**Source:** `.github/workflows/ci.yml:46-47` (install step); guard internals
in `bin/composer-policy:395-426`
**Apply to:** every mutating/audited Composer command (`validate`, `install`,
`audit`) — always invoke as `php bin/composer-policy <command> <flags>`,
never call `composer` directly for these three.
```php
// bin/composer-policy:395-399
$commandDecision = ComposerPolicyCommandContract::decide($arguments);

if (! $commandDecision['allowed']) {
    reject('Composer command rejected.');
}
```

### Direct (non-guarded) invocation for read-only diagnostics
**Source:** RESEARCH.md D-03.2, confirmed against
`bin/composer-policy:403,422` (only `install`/`update` trigger manifest-policy
checks) and the guard's canonical command list.
**Apply to:** `check-platform-reqs` only, in this phase. Call the container's
system `composer` binary directly, not the guard wrapper.

### Fail-closed via default GitHub Actions semantics — no custom gating logic
**Source:** every existing step in `.github/workflows/ci.yml` (none use
explicit `continue-on-error`, `set +e`, or `|| true`)
**Apply to:** all five new gate steps — a nonzero exit from any `run:` command
fails the job by default; do not add error suppression.

### Native advisory policy block (read-only reference, do not modify)
**Source:** `composer.json:30-41`
```json
"policy": {
    "ignore-unreachable": false,
    "advisories": {
        "block": true,
        "audit": "fail",
        "ignore-id": {
            "PKSA-3r5d-mb8f-1qw9": "...",
            "PKSA-m5cs-t1y6-qpcs": "...",
            "PKSA-mdq4-51ck-6kdq": "..."
        }
    }
}
```
**Apply to:** the new audit gate step relies on this block being unchanged;
do not touch it in this phase.

### `post-autoload-dump` script hook (read-only reference, now exercised by D-04)
**Source:** `composer.json:62-66`
```json
"scripts": {
    "post-autoload-dump": [
        "Illuminate\\Foundation\\ComposerScripts::postAutoloadDump",
        "@php artisan package:discover --ansi"
    ],
    ...
}
```
**Apply to:** confirms what "drop `--no-scripts`" actually triggers — no edit
to this block is needed, just removal of the flag that was suppressing it.

## No Analog Found

None — this phase touches exactly one file (`.github/workflows/ci.yml`), and
every new step has a same-file precedent as mapped above. If the discretionary
`README.md` §"Dependency management" sync is taken, there is no close
in-repo analog for CI-documentation prose specifically; the planner should
treat that as free-form minimal prose edit consistent with the existing
section's tone, not a pattern-matched artifact.

## Metadata

**Analog search scope:** `.github/workflows/ci.yml`, `composer.json`,
`bin/composer-policy`, `tools/composer/ComposerPolicyCommandContract.php`
(referenced), `tests/Composer/ComposerPolicyGuardTest.php` (referenced via
RESEARCH.md line citations, not independently re-read this pass since
RESEARCH.md already extracted the load-bearing line numbers).
**Files scanned:** 3 read directly (`ci.yml`, `composer.json`,
`bin/composer-policy`); CONTEXT.md and RESEARCH.md supplied additional
line-cited excerpts reused here without re-reading.
**Pattern extraction date:** 2026-07-25
