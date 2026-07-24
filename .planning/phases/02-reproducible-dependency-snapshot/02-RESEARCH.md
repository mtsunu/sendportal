# Phase 2: Reproducible Dependency Snapshot - Research

**Researched:** 2026-07-24
**Domain:** Composer lockfile freeze / strict validation / platform check / locked security audit for a Laravel 11 + `mettle/sendportal-core` host on PHP 8.4, routed through a fail-closed guard
**Confidence:** HIGH (all three review checks executed on the real target: PHP 8.4.23 + Composer 2.10.2)

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions
- **D-01 (advisory exception resolution):** At lockfile review, run `composer audit --locked` against the frozen graph and verify whether the locked `laravel/framework` version still surfaces the three IDs (`PKSA-3r5d-mb8f-1qw9`, `PKSA-m5cs-t1y6-qpcs`, `PKSA-mdq4-51ck-6kdq`). **If patched in the locked version → drop the `ignore-id` entries entirely** (preferred). **If still present → retain exactly those three**, rewrite each justification to name the exact locked `laravel/framework` version, and reset the expiry to a real forward condition: "the next dependency-upgrade review, or when a stable `mettle/sendportal-core` release permits an upgraded Laravel line, whichever comes first." Reversibility: costly.
- **D-02:** Keep every other advisory blocking and failing. No package-wide ignores, no severity-wide ignores, no non-blocking audit mode, no platform/ignore bypass flags. Owner residual-risk acceptance carries forward for these three IDs only; do not broaden it.
- **D-03 (lockfile provenance):** Treat the on-disk `composer.lock` as a **candidate, not the deliverable**. Regenerate/normalize through the guarded path (`php bin/composer-policy update --prefer-dist --no-interaction`) on real PHP 8.4, confirm the resolved graph matches the Phase-1-approved resolution (no unexpected version drift), then commit. Prove synchronization with `composer validate --strict` before committing. Reversibility: costly.
- **D-04:** Remove `composer.lock` from `.gitignore` (`.gitignore:19`) so the reviewed lockfile can be tracked and committed. Enabling change for DEPS-01.
- **D-05:** Standard install = `install` against the committed lock, on every path; **do not invent new deployment automation**. Clarify README so ordinary operator installs use the guarded `install` (consumes the lock); reserve `update` for intentional dependency-upgrade work only.
- **D-06:** CI keeps the existing guarded `php bin/composer-policy install --no-scripts …` with dev dependencies present. Document the production deployment install as guarded `install --no-dev --optimize-autoloader` against the committed lock. Reversibility: reversible.
- **D-07:** Phase 2 **establishes, runs, and captures evidence** for the three review checks on real PHP 8.4 — `composer validate --strict` (DEPS-01), `composer check-platform-reqs` (DEPS-02), `composer audit --locked` (DEPS-03) — and documents them as the repeatable lockfile-review procedure (all routed through the Phase-1 guard **where a guarded route exists**). **Permanent CI gating and the dedicated PHP 8.4 CI job are out of scope (Phase 3 CI-01/CI-02).**

### Claude's Discretion
- Exact command invocations, flag ordering, and whether review commands are surfaced as `composer` script aliases, a README "Lockfile review" section, or both — choose the minimal reviewable form consistent with the Phase-1 guard.
- Exact wording of the refreshed advisory justifications/expiry, pinned to the real locked version observed at review time.
- Where the verification evidence is recorded (verification artifact vs. inline commit evidence).

### Deferred Ideas (OUT OF SCOPE)
- Dedicated PHP 8.4 CI job + permanent validate/platform/audit gating → Phase 3 (CI-01, CI-02).
- Laravel boot check and PHPUnit MySQL/PostgreSQL matrix on PHP 8.4 → Phase 3 (RUNTIME-01…04).
- Laravel major-version / security modernization → separate milestone.
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|------------------|
| DEPS-01 | A reviewed `composer.lock` is committed and remains synchronized with `composer.json` under `composer validate --strict`. | `validate --strict` runs through the guard and returns EXIT 0 today (§Validation Architecture). D-04 removes the gitignore entry; provenance proof in §Pattern 2. |
| DEPS-02 | The locked graph passes `composer check-platform-reqs` on PHP 8.4. | `check-platform-reqs --lock` passes (EXIT 0) on PHP 8.4.23 — every locked package's platform requirement is satisfied. **Guard does NOT allow this command** (Pitfall 1); route options in §Pattern 3. |
| DEPS-03 | The locked graph passes a non-bypassed dependency security check (`composer audit --locked` or equivalent). | `audit --locked` runs through the guard, EXIT 0, with exactly the three owner-approved advisories ignored and all others blocking. Resolves D-01 (§Pattern 1). |
| DEPS-04 | Standard local, CI, and deployment installation paths use `composer install` against the committed lockfile rather than fresh resolution. | `install` consumes a present lock (exact versions, no re-resolve) per official docs; CI already uses guarded `install`; README + deployment doc are the deliverable (§Pattern 4). |
</phase_requirements>

## Summary

