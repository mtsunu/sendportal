# Pitfalls Research

**Domain:** PHP 8.4 compatibility for a Laravel 11 Composer application
**Researched:** 2026-07-22
**Confidence:** MEDIUM

## Evidence Scope

The most important claims below are cross-checked against official Composer, PHP, Laravel, and GitHub Actions documentation. Confidence is **MEDIUM**, rather than HIGH, because the current environment could not re-run a complete Composer solve: Packagist DNS resolution failed. The project record already captures the reported `roave/security-advisories` / `illuminate/mail` conflict, but the final blocker tree and exact safe versions must be produced in the implementation phase on a networked PHP 8.4 runner.

## Critical Pitfalls

### Pitfall 1: Bypassing the advisory conflict instead of resolving the vulnerable constraint

**What goes wrong:**
The team unblocks `composer update` with `--no-blocking`, `--no-security-blocking`, `--ignore-platform-reqs`, or a broad advisory ignore, then commits a graph that either contains the vulnerable `illuminate/mail` range or was never proven compatible. The current `roave/security-advisories: dev-master` is a conflict-only metapackage, so its refusal is a security signal, not application code that can be safely patched around.

**Why it happens:**
`roave/security-advisories` changes as advisories are published and its `dev-master` constraint makes a newly discovered conflict appear abrupt. Composer's current built-in policy/audit facilities also mean a migration may be mistaken for a reason to silently remove the safeguard.

**How to avoid:**

- Use `composer prohibits illuminate/mail <candidate-version> -t` and the solver output to identify the precise blocked Laravel/Illuminate range; update the smallest compatible set of direct roots (normally Laravel and first-party Laravel packages) with `-W`, then review the complete lock diff.
- Keep a security control as an explicit acceptance criterion: either retain the advisory metapackage after selecting patched versions, or replace it only after pinning a Composer version that supports the chosen native policy and making `composer audit` a failing CI step.
- Never use `--no-blocking`, `--no-security-blocking`, or `--ignore-platform-reqs` in the resolution, CI, or release path. Any narrowly scoped advisory exception must name the CVE/GHSA/PKSA, explain the compensating control, have an owner, and expire.

**Warning signs:**

- A proposed fix removes `roave/security-advisories` without adding a failing `composer audit` / policy control.
- CI output contains an ignore-platform or no-blocking flag, or an advisory exception without an identifier and expiry.
- The update touches `illuminate/*`, Symfony, PHPUnit, or SendPortal Core but the lock diff and audit output are not attached to review.

**Phase to address:**
Phase 1 — **Constraint resolution and security control**.

---

### Pitfall 2: Changing the PHP range without understanding its real semantics

**What goes wrong:**
The manifest is edited to “add 8.4” but accidentally narrows supported versions, or the team assumes the PHP declaration proves that all packages and runtime extensions work. `^8.2` already permits PHP 8.4 (up to, but excluding, PHP 9); the current `^8.2|^8.3` is redundant rather than PHP-8.4-exclusive. Laravel 11 itself documents PHP 8.2–8.4 support.

**Why it happens:**
Human-readable version lists are confused with Composer constraints, and a solve performed on PHP 8.4 can select a transitive version that later excludes PHP 8.2 or 8.3 while the root constraint still advertises them.

**How to avoid:**

- First decide the compatibility contract: retain 8.2–8.4, or deliberately raise the minimum. Express the smallest accurate constraint; do not add redundant OR branches merely for display.
- If 8.2/8.3 remain supported, resolve against the lowest supported PHP platform (or a real 8.2 job), then run `composer check-platform-reqs` on every real runtime. Composer warns that `config.platform` emulates a platform and can hide a mismatch on the actual machine, so it is a solver guard—not a runtime test.
- Verify PHP extensions required by Laravel and the mail/database stack in each CI container, not just the `php` version.

**Warning signs:**

- The change replaces `^8.2|^8.3` with `^8.4` without an explicit product decision to drop 8.2/8.3.
- A lock generated on 8.4 cannot install on an advertised lower runtime, or `check-platform-reqs` fails there.
- The rationale says “Laravel 11 does not support 8.4”; its official 11.x release table says otherwise.

**Phase to address:**
Phase 1 — **Constraint resolution and security control**, with verification completed in Phase 3.

---

### Pitfall 3: Treating a successful solver run as PHP 8.4 application compatibility

