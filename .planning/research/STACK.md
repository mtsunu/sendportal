# Stack Research

**Domain:** PHP 8.4 compatibility for an existing Laravel 11 SendPortal host
**Researched:** 2026-07-22
**Confidence:** MEDIUM

## Recommended Stack

### Core Technologies

| Technology | Version | Purpose | Why Recommended |
|------------|---------|---------|-----------------|
| PHP | 8.4.23 target (8.4.x patched releases) | Application runtime | PHP 8.4 is actively supported through 2026-12-31 and receives security fixes through 2028-12-31. Laravel 11 officially supports PHP 8.2–8.4, so no Laravel-major upgrade is required for this runtime change. |
| Laravel Framework | `^11.0`, resolved to one tested patch version | Framework and Illuminate Mail provider | Laravel 11 is compatible with PHP 8.4. Keep the Laravel major fixed for this focused compatibility milestone; the generated lockfile, not a floating deployment resolution, chooses the exact patch release. |
| Composer | `>=2.10.0 <3.0` in CI and release tooling | Dependency resolution, platform verification, advisory policy | Composer 2.10 supplies the native `config.policy` security controls. It replaces the incompatible Roave metapackage without introducing an artificial conflict into the Laravel graph. |
| Mettle SendPortal Core | `^3.0`, resolved to `3.0.2` unless an upstream compatible release is intentionally adopted | SendPortal domain package | Its declared PHP range, `^8.2|^8.3`, already includes every PHP version from 8.2 up to (but not including) 9.0. It is therefore Composer-compatible with PHP 8.4; do not fork it merely to append `^8.4`. |

### Supporting Libraries

| Library / mechanism | Version | Purpose | When to Use |
|---------------------|---------|---------|-------------|
| Composer native advisory policy | Composer 2.10+ | Blocks known-insecure dependency versions while resolving updates | Configure it in the root `composer.json` after removing Roave. Keep explicit `composer audit --locked` in CI to check the exact production graph. |
| Composer platform check | Composer default, configured as `true` | Checks PHP and required extensions at autoloader bootstrap | Enable it for deployments so the installed graph cannot begin on a host missing a required platform package. |
| Composer lockfile | generated on PHP 8.4 | Reproducible dependency graph | Commit it after the PHP 8.4 resolution passes validation and tests. CI and production must use `composer install`, never a fresh update. |

### Development Tools

| Tool | Purpose | Notes |
|------|---------|-------|
| Composer `validate --strict` | Validates manifest and lock consistency | Run before committing `composer.json` / `composer.lock`; Composer’s own CLI documentation explicitly recommends this. |
| Composer `check-platform-reqs` | Verifies the *actual* PHP runtime and extensions | Run after install on PHP 8.4. It ignores any simulated `config.platform` values, which makes it the decisive deployment check. |
| Composer `audit --locked` | Fails CI for vulnerabilities or abandoned packages in the lockfile | Run after `composer install`; use the locked graph so audit evaluates what will actually be deployed. |
| PHPUnit | Regression verification | Add PHP 8.4 to the existing MySQL/PostgreSQL matrix and retain the existing PHP 8.2/8.3 lanes only if they remain supported deployment targets. |

## Required Manifest and Tooling Changes

### 1. Keep the PHP constraint semantically correct; simplify rather than widen it

The current root range, `^8.2|^8.3`, already allows PHP 8.4. Composer defines `^1.2.3` as `>=1.2.3 <2.0.0`; consequently `^8.2` means `>=8.2.0 <9.0.0`. The identical observation applies to SendPortal Core `3.0.2`.

Use the equivalent, non-redundant root constraint below if the manifest is being touched. It preserves PHP 8.2, 8.3, and 8.4 support while preventing an accidental PHP 9 resolution:

```json
{
  "require": {
    "php": "^8.2"
  }
}
```

This change is optional for resolution—PHP 8.4 already satisfies the current range—but recommended for clarity. Do **not** introduce an unsupported claim that `mettle/sendportal-core` needs a PHP-8.4 fork; its current caret constraint admits 8.4.