This phase is a **freeze-and-prove** operation, not new development. The dependency graph was already resolved and security-vetted in Phase 1; the on-disk `composer.lock` (391 KB, currently untracked because `.gitignore:19` ignores it) is the *candidate* snapshot. Phase 2 regenerates/normalizes it through the guarded path on real PHP 8.4, proves `composer.json ↔ composer.lock` synchronization, proves the locked graph is PHP-8.4-clean and security-audited, resolves the expiring advisory exception, removes the gitignore entry, commits the lock, and documents the `install`-vs-`update` contract for local/CI/deployment.

All three review commands were executed during this research on the real target (**PHP 8.4.23, Composer 2.10.2**, the exact tracked distribution) and **all return EXIT 0 today**: `validate --strict`, `check-platform-reqs --lock`, and `audit --locked`. The locked `laravel/framework` version is **`v11.55.0`**, and `audit --locked` confirms **all three advisory IDs still affect it** — so per D-01 the **RETAIN** branch applies (not the DROP branch): keep exactly those three, rewrite each justification to name `v11.55.0`, and reset the expiry to the forward condition.

Two non-obvious hazards dominate the planning risk. **(1)** The guard's allowed-command contract is `[validate, audit, install, update]` — it **rejects `check-platform-reqs`**, so DEPS-02 has no guarded route today. **(2)** The exact advisory rationale string and the three IDs are **hardcoded inside `bin/composer-policy`** (`hasExactManifestPolicy()`), so rewriting the justification in `composer.json` (D-01) without a lockstep edit to the guard will make every guarded `install`/`update` fail closed with "Composer manifest policy rejected."

**Primary recommendation:** Snapshot the on-disk lock → regenerate via guarded `update --prefer-dist --no-interaction` → prove zero package-version drift + `validate --strict` EXIT 0 → rewrite the three advisory reasons/expiry **simultaneously in `composer.json` AND `bin/composer-policy`** to name `v11.55.0` → remove `.gitignore:19` → commit lock + manifest + guard together → run and capture all three review checks → update README (`install` vs `update`, `--no-dev` deployment). Run `check-platform-reqs --lock` directly against the tracked/verified `tools/composer/composer-2.10.2.phar` (read-only, cannot bypass any safeguard); optionally extend the guard.

## Architectural Responsibility Map

| Capability | Primary Tier | Secondary Tier | Rationale |
|------------|-------------|----------------|-----------|
| Dependency resolution & lock generation | Composer manifest/lock boundary (`composer.json` ↔ `composer.lock`) | Guard (`bin/composer-policy`) | The lock is the reproducibility contract; the guard mediates the only sanctioned `update`/`install` route. |
| Manifest↔lock sync proof | Composer (`validate --strict`) | Guard | Read-only metadata validation; guarded route exists. |
| Platform (PHP 8.4) verification of locked graph | Composer (`check-platform-reqs --lock`) | Tracked phar (direct) | Read-only; **no guarded route** — runs against the verified tracked distribution. |
| Security audit of locked graph | Composer (`audit --locked`) + `config.policy.advisories` | Guard | Native policy in `composer.json`; guarded route exists; enforces block/fail + scoped ignore. |
| Advisory-exception policy | `composer.json` `config.policy` **and** `bin/composer-policy` constant | — | The two are coupled; both encode the exact ignore-id map + rationale. |
| Install-path contract (local/CI/deploy) | Documentation (README) + CI workflow | Guard | Behavior is "use `install`, not `update`"; no new automation invented (D-05). |
| Lockfile tracking | VCS (`.gitignore`) | — | D-04 removes the ignore entry so the reviewed lock can be committed. |

## Standard Stack

This phase installs **no new packages** — it freezes the existing Phase-1-approved graph. The "stack" is the toolchain that freezes and verifies it.

### Core
| Tool | Version | Purpose | Why Standard |
|------|---------|---------|--------------|
| Composer | 2.10.2 | Resolution, lock, `validate`, `check-platform-reqs`, `audit`, `config.policy` | `[VERIFIED: tool]` System `composer --version` == `Composer version 2.10.2 2026-07-01`; identical to the tracked `tools/composer/composer-2.10.2.phar` the guard pins (`bin/composer-policy` COMPOSER_RELEASE). `config.policy` advisory schema requires the 2.10 line. |
| PHP | 8.4.23 (Herd, NTS) | Real target runtime for the resolve/verify | `[VERIFIED: tool]` `php --version` → `PHP 8.4.23 … Built by Laravel Herd`; matches the Phase-1 clean-environment evidence (STATE.md). |
| `bin/composer-policy` | Phase-1 guard | Fail-closed wrapper; allows only `validate`/`audit`/`install`/`update` | `[VERIFIED: codebase]` Read in full; `ComposerPolicyCommandContract::ALLOWED_COMMANDS`. Every sanctioned mutation/verification routes here. |