**What goes wrong:**
Dependencies install but the application, queued mail, or SendPortal Core integration fails at bootstrap or in production. PHP 8.4 contains behavior changes and deprecations that a Composer constraint cannot model, including changed `exit`/`die` type behavior, implicitly nullable parameter deprecations, resource-to-object migrations, and typed extension constants.

**Why it happens:**
The host repository's tests mainly cover authentication, setup, workspaces, and token resolution, while the primary campaign, mail, webhook, and API behaviors are supplied by `mettle/sendportal-core`.

**How to avoid:**

- Run a real PHP 8.4 verification path after installation: `artisan about`, configuration/cache bootstrap, migrations against both configured databases, and PHPUnit with deprecations surfaced rather than discarded.
- Add a small integration smoke suite for the core package's route registration, mail/queue handoff, webhook/public API boot, and one tenant-scoped campaign/subscriber path before accepting the dependency update.
- Keep the PHP 8.2 and 8.3 jobs if the manifest continues to claim them; PHP 8.4 coverage is additive, not a replacement.

**Warning signs:**

- “Composer install succeeds” is the only PHP 8.4 evidence.
- CI runs the suite only with `--no-scripts`, skips migration/route boot checks, or has no test that reaches SendPortal Core.
- PHP 8.4 emits deprecations/errors during boot, package discovery, queue/mail startup, or a database-engine-specific test leg.

**Phase to address:**
Phase 3 — **PHP 8.4 runtime and integration verification**.

---

### Pitfall 4: Generating a lockfile without a controlled resolution and review boundary

**What goes wrong:**
The first lockfile is created with a broad root update, silently advancing Laravel, Symfony, Livewire, Horizon, PHPUnit, and SendPortal Core together. Alternatively, a hand-edited or stale lock is committed and CI appears green only because it resolves fresh packages or ignores the mismatch.

**Why it happens:**
The application currently has no `composer.lock`, uses wide caret constraints, and permits global `minimum-stability: dev`; every install has been a fresh moving solve.

**How to avoid:**

- Make the first lockfile an intentional dependency-change review: update only the required root packages with `-W`, retain `prefer-stable`, inspect all direct/transitive changes, then run `composer validate --strict`, `composer audit`, and the test matrix before committing.
- Commit the generated lockfile. Thereafter, CI and deployments must run `composer install --prefer-dist --no-interaction` from that lock; dependency freshness belongs in a separate scheduled update pull request, not ordinary test jobs.
- Reconsider global `minimum-stability: dev`. If only the advisory tool needs a dev branch, use a stable project-wide policy and an explicit per-package dev constraint, or migrate to the selected native Composer security control after its CI version floor is enforced.

**Warning signs:**

- `composer.json` changes without a matching lock hash update; `composer validate --strict` reports it.
- CI logs say “Updating dependencies” on a normal branch test, or no lockfile exists after the purported fix.
- The lock diff includes unexpected major/minor dependency movement or a new dev package unrelated to the compatibility task.

**Phase to address:**
Phase 2 — **Reproducible dependency snapshot**.

---

### Pitfall 5: Making CI cover “PHP 8.4” in name only

**What goes wrong:**
A new matrix label is added but the chosen test-runner image lacks PHP 8.4, Composer, required extensions, or the intended database drivers. The PHP 8.4 test may also replace earlier versions or inadvertently test only one service, leaving the claimed compatibility range and cross-database behavior unverified.

**Why it happens:**
The current workflow keys the entire job container to two third-party images (8.2 and 8.3), runs both MySQL and PostgreSQL service containers, and installs with `--no-scripts`. Updating only the matrix value does not prove the image's toolchain or application bootstrap behavior.

**How to avoid:**

- Add a real 8.4 container image only after pinning/verifying its digest or supported tag, then assert `php --version`, `composer --version`, `php -m`, and `composer check-platform-reqs` in the job.
- Run the existing MySQL and PostgreSQL PHPUnit legs for PHP 8.4, retain 8.2/8.3 while advertised, and use the job's `mysql`/`postgres` service hostnames (the documented job-container network model).
- Test both the current fast dependency install path and a script-enabled bootstrap check. `--no-scripts` avoids side effects, but it also bypasses Laravel's package-discovery hook and cannot by itself prove a deployable install.
- Cache only Composer's downloaded-package cache, keyed by `composer.lock`; never cache `vendor/` across lockfiles or store credentials/auth files in Actions cache.

**Warning signs:**