### 2. Remove Roave and use Composer’s native security policy

Remove this development dependency:

```json
"roave/security-advisories": "dev-master"
```

Current Roave metadata conflicts with `illuminate/mail >=9,<12.60`; that includes every Laravel 11 Illuminate Mail release. Changing `dev-master` to `dev-latest` does not solve this: it tracks the same current conflict list. Pinning an old Roave commit would only make the resolver pass by freezing stale vulnerability knowledge, so it is not an acceptable compatibility or security solution.

With Composer 2.10+, add the native policy under the existing `config` block:

```json
{
  "config": {
    "platform-check": true,
    "policy": {
      "advisories": {
        "block": true,
        "audit": "fail"
      },
      "abandoned": {
        "block": true,
        "audit": "fail"
      }
    }
  }
}
```

This is the compatible replacement: it has no package-level conflicts, prevents advisory-affected versions during dependency changes, and makes audits fail in CI. Ensure the CI image installs Composer 2.10+ before relying on `config.policy`; the repository’s local validation environment is Composer 2.10.1. If temporarily constrained to an older Composer 2.x, use the documented legacy `config.audit` settings plus an explicit `composer audit --locked`, then migrate to `config.policy` when CI moves to 2.10+.

### 3. Make stable, locked resolution the installation contract

All direct requirements have stable releases. After Roave is removed, remove `"minimum-stability": "dev"` (Composer’s default is stable); retaining `"prefer-stable": true` is harmless but no longer needed to compensate for a dev-only security metapackage. This reduces accidental adoption of development branches.

Generate the first lockfile on PHP 8.4, review its package changes, and commit it. Do not refresh it in production or on ordinary CI test jobs.

## Installation and Verification

Run the one-time resolution from a clean PHP 8.4 environment after changing the manifest:

```bash
composer --version
php -v
composer validate --strict
composer update --with-all-dependencies --prefer-dist --no-interaction
composer check-platform-reqs
composer audit --locked
vendor/bin/phpunit
```

After committing `composer.lock`, CI and deployments should install the exact graph and then prove its safety and runtime fit:

```bash
composer install --prefer-dist --no-interaction --no-progress
composer validate --strict
composer check-platform-reqs
composer audit --locked
vendor/bin/phpunit
```

Update `.github/workflows/ci.yml` to include a PHP 8.4 container in the test matrix and run the three Composer verification commands above. Preserve both MySQL and PostgreSQL jobs because the PHP dependency resolution is only one half of runtime compatibility.

## Alternatives Considered

| Recommended | Alternative | When to Use Alternative |
|-------------|-------------|-------------------------|
| Composer 2.10 native policy plus locked audit | Legacy `config.audit` plus `composer audit --locked` | Only while a controlled CI image cannot yet use Composer 2.10. Treat it as a migration bridge because current Composer documents `config.audit` as deprecated. |
| Keep `mettle/sendportal-core` `^3.0` | Fork SendPortal Core to add an explicit `^8.4` branch | Only if a real PHP 8.4 runtime failure is reproduced and fixed upstream/downstream. Composer’s caret semantics mean a fork is not needed for the current PHP constraint alone. |
| PHP 8.4 test matrix with locked installs | `config.platform.php` emulation | Use emulation only for an additional cross-target resolver check; it is not evidence that the actual PHP 8.4 runtime and extensions work. |

## What NOT to Use

| Avoid | Why | Use Instead |
|-------|-----|-------------|
| `roave/security-advisories` `dev-master`, `dev-latest`, or an old pinned snapshot | The current metapackage blocks all Laravel 11 `illuminate/mail` versions; an old snapshot silently misses newer advisories. | Remove it; use Composer 2.10 `config.policy` and `composer audit --locked`. |
| `--ignore-platform-reqs` or `--ignore-platform-req=php` | It forces an install that Composer has judged incompatible and hides a real runtime defect. | Resolve under PHP 8.4 and run `composer check-platform-reqs`. |
| `config.platform.php: "8.3"` as the production workaround | It only lies to the solver; Composer docs warn that simulated platform values can install a graph that fails on the real platform. | Use the actual PHP 8.4 resolver. If a simulation is retained for another target, pair it with real-platform checks. |
| `"platform-check": false` | Disables Composer’s generated startup protection for PHP/extensions. | Set `"platform-check": true` and test the production image. |
| Lockfile-free installs or `composer update` in deployment | Each install can choose a different graph, defeating reproducibility and review. | Commit `composer.lock`; use `composer install` in CI and production. |