### Supporting (the frozen graph — informational, not to be re-resolved)
| Package | Locked Version | Notes |
|---------|----------------|-------|
| `laravel/framework` | `v11.55.0` | `[VERIFIED: tool]` `grep` of `composer.lock` line 1579. This exact version is the anchor for the D-01 advisory decision. |
| `mettle/sendportal-core` | `^3.0` (locked in graph) | Host/Core boundary fixed; do not upgrade/re-resolve. |
| Lock `plugin-api-version` | `2.9.0` | `[VERIFIED: tool]` `composer.lock` line 10585 — consistent with Composer 2.10. |
| Lock `content-hash` | `41abd56c5581800607cc9d3c28862a76` | `[VERIFIED: tool]` The value `validate --strict` checks the manifest against. |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| `audit --locked` native policy (DEPS-03) | Re-add a security-advisories metapackage | Rejected in Phase 1 (Roave conflicted with Laravel 11). The native `config.policy` is the accepted replacement — do not reverse. |
| Running `check-platform-reqs` direct against tracked phar | Extending the guard's allowed-command list | Extending touches Phase-1 verified security infra + its 164 KB guard test; direct read-only run is the minimal, no-bypass option (Pattern 3). |

**Installation:** No package install in this phase. Freeze command (guarded): `php bin/composer-policy update --prefer-dist --no-interaction` (regeneration only, per D-03). Consume command: `php bin/composer-policy install --prefer-dist --no-interaction`.

## Package Legitimacy Audit

**Not applicable — no external packages are introduced in this phase.** The dependency graph being frozen was resolved and security-vetted during Phase 1 (real PHP 8.4 solver evidence, native advisory policy). Phase 2 pins the existing graph; it does not add, upgrade, or discover packages. The security posture of the locked graph is proven by `composer audit --locked` (§DEPS-03), which is the correct legitimacy gate for a locked-graph freeze.

## Architecture Patterns

### System Architecture Diagram

```
                         composer.json  (require + config.policy.advisories)
                              │  content-hash
                              ▼
   [guarded update]  php bin/composer-policy update --prefer-dist --no-interaction
   (real PHP 8.4.23, Composer 2.10.2, isolated COMPOSER_HOME)
                              │  writes / normalizes
                              ▼
                        composer.lock  ◄── candidate on disk (untracked, .gitignore:19)
                              │
        ┌─────────────────────┼───────────────────────────────┐
        ▼                     ▼                                ▼
  validate --strict     check-platform-reqs --lock        audit --locked
  (DEPS-01, guarded)    (DEPS-02, NO guarded route →      (DEPS-03, guarded,
   json↔lock sync        run tracked phar direct)          config.policy enforced)
   EXIT 0                EXIT 0 on PHP 8.4                  EXIT 0, 3 ignored
        │                     │                                │
        └─────────────────────┴───── all green ───────────────┘
                              │
                     remove .gitignore:19 (D-04)
                     rewrite advisory reasons/expiry in
                     composer.json AND bin/composer-policy (D-01, lockstep)
                              │  git add + commit
                              ▼
                     tracked composer.lock  ──► consumed by:
                       • local:  guarded install (README)
                       • CI:     guarded install --no-scripts (ci.yml, dev deps kept)
                       • deploy: guarded install --no-dev --optimize-autoloader (documented)
```

### Recommended Change Surface (files this phase touches)
```
composer.json          # D-01: rewrite the 3 ignore-id reasons + forward expiry (name v11.55.0)
composer.lock          # D-03: regenerate via guarded update; commit (was untracked)
bin/composer-policy     # D-01 LOCKSTEP: update hardcoded $rationale constant (line ~365) to match composer.json
.gitignore             # D-04: delete line 19 ("composer.lock")
README.md              # D-05/D-06: clarify install-vs-update; add --no-dev --optimize-autoloader deploy note
.github/workflows/ci.yml # keep as-is (guarded install --no-scripts, dev deps) — do NOT add PHP 8.4 job (Phase 3)
```

### Pattern 1: Resolve the advisory exception (D-01) against the locked version — RETAIN branch
**What:** Decide whether to drop or retain the three `ignore-id` entries based on the *locked* `laravel/framework` version.
**When to use:** At lockfile review, exactly once, before committing.
**Verified result (this research):** Locked version is `v11.55.0`. `audit --locked` reports all three IDs as *ignored* advisories that *affect* the locked graph:

```
# Source: php bin/composer-policy audit --locked --no-interaction  (EXIT 0)
PKSA-m5cs-t1y6-qpcs  medium  Temporary Signed URL Path Confusion   Affected: <12.61.1|>=13.0.0,<13.12.0
PKSA-3r5d-mb8f-1qw9  high    CRLF injection in default email rule   Affected: <12.60.0|>=13.0.0,<=13.9.0
PKSA-mdq4-51ck-6kdq  (CVE-2026-48019)  CRLF injection                Affected: …|>=11.0.0,<12.0.0|…<12.60.0|>=13.0.0,<13.10.0
```
`v11.55.0` falls inside every one of those ranges → **all three still apply → RETAIN all three** (the DROP branch does NOT apply). `[VERIFIED: tool]`

**Action per D-01/D-02:** In `composer.json` `config.policy.advisories.ignore-id`, keep exactly these three keys; rewrite each reason to name `laravel/framework v11.55.0` and set expiry to *"the next dependency-upgrade review, or when a stable `mettle/sendportal-core` release permits an upgraded Laravel line, whichever comes first."* Do not add any other ID, package-wide, or severity-wide ignore. Keep `"block": true`, `"audit": "fail"`.