- The 8.4 matrix fails before PHPUnit with missing extensions, a missing database driver, or an image/tag pull failure.
- `php --version` in the supposed 8.4 job reports a different runtime.
- One database pass masks failures in the other, or the script-enabled application bootstrap is never exercised.

**Phase to address:**
Phase 3 — **PHP 8.4 CI and runtime verification**.

## Technical Debt Patterns

| Shortcut | Immediate Benefit | Long-term Cost | When Acceptable |
|----------|-------------------|----------------|-----------------|
| `--ignore-platform-reqs` / `--no-blocking` to get a lock | A locally installable vendor tree | Conceals the exact PHP/security incompatibility; release artifact is untrustworthy | Never for this milestone |
| Delete Roave without a replacement | Solver may proceed | Known-vulnerable dependencies can re-enter later updates | Only after a version-pinned native Composer policy plus failing audit CI is verified |
| Keep no lockfile during compatibility work | No large generated diff | Non-reproducible builds and invisible dependency drift | Never for a deployable application |
| Use `config.platform` as the sole PHP test | Solver can target a lower runtime | Actual PHP/extensions may differ and fail at runtime | Only as a solver guard, alongside real-runtime checks |
| Cache `vendor/` without a strict lockfile key | Faster CI | Cross-version stale code and harder-to-explain failures | Avoid; cache downloaded Composer artifacts instead |

## Integration Gotchas

| Integration | Common Mistake | Correct Approach |
|-------------|----------------|------------------|
| Composer + Packagist advisory feed | Treat a newly surfaced conflict as an outage to suppress | Diagnose the blocked range, select patched compatible versions, audit the resolved graph, and preserve a security control |
| Laravel package discovery | Only use `composer install --no-scripts` in CI | Keep the safe install path but add an explicit script-enabled bootstrap/package-discovery verification job or step |
| GitHub Actions service containers | Use `localhost` from a job container or change database coverage while adding PHP 8.4 | Use the service labels (`mysql`, `postgres`) from the job container and run both database legs |
| SendPortal Core | Trust host-only tests after a core-package update | Add core route/queue/mail/API smoke coverage tied to the locked package release |

## Performance Traps

| Trap | Symptoms | Prevention | When It Breaks |
|------|----------|------------|----------------|
| Fresh dependency solving in every CI run | Slow, nondeterministic installs and intermittent solver failures | Commit the lockfile and use `composer install`; schedule updates separately | Immediately; behavior can change on any registry/advisory update |
| Unkeyed `vendor/` cache | Tests use code inconsistent with `composer.lock` | Cache Composer downloads with a lockfile-based key, regenerate `vendor` | On the first dependency or PHP-image change |
| Rebuilding two database suites for every experimental dependency solve | Long feedback cycle hides the actual compatibility failure | Resolve once, then use the lockfile and run a focused solver/audit check before the full matrix | As the matrix expands to PHP 8.4 and beyond |

## Security Mistakes

| Mistake | Risk | Prevention |
|---------|------|------------|
| Ignore all Composer platform requirements | Runtime executes packages unsupported by PHP 8.4 or required extensions are absent | Keep Composer platform checks on and run `composer check-platform-reqs` in each real target |
| Disable advisory blocking/audit to evade Roave | Vulnerable Illuminate/Mail or transitive code enters the release | Resolve to a patched graph; retain Roave or pin a native Composer policy and fail CI audit |
| Cache Composer auth/config or secrets in Actions | Pull-request-accessible cache can disclose credentials | Cache only dependency downloads; keep auth outside cached paths |
| Test only with `--no-scripts` | Deployment hooks/package discovery can fail after release | Add a controlled script-enabled bootstrap check in CI |

## "Looks Done But Isn't" Checklist

- [ ] **Constraint repair:** Solver output identifies why the selected Laravel/Illuminate version is compatible with the advisory policy; no ignore/no-blocking flag is present.
- [ ] **PHP support declaration:** The team has documented whether 8.2 and 8.3 remain supported; the chosen constraint has been evaluated with Composer caret semantics, not visually inferred.
- [ ] **Lockfile:** `composer.lock` is committed, `composer validate --strict` passes, and normal CI uses `composer install` rather than a fresh update.
- [ ] **Security control:** `composer audit` (or a pinned equivalent policy) runs and fails CI appropriately; any exception is advisory-ID-specific and time bounded.
- [ ] **Runtime proof:** PHP 8.4 runs script-enabled Laravel bootstrap, migrations, and both MySQL/PostgreSQL tests; 8.2/8.3 remain covered if declared.
- [ ] **Core integration:** At least one SendPortal Core route/mail/queue/API smoke test runs against the newly locked release.