## Stack Patterns by Variant

**If Composer 2.10+ is available in CI (recommended):**

- Use `config.policy` with advisory and abandoned package blocking, plus `composer audit --locked`.
- Because it preserves security enforcement without a conflict-package whose rules make Laravel 11 unsatisfiable.

**If a legacy Composer 2.x CI image cannot be upgraded immediately:**

- Use `config.audit` settings and an explicit `composer audit --locked` as a short-lived bridge; document the removal date.
- Because legacy Composer accepts `config.audit`, while native policy is the maintained replacement in Composer 2.10.

**If PHP 8.2/8.3 remain supported deployments:**

- Retain those jobs and add PHP 8.4 rather than replacing them; create the lockfile from PHP 8.4 but ensure all declared runtime targets satisfy it.
- Because the root PHP requirement is intentionally compatible across the 8.2–8.x range below PHP 9.

## Version Compatibility

| Package A | Compatible With | Notes |
|-----------|-----------------|-------|
| PHP `^8.2` (or current `^8.2|^8.3`) | PHP 8.4.x | Yes. Both expressions include all versions `>=8.2.0 <9.0.0`; the second branch is redundant. |
| Laravel `^11.0` | PHP 8.4 | Yes, according to Laravel’s 11.x release policy. Laravel 11 security support ended 2026-03-12, so PHP compatibility does not remove the separate framework-upgrade risk. |
| `mettle/sendportal-core` `^3.0` / v3.0.2 | PHP 8.4, Laravel 11 | Its PHP `^8.2|^8.3` and Illuminate Support `^10|^11` requirements permit this combination. Validate its provider integrations with the full test suite. |
| `roave/security-advisories` current dev branch | Laravel 11 / `illuminate/mail` 11.x | No. Its `illuminate/mail >=9,<12.60` conflict range rejects Laravel 11 mail components. |
| Composer 2.10+ native policy | Laravel 11 graph | Yes. It performs advisory policy and audit without installing a package that conflicts with Illuminate Mail. |

## Sources

- [PHP supported versions](https://www.php.net/supported-versions.php) — PHP 8.4 support dates; MEDIUM confidence (official primary source retrieved through verified web search).
- [PHP 8.4 migration guide](https://www.php.net/migration84) — backward-incompatible-change and deprecation test requirement; MEDIUM confidence.
- [Laravel 11 release notes](https://laravel.com/docs/11.x/releases) and [deployment requirements](https://laravel.com/docs/11.x/deployment) — PHP 8.2–8.4 compatibility, extension requirements, and Laravel 11 support dates; MEDIUM confidence.
- [Composer version constraints](https://getcomposer.org/doc/articles/versions.md) — caret semantics; MEDIUM confidence.
- [Composer configuration](https://getcomposer.org/doc/06-config.md) and [Composer CLI](https://getcomposer.org/doc/03-cli.md) — platform checks, lockfile validation, audit, and dependency policy; MEDIUM confidence.
- [Composer 2.10 changelog](https://getcomposer.org/changelog/2.10.0-RC2) — introduction of the `policy` configuration; MEDIUM confidence.
- [SendPortal Core package metadata](https://packagist.org/packages/mettle/sendportal-core) — v3.0.2 PHP and Illuminate Support constraints; MEDIUM confidence.
- [Roave Security Advisories package metadata](https://packagist.org/packages/roave/security-advisories) — conflicting `illuminate/mail` range; MEDIUM confidence.

---
*Stack research for: SendPortal PHP 8.4 compatibility*
*Researched: 2026-07-22*