**Anti-pattern:** Leaving the self-referential "expires at Phase 2 lockfile review" text in the committed manifest — that clause is now consumed and would be dead/self-contradictory.

### Pattern 2: Prove lockfile provenance / zero drift (D-03)
**What:** Prove the regenerated lock equals the Phase-1-approved resolution.
**Steps (recommended):**
```bash
# 1. Preserve the candidate for a byte/content comparison
cp composer.lock /tmp/candidate-before.lock
# 2. Regenerate through the guarded path on real PHP 8.4
php bin/composer-policy update --prefer-dist --no-interaction
# 3. Drift signals (all must hold):
#    a) content-hash unchanged  → composer.json unchanged, json↔lock in sync
grep '"content-hash"' composer.lock            # expect 41abd56c5581800607cc9d3c28862a76 (unless D-01 edits land first)
#    b) identical package→version set → no re-resolution drift
diff <(grep -E '"name"|"version"' /tmp/candidate-before.lock) \
     <(grep -E '"name"|"version"' composer.lock)   # expect empty
#    c) strict validation passes
php bin/composer-policy validate --strict --no-interaction   # expect EXIT 0
```
**Distinguishing normalization from re-resolution:** An acceptable *normalization* changes only ordering/formatting/metadata while every `name→version` pair is identical (diff in 3b empty). A real *re-resolution* changes at least one locked version — that is drift and must be investigated against Phase-1 evidence before committing.
**Sequencing note:** If D-01 edits `composer.json` (advisory reasons) **before** regeneration, the `content-hash` will legitimately change (reasons live under `config`, which feeds the hash). To keep the drift check unambiguous, either (a) regenerate/prove-drift on the *pre-D-01* manifest first, then apply D-01 and re-run `validate --strict`, or (b) apply D-01, regenerate once, and rely on the package→version diff (3b) as the drift signal while `validate --strict` re-establishes sync. `[VERIFIED: tool]` `validate --strict` returns `./composer.json is valid` EXIT 0 on the current pair.

### Pattern 3: Route the platform check (DEPS-02) — no guarded route exists
**What:** `check-platform-reqs` verifies every locked package's platform requirement (`php`, `ext-*`) against the real running platform.
**Constraint:** The guard **rejects** `check-platform-reqs` (`ALLOWED_COMMANDS = [validate, audit, install, update]`). `[VERIFIED: tool]` `php bin/composer-policy check-platform-reqs --lock` → `Composer policy guard: Composer command rejected.` EXIT 1.
**Recommended (minimal, no-bypass):** Run directly against the tracked, integrity-verified distribution — it is read-only and cannot weaken any safeguard:
```bash
# Source: getcomposer.org/doc/03-cli.md — --lock checks the lock file, not vendor/
php tools/composer/composer-2.10.2.phar check-platform-reqs --lock --no-interaction   # EXIT 0
```
`[VERIFIED: tool]` returns `php 8.4.23 success` for every requirement, EXIT 0. D-07 explicitly permits this ("where a guarded route exists") — here none does, and the check has no bypass capability.
**Optional (heavier):** Add `check-platform-reqs` to the guard's `ALLOWED_COMMANDS` + `VALUELESS_GLOBAL_OPTIONS`/selectors so DEPS-02 gains a guarded route. This edits Phase-1 verified security code (`bin/composer-policy`, `tools/composer/ComposerPolicyCommandContract.php`) and its 164 KB `tests/Composer/ComposerPolicyGuardTest.php`; the guard-route tests run in CI (`ci.yml` "Verify Composer policy routes"). Only take this if a guarded route for DEPS-02 is judged worth the surface change.

### Pattern 4: Install-path contract (DEPS-04, D-05/D-06)
**What:** Document that ordinary installs consume the committed lock; `update` is reserved for intentional upgrades.
```bash
# Source: getcomposer.org/doc/03-cli.md — install uses exact versions from composer.lock when present
# Local operator (README):
php bin/composer-policy install --prefer-dist --no-interaction        # consumes lock, no re-resolve
# CI (ci.yml, unchanged — dev deps needed for the test job):
php bin/composer-policy install -q --no-ansi --no-interaction --no-scripts --no-progress --prefer-dist
# Production deployment (documented in README, no new script invented):
php bin/composer-policy install --no-dev --optimize-autoloader --no-interaction
# Intentional dependency upgrade ONLY:
php bin/composer-policy update --prefer-dist --no-interaction
```
`[CITED: getcomposer.org/doc/03-cli.md]` "If there is a `composer.lock` file … it will use the exact versions from there instead of resolving them." `--no-dev` disables dev deps; `--optimize-autoloader` builds a class map for production.