## Recovery Strategies

| Pitfall | Recovery Cost | Recovery Steps |
|---------|---------------|----------------|
| Advisory conflict was bypassed and insecure lock committed | HIGH | Revert the lock/manifest change, identify the advisory and blocked range, update to a patched compatible Laravel set, audit, then regenerate/review the lockfile |
| PHP 8.4 CI image cannot run the suite | MEDIUM | Verify image PHP/Composer/extensions/drivers, replace or build a deterministic image, run both database services, and keep prior PHP legs active |
| Lock excludes an advertised lower PHP version | MEDIUM | Re-resolve with the documented lowest target platform, run real lower-version jobs and `check-platform-reqs`, then commit the corrected lock |
| Scriptless CI hid package-discovery failure | MEDIUM | Add a script-enabled bootstrap check, repair provider/discovery failures, and retain the no-scripts install only where deliberately needed |
| Broad first lockfile update regressed SendPortal behavior | HIGH | Restore the last known manifest/lock, repeat with the smallest root update set and `-W`, then add the missing core integration regression test |

## Pitfall-to-Phase Mapping

| Pitfall | Prevention Phase | Verification |
|---------|------------------|--------------|
| Advisory conflict bypass / lost security gate | Phase 1 — Constraint resolution and security control | `prohibits` evidence, patched graph, audit/policy failure test, no bypass flags |
| Incorrect PHP support range or lower-version drift | Phase 1 then Phase 3 | Constraint review, real PHP 8.2/8.3/8.4 install/test and `check-platform-reqs` when those versions are supported |
| Unreviewed or stale lockfile | Phase 2 — Reproducible dependency snapshot | `composer validate --strict`, reviewed lock diff, CI `composer install` from committed lock |
| PHP 8.4 boot/core integration failure | Phase 3 — PHP 8.4 runtime and integration verification | Script-enabled bootstrap, migrations, host + core smoke tests, both database engines |
| Nominal-only PHP 8.4 CI matrix | Phase 3 — PHP 8.4 CI and runtime verification | Image/toolchain assertions, MySQL/PostgreSQL passes, lockfile-keyed safe cache |

## Sources

- [Composer: basic usage and lockfiles](https://getcomposer.org/doc/01-basic-usage.md) — primary; **MEDIUM** provider confidence after direct official-source verification.
- [Composer: CLI (`prohibits`, `-W`, validate, platform-bypass flags)](https://getcomposer.org/doc/03-cli.md) — primary; **MEDIUM** provider confidence after direct official-source verification.
- [Composer: platform configuration and `check-platform-reqs`](https://getcomposer.org/doc/06-config.md) and [platform dependencies](https://getcomposer.org/doc/articles/composer-platform-dependencies.md) — primary; **MEDIUM** provider confidence after direct official-source verification.
- [Composer: version constraints](https://getcomposer.org/doc/articles/versions.md) — primary; **MEDIUM** provider confidence after direct official-source verification.
- [Composer security policy and audit configuration](https://getcomposer.org/doc/06-config.md) — primary; **MEDIUM** provider confidence after direct official-source verification.
- [Roave SecurityAdvisories package metadata](https://packagist.org/packages/roave/security-advisories) and [Packagist advisory feed](https://packagist.org/security-advisories/) — primary registry sources; **MEDIUM** provider confidence after direct official-source verification.
- [PHP 8.4 migration guide](https://www.php.net/manual/en/migration84.php) and [backward-incompatible changes](https://www.php.net/manual/en/migration84.incompatible.php) — primary; **MEDIUM** provider confidence after direct official-source verification.
- [Laravel 11 release notes](https://laravel.com/docs/11.x/releases) and [deployment requirements](https://laravel.com/docs/11.x/deployment) — primary; **MEDIUM** provider confidence after direct official-source verification.
- [GitHub Actions service-container networking](https://docs.github.com/en/actions/tutorials/use-containerized-services/use-docker-service-containers) and [cache security](https://docs.github.com/en/actions/concepts/workflows-and-actions/dependency-caching) — primary; **MEDIUM** provider confidence after direct official-source verification.

---
*Pitfalls research for: SendPortal PHP 8.4 compatibility*
*Researched: 2026-07-22*