### Anti-Patterns to Avoid
- **Committing the stray on-disk lock without regenerating/validating** — violates D-03; the candidate's sync state is untrusted until `validate --strict` passes.
- **Editing `composer.json` advisory reasons without the lockstep `bin/composer-policy` edit** — breaks guarded `install`/`update` (Pitfall 2).
- **Using `update` in CI/deploy or operator docs** — re-resolves and defeats reproducibility (DEPS-04).
- **Adding the PHP 8.4 CI job or permanent validate/platform/audit gating** — Phase 3 scope (CI-01/CI-02); out of bounds here.
- **Any bypass flag** (`--ignore-platform-req(s)`, `--no-audit`, `--no-blocking`, `-d`/`--working-dir`) — the guard already rejects these; do not attempt them.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Prove manifest↔lock sync | A custom hash/JSON differ | `composer validate --strict` | Native content-hash + lock-freshness check; canonical for DEPS-01. |
| Prove PHP 8.4 platform fit of the locked graph | A script scraping each package's `require.php` | `composer check-platform-reqs --lock` | Native; reads real platform (ignores `config.platform`), covers `ext-*` too. |
| Security-audit the locked graph | A homegrown advisory lookup or re-adding Roave | `composer audit --locked` + `config.policy.advisories` | Native locked audit with scoped, documented `ignore-id`; Roave was the Phase-1 blocker. |
| Reproducible installs | A vendored `vendor/` tarball or bespoke pin script | Committed `composer.lock` + `install` | The lock is the standard reproducibility contract; `install` consumes it verbatim. |
| Deployment automation | A new deploy script | Documented guarded `install --no-dev --optimize-autoloader` | D-05: do not invent automation; the guarded command already exists. |

**Key insight:** Every requirement in this phase maps to a single native Composer command already available in the tracked 2.10.2 distribution. The only "build" work is (a) the D-01 advisory-reason rewrite in two coupled files and (b) documentation — nothing bespoke.

## Runtime State Inventory

This is a config/VCS change, not a rename/refactor with stored runtime state. One VCS-state item is relevant:

| Category | Items Found | Action Required |
|----------|-------------|------------------|
| Stored data | None — no datastore keys reference the lockfile. | None. |
| Live service config | None. | None. |
| OS-registered state | None. | None. |
| Secrets/env vars | None — `.env` handling is unrelated to the lock freeze. | None. |
| Build artifacts / VCS state | `composer.lock` is present on disk but **untracked** (`.gitignore:19` ignores it). `[VERIFIED: tool]` `git ls-files composer.lock` returns empty. | D-04: delete `.gitignore:19`, then `git add composer.lock` so the reviewed snapshot is tracked. Without this, the "committed lock" (DEPS-01/DEPS-04) does not exist and installs keep re-resolving. |

## Common Pitfalls

### Pitfall 1: The guard rejects `check-platform-reqs` — DEPS-02 has no guarded route
**What goes wrong:** A plan that routes DEPS-02 as `php bin/composer-policy check-platform-reqs …` fails with `Composer command rejected.` (EXIT 1).
**Why it happens:** `ComposerPolicyCommandContract::ALLOWED_COMMANDS` is `[validate, audit, install, update]` only. `[VERIFIED: codebase]`
**How to avoid:** Run `check-platform-reqs --lock` directly against `tools/composer/composer-2.10.2.phar` (read-only; Pattern 3), or deliberately extend the guard (heavier). D-07's "where a guarded route exists" anticipates this gap.
**Warning signs:** Any planned command of the form `bin/composer-policy <not validate/audit/install/update>`.

### Pitfall 2: Advisory rationale is hardcoded in the guard — rewriting D-01 without lockstep breaks installs
**What goes wrong:** After D-01 rewrites the three `ignore-id` reasons/expiry in `composer.json`, every guarded `install`/`update` fails with `Composer manifest policy rejected.`
**Why it happens:** `bin/composer-policy` `hasExactManifestPolicy()` hardcodes the **exact** rationale string (one occurrence, ~line 365) and the exact three-ID map, and compares `config.policy.advisories.ignore-id === $expectedIgnoreIds` with strict equality. `install`/`update` run this check (lines ~403 and ~422); `validate`/`audit` do not. `[VERIFIED: codebase]`
**How to avoid:** Edit the rationale in `composer.json` **and** the `$rationale` constant in `bin/composer-policy` in the same change so the strings are byte-identical. Then run a guarded `install`/`update` to confirm the policy still passes.
**Warning signs:** `validate --strict` and `audit --locked` pass but `install`/`update` reject the manifest policy — that asymmetry means the guard constant drifted from `composer.json`. Note: the 164 KB `tests/Composer/ComposerPolicyGuardTest.php` does **not** hardcode the rationale string or the IDs (`grep` count 0), so it will not independently flag the drift — the guarded install is the detector. `[VERIFIED: tool]`

### Pitfall 3: `content-hash` drift when D-01 edits the manifest
**What goes wrong:** A drift check that expects `content-hash` to stay `41abd56c…` fails after the D-01 advisory-reason edit.
**Why it happens:** `config` (including `policy.advisories`) feeds the content-hash; changing the reasons legitimately changes the hash. This is expected, not drift.
**How to avoid:** Sequence per Pattern 2 — use the package→version-set diff (not the raw hash) as the drift signal once D-01 lands, and re-establish sync with `validate --strict`.
**Warning signs:** `validate --strict` still EXIT 0 (sync fine) but the hash differs from the pre-edit value — that is the manifest edit, not a re-resolution.

### Pitfall 4: Installing before `.gitignore:19` is removed / lock is committed
**What goes wrong:** A fresh clone (or a machine that never ran `update`) has no tracked lock and re-resolves the graph, defeating reproducibility.
**Why it happens:** Until D-04 lands and the lock is committed, `composer.lock` is ignored/untracked.
**How to avoid:** Land D-04 and commit the validated lock in the same phase deliverable; only then does DEPS-04's "install against the committed lock" hold.

### Pitfall 5: `minimum-stability: dev` misread as unstable lock
**What goes wrong:** A reviewer flags `"minimum-stability": "dev"` in `composer.json` as a reproducibility risk.
**Why it happens:** The setting was needed historically; with `"prefer-stable": true` and a committed lock, `install` uses the exact locked versions regardless. `validate --strict` passing (EXIT 0) confirms the pair is consistent. `[VERIFIED: tool]` Do not change stability settings in this phase (out of D-03 minimal scope) unless a review explicitly requires it.

## Code Examples

### The full lockfile-review procedure (repeatable evidence, D-07)
```bash
# Source: executed in this research on PHP 8.4.23 + Composer 2.10.2 — all EXIT 0
# DEPS-01 — manifest↔lock sync (guarded)
php bin/composer-policy validate --strict --no-interaction
#   → ./composer.json is valid            (EXIT 0)

# DEPS-02 — PHP 8.4 platform fit of the LOCKED graph (no guarded route → tracked phar)
php tools/composer/composer-2.10.2.phar check-platform-reqs --lock --no-interaction
#   → php 8.4.23 success (per requirement) (EXIT 0)

# DEPS-03 — locked security audit with scoped ignore (guarded)
php bin/composer-policy audit --locked --no-interaction
#   → Found 3 ignored security vulnerability advisories affecting 1 package  (EXIT 0)
#   (optional compact evidence form:)
php bin/composer-policy audit --locked --format=summary --no-interaction
```

### DEPS-04 install contract (documentation targets)
```bash
php bin/composer-policy install --prefer-dist --no-interaction                 # local operator (README)
php bin/composer-policy install --no-dev --optimize-autoloader --no-interaction # production deploy (README)
# update is reserved for intentional dependency-upgrade work only.
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| `roave/security-advisories` metapackage as the security gate | Composer 2.10 native `config.policy.advisories` (`block`/`audit`/`ignore-id`) + `audit --locked` | Phase 1 (this milestone) | Removes the Roave↔Laravel-11 conflict; the locked audit is now the gate. Do not reverse. |
| Legacy `config.audit.ignore` (map of id→reason) | `config.policy.advisories.ignore-id` (with `block`/`audit` scoping) | Composer 2.10 | `[CITED: getcomposer.org/doc/06-config.md]` describes `config.audit` as legacy and `config.policy.advisories` as the modern form; the guard forbids the legacy `config.audit` key entirely. |
| No lockfile (each install re-resolves) | Committed, reviewed `composer.lock` consumed by `install` | Phase 2 (this phase) | Machine-to-machine reproducibility (PROJECT.md reproducibility constraint). |

**Deprecated/outdated:**
- `config.audit.ignore` — superseded by `config.policy.advisories.ignore-id`; the guard actively rejects a `config.audit` key.

## Assumptions Log

| # | Claim | Section | Risk if Wrong |
|---|-------|---------|---------------|
| A1 | `check-platform-reqs` failure exit code is non-zero (official docs don't state it explicitly); relied on EXIT 0 = pass observed here. | Pattern 3 / Validation | Low — the pass signal (EXIT 0 + "success" rows) is directly observed; a future *failing* case is Phase-3 CI concern. |
| A2 | The `config.policy.advisories` `block`/`audit` scoping semantics match the docs' `on-block`/`on-audit` description. | State of the Art | Low — observed behavior (3 ignored, EXIT 0, all others would block) confirms the effective policy; exact schema naming is bleeding-edge (Composer 2.10, released 2026-07-01, post training cutoff). |

**Everything else is `[VERIFIED: tool]` (executed on the real target) or `[CITED]` from official Composer docs.**

## Open Questions (RESOLVED)

1. **Guarded route for DEPS-02?**
   - What we know: `check-platform-reqs` is rejected by the guard; running it against the tracked phar is read-only and safe.
   - What's unclear: Whether the owner wants a *guarded* route (extend the contract) vs. the minimal direct-phar run.
   - Recommendation: Default to the direct tracked-phar run (Pattern 3); surface guard extension as an explicit, optional planner decision because it edits Phase-1 verified security code + its CI-run tests.
   - **RESOLVED:** Plan 02-02 Task 2 adopts the recommended minimal direct tracked-phar run (`php tools/composer/composer-2.10.2.phar check-platform-reqs --lock`); the guard is NOT extended, honoring the "smallest change" boundary.

2. **Where to record evidence (Claude's Discretion, D-07).**
   - Recommendation: Capture the three command outputs (EXIT codes + the audit table) inline in the phase VERIFICATION/commit evidence; optionally add a short README "Lockfile review" subsection listing the three commands so the procedure is repeatable.
   - **RESOLVED:** Plans capture evidence inline (SUMMARY/commit evidence) AND add a README "Lockfile review" subsection listing the three review commands so the procedure is repeatable (Plan 02-02 Task 2).

## Environment Availability

| Dependency | Required By | Available | Version | Fallback |
|------------|------------|-----------|---------|----------|
| PHP | All review checks | ✓ | 8.4.23 (Herd, NTS) | — |
| Composer (system) | Direct `check-platform-reqs` | ✓ | 2.10.2 | Tracked phar (identical) |
| Tracked Composer phar | Guard + direct platform check | ✓ | `tools/composer/composer-2.10.2.phar` (integrity record present) | — |
| `bin/composer-policy` guard | `validate`/`audit`/`install`/`update` | ✓ | Phase-1 | — |
| `composer.lock` (on disk) | Freeze/validate/commit | ✓ | 391 KB candidate, untracked | Regenerate via guarded `update` |
| git | Track/commit the lock | ✓ | repo present | — |

**Missing dependencies with no fallback:** None.
**Missing dependencies with fallback:** `check-platform-reqs` has no guarded route → fall back to the tracked phar (read-only).

## Validation Architecture

> Nyquist validation is enabled (`workflow.nyquist_validation: true`).

### Test Framework
| Property | Value |
|----------|-------|
| Framework | Composer 2.10.2 native commands (verification is command-exit-code based, not PHPUnit); PHPUnit 10.5 exists but its runtime matrix is Phase 3. |
| Config file | `composer.json` (manifest + `config.policy`), `composer.lock` (the frozen graph) |
| Quick run command | `php bin/composer-policy validate --strict --no-interaction` |
| Full suite command | The three-command review procedure below (validate + check-platform-reqs + audit) |

### Phase Requirements → Test Map
| Req ID | Behavior | Test Type | Command | Expected signal | Route exists? |
|--------|----------|-----------|---------|-----------------|---------------|
| DEPS-01 | `composer.json`↔`composer.lock` synchronized, committed | manifest validation | `php bin/composer-policy validate --strict --no-interaction` | `./composer.json is valid`, EXIT 0; `git ls-files composer.lock` non-empty after commit | ✅ guarded (verified EXIT 0) |
| DEPS-02 | Locked graph is PHP-8.4 platform-clean | platform check | `php tools/composer/composer-2.10.2.phar check-platform-reqs --lock --no-interaction` | every row `… success`, no `failed`/`missing`, EXIT 0 | ⚠️ no guarded route — tracked phar (verified EXIT 0) |
| DEPS-03 | Locked graph passes non-bypassed audit; only the 3 owner-approved IDs ignored | security audit | `php bin/composer-policy audit --locked --no-interaction` | EXIT 0; output = `Found 3 ignored … advisories affecting 1 package`; no *non-ignored* advisory, no blocking abandoned | ✅ guarded (verified EXIT 0) |
| DEPS-04 | Local/CI/deploy install from the committed lock, not fresh resolve | contract + doc | `php bin/composer-policy install --prefer-dist --no-interaction` (consumes lock); README documents `install` vs `update` + `--no-dev --optimize-autoloader`; `ci.yml` unchanged guarded `install --no-scripts` | install reports installing from lock (no "Updating dependencies"/no lock write); README/ci.yml reviewed | ✅ guarded (CI already uses it) |
| D-01 | Advisory exception resolved: 3 IDs retained, reasons name `v11.55.0`, forward expiry, guard constant in lockstep | policy consistency | Re-run `audit --locked` (reasons updated) **and** a guarded `install`/`update` (proves `hasExactManifestPolicy` still passes) | audit shows updated ignore reasons; guarded install does NOT emit `Composer manifest policy rejected` | ✅ guarded |

### Sampling Rate
- **Per task commit:** `validate --strict` (fast sync check).
- **Per wave / pre-commit of the lock:** full three-command review procedure + a guarded `install`/`update` smoke (catches Pitfall 2).
- **Phase gate:** all three review checks EXIT 0 on PHP 8.4 with evidence captured, before `/gsd-verify-work`.

### Wave 0 Gaps
- None — verification is native-command exit-code based and all commands are present and passing today. No new test files required. (The PHP 8.4 CI job that would automate these permanently is Phase 3 / CI-01, explicitly out of scope.)

## Security Domain

> `security_enforcement: true`, ASVS level 1. This phase changes dependency/security *policy*, not application code paths.

### Applicable ASVS Categories
| ASVS Category | Applies | Standard Control |
|---------------|---------|-----------------|
| V1 Architecture / Dependencies | yes | Committed, audited `composer.lock`; `composer audit --locked` gate; `config.policy.advisories` (block/fail). |
| V5 Input Validation | no | No new request-handling code. |
| V6 Cryptography | no (indirect) | Guard verifies the tracked Composer phar via SHA-256 before use (`verifiedComposerPath`). Do not weaken. |
| V14 Configuration | yes | `.gitignore`, `config.policy`, and the guard constant are the security-relevant config surface; keep the three-ID scope; no bypass flags. |

### Known Threat Patterns for this change
| Pattern | STRIDE | Standard Mitigation |
|---------|--------|---------------------|
| Silently broadening the advisory exception (extra/severity-wide/package-wide ignore) | Repudiation / Elevation | D-02: keep exactly the three IDs; guard strict-equality on the ignore-id map enforces it. |
| Advisory reason drift between manifest and guard → fail-open or fail-closed surprise | Tampering | Lockstep edit `composer.json` + `bin/composer-policy` (Pitfall 2); verify via guarded install. |
| Committing an unvalidated/drifted lock | Tampering | D-03 provenance proof (Pattern 2) + `validate --strict` before commit. |
| Using a bypass flag to force a "green" install | Elevation | Guard rejects `--ignore-platform-req(s)`, `--no-audit`, `--no-blocking`, `-d`, `--working-dir`; do not attempt. |

## Sources

### Primary (HIGH confidence — executed this session, PHP 8.4.23 + Composer 2.10.2)
- `php bin/composer-policy validate --strict` → `./composer.json is valid`, EXIT 0.
- `php tools/composer/composer-2.10.2.phar check-platform-reqs --lock` → all `success`, EXIT 0.
- `php bin/composer-policy audit --locked` → 3 ignored advisories, EXIT 0 (affected ranges captured).
- `php bin/composer-policy check-platform-reqs` → `Composer command rejected` EXIT 1 (guard gap confirmed).
- `grep composer.lock` → `laravel/framework v11.55.0`; `content-hash 41abd56c…`; `plugin-api-version 2.9.0`.
- `git ls-files composer.lock` → empty (untracked).
- Full reads of `bin/composer-policy`, `tools/composer/ComposerPolicyCommandContract.php`, `composer.json`, `.github/workflows/ci.yml`, `README.md`, `.gitignore`.

### Secondary (MEDIUM confidence — official docs)
- [Composer CLI](https://getcomposer.org/doc/03-cli.md) — `validate --strict` lock-freshness, `check-platform-reqs --lock`, `audit --locked`/`--abandoned`/`--format`, `install` vs `update`, exit codes.
- [Composer config](https://getcomposer.org/doc/06-config.md) — `config.audit.ignore` (legacy) vs `config.policy.advisories.ignore-id` (modern), `abandoned` values.

## Metadata

**Confidence breakdown:**
- Standard stack / commands: HIGH — every command executed on the exact target and passing.
- Advisory decision (D-01 RETAIN): HIGH — locked version and affected ranges directly observed.
- Guard coupling / DEPS-02 gap pitfalls: HIGH — read from source and reproduced.
- Composer 2.10 `config.policy` exact schema naming: MEDIUM — behavior verified; version is post-training-cutoff, doc naming CITED.

**Research date:** 2026-07-24
**Valid until:** ~2026-08-23 (stable toolchain; re-verify the advisory ranges if `laravel/framework` is re-resolved or a new advisory publishes, and re-run `audit --locked` at any lock change).

## RESEARCH COMPLETE

**Phase:** 2 — Reproducible Dependency Snapshot
**Confidence:** HIGH

### Key Findings
- All three review checks pass EXIT 0 **today** on the real target (PHP 8.4.23, Composer 2.10.2): `validate --strict`, `check-platform-reqs --lock`, `audit --locked`.
- Locked `laravel/framework` is **`v11.55.0`**, and all three advisory IDs still affect it → **D-01 RETAIN branch** (rewrite reasons to name `v11.55.0` + forward expiry; do NOT drop).
- **Pitfall:** the guard **rejects `check-platform-reqs`** (no guarded route) → run it against the tracked verified phar (read-only, no bypass).
- **Pitfall:** the advisory rationale + three IDs are **hardcoded in `bin/composer-policy`** → the D-01 rewrite must edit `composer.json` **and** the guard constant in lockstep, or guarded `install`/`update` fail closed.
- `composer.lock` is on disk but **untracked** (`.gitignore:19`); D-04 removes the ignore entry so the reviewed lock can be committed (DEPS-01/DEPS-04).

### File Created
`/Users/meigire/Work/idai-jatim/sendportal/.planning/phases/02-reproducible-dependency-snapshot/02-RESEARCH.md`

### Confidence Assessment
| Area | Level | Reason |
|------|-------|--------|
| Standard Stack / commands | HIGH | Executed on the exact target; all EXIT 0. |
| Architecture (freeze/validate/commit + install contract) | HIGH | Grounded in official Composer docs + guard source. |
| Pitfalls | HIGH | Guard gap and rationale coupling reproduced from source. |

### Open Questions
- Whether to give DEPS-02 a guarded route (extend the guard) vs. the minimal tracked-phar run — recommended default is the tracked-phar run.
- Where to record the three-command evidence (inline verification vs. README "Lockfile review" section) — Claude's Discretion.

### Ready for Planning
Research complete. The planner can create PLAN.md files; note the two lockstep hazards (guard command gap for DEPS-02, hardcoded rationale for D-01) and sequence D-01/D-03/D-04 + the guard edit as described.
