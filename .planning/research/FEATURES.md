# Feature Research

**Domain:** Production PHP 8.4 runtime compatibility and Composer installation reliability for an existing Laravel 11 application
**Researched:** 2026-07-22
**Confidence:** MEDIUM

## Feature Landscape

This milestone delivers an operational compatibility contract, not user-facing functionality. The word “feature” below means a verifiable upgrade capability that an operator, deployer, or maintainer relies on.

### Table Stakes (Operators Expect These)

| Feature | Why Expected | Complexity | Notes and concrete acceptance check |
|---------|--------------|------------|-------------------------------------|
| PHP 8.4 is an explicit supported Composer target | An install that rejects the target interpreter has not been upgraded. Laravel 11 itself lists PHP 8.2–8.4 as supported, but every root and transitive dependency must agree. | MEDIUM | Update the root PHP/package constraints only as needed for a PHP 8.4-resolvable graph. On a clean PHP 8.4 environment, `composer install --prefer-dist --no-interaction` exits 0 without `--ignore-platform-req` or `config.platform` pretending to be 8.4. `composer check-platform-reqs` passes against the actual target runtime. |
| Reproducible dependency snapshot | This application is a deployable application, not a reusable library. Without `composer.lock`, every install is a fresh, time-dependent dependency resolution. | MEDIUM | Commit the generated `composer.lock`. From a fresh checkout, `composer install` uses the lock and exits 0; `composer validate --strict` reports a valid, in-sync manifest and lock. CI uses `install`, not a dependency-solving `update`. |
| Compatible security safeguard | The current Roave advisory metapackage blocks the Laravel 11 graph; simply removing it without replacement weakens supply-chain protection. | MEDIUM | Remove or adjust only the conflicting mechanism, then retain a non-bypassed security check such as `composer audit`/Composer policy. The designated CI/security command exits 0 on the committed lockfile, and any exception is narrow, documented, and reviewable rather than a global disablement. |
| PHP 8.4 application regression validation | Composer can solve a graph even when application boot, package discovery, or PHP 8.4 deprecations break real behavior. | MEDIUM | On PHP 8.4, install dependencies with normal scripts enabled, boot Laravel (`php artisan about` or an equivalent safe boot check), and run `vendor/bin/phpunit` successfully against both currently supported CI databases: MySQL and PostgreSQL. Treat new PHP 8.4 deprecations/errors in application paths as upgrade defects. |
| PHP 8.4 is enforced in automation | Local success does not create a supported runtime unless the project continuously verifies it. | LOW | Update the CI matrix or equivalent CI job to run the lockfile install and suite under PHP 8.4 with the same database services. A pull request changing Composer files fails if install, validation, security check, boot, or tests fail on that runtime. |

### Differentiators (Reliability Beyond a One-Time Green Install)

| Feature | Value Proposition | Complexity | Notes and concrete acceptance check |
|---------|-------------------|------------|-------------------------------------|
| Dependency-change guardrail | Makes the lockfile an ongoing reliability contract instead of a one-off artifact. | LOW | CI runs `composer validate --strict`; a manifest edit made without refreshing the lock causes the job to fail. Keep the normal install path lock-based and reserve `composer update` for an intentional, reviewed maintenance workflow. |
| Core-package integration smoke coverage | The primary newsletter/campaign functionality comes from `mettle/sendportal-core`, while the existing suite concentrates on the host application. A small contract test catches upgrade regressions that host-only tests miss. | MEDIUM | Add or document a minimal automated smoke check that the core provider can boot and its expected route groups register, plus one representative safe integration path where fixtures permit it. It must run on PHP 8.4 and not introduce new product behavior. |
| Upgrade evidence artifact | Turns an implicit environment claim into an auditable release record. | LOW | The pull request or CI summary records PHP/Composer versions, the locked package graph, command results for validation/audit/platform checks, and the MySQL/PostgreSQL PHPUnit outcomes. No credentials, tokens, or `.env` values are recorded. |
| Focused deprecation triage | Avoids accepting a noisy upgrade that will become a hard break later, without turning this milestone into a broad refactor. | MEDIUM | Run the PHP 8.4 suite with normal error reporting, classify new deprecations by direct application code versus third-party code, and fix or constrain only blockers in scope. Any deferred third-party warning has a linked package/version and follow-up rationale. |

### Anti-Features (Commonly Requested, Often Problematic)

| Feature | Why Requested | Why Problematic | Alternative |
|---------|---------------|-----------------|-------------|
| `--ignore-platform-req` / `--ignore-platform-reqs` or disabling Composer platform checks | It makes a failing dependency solve appear successful quickly. | It hides PHP or extension incompatibilities and invalidates the claim that PHP 8.4 is supported. | Resolve or replace the actual conflicting constraints; prove the install on the real PHP 8.4 runtime with `composer check-platform-reqs`. |
| Removing security checks to unblock Composer | It is the shortest route around the current Roave conflict. | It silently changes the project’s security posture and can permit known-vulnerable dependencies. | Retain Composer audit/policy or an equivalent CI safeguard, with narrow documented exceptions only when necessary. |
| A solver-only “upgrade” | `composer update` may finish locally and look complete. | It neither freezes the result nor proves Laravel boot, scripts, extensions, or the existing database-backed application behavior. | Commit a lockfile, execute a clean lockfile install, boot Laravel, and run the current MySQL/PostgreSQL suite on PHP 8.4. |
| Laravel 11 structural migration, auth/security repair, or UI cleanup bundled into this change | Compatibility work exposes legacy code and tempting adjacent improvements. | The project already has unrelated authorization and installer risks; mixing them with dependency changes inflates review and obscures the compatibility regression surface. | Keep fixes limited to PHP 8.4/Composer blockers. Capture unrelated issues for separately scoped milestones. |
| New end-user functionality or broad package modernization | A dependency refresh can be framed as a product release. | It exceeds the milestone’s compatibility-and-installation objective and adds behavior that has no bearing on PHP 8.4 support. | Preserve existing behavior; add only test and CI coverage required to prove it survives the new runtime. |
| Claiming universal PHP support | Supporting every PHP version seems future-proof. | It would conflict with Laravel 11’s documented runtime range and multiplies CI/maintenance obligations. | State PHP 8.4 as supported, retain only already-intended compatible versions, and make any future runtime expansion a separate decision. |

## Feature Dependencies

```text
Accurate PHP and package constraints
    └──requires──> PHP 8.4-resolvable dependency graph
                           └──requires──> committed composer.lock
                                                   └──requires──> clean lockfile install
                                                                       └──requires──> platform and Laravel boot checks
                                                                                           └──requires──> PHP 8.4 CI suite on MySQL and PostgreSQL

Compatible security safeguard ──must validate──> committed dependency graph
Core-package smoke coverage ──enhances──> PHP 8.4 CI suite
Platform-requirement bypass ──conflicts──> trustworthy PHP 8.4 support
Unrelated refactors ──conflict with──> reviewable, focused compatibility delivery
```

### Dependency Notes

- **A real PHP 8.4 graph requires correct constraints:** a manifest that declares support but cannot resolve it is not an installable product.
- **The lockfile requires a successful intended resolution first:** generate it after resolving the Roave/Laravel conflict, then validate it against the final manifest.
- **Clean install precedes runtime validation:** package discovery and application boot use the installed locked graph, so test results are meaningful only after a normal install succeeds.
- **Security validation depends on the final lockfile:** audit the exact packages operators will install, rather than a hypothetical range in `composer.json`.
- **Core smoke coverage enhances, but does not replace, the suite:** it addresses the stated risk from the external `mettle/sendportal-core` package without expanding the product scope.

## MVP Definition

### Launch With (v1)

- [ ] A PHP 8.4-resolvable manifest and dependency graph — essential because it is the milestone’s stated operator outcome.
- [ ] A committed, validated `composer.lock` and clean `composer install` path — essential for reproducible installation.
- [ ] A retained, passing dependency security safeguard — essential because removing the conflicting advisory metapackage must not reduce security coverage.
- [ ] PHP 8.4 Laravel boot plus the existing PHPUnit suite against MySQL and PostgreSQL — essential proof that existing behavior remains operational.
- [ ] A PHP 8.4 CI job using the lockfile — essential to keep support from regressing immediately.

### Add After Validation (v1.x)

- [ ] Minimal SendPortal-core provider/route integration smoke coverage — add when fixtures and the final package graph make the highest-value contract test clear.
- [ ] CI evidence summary / explicit dependency-update workflow — add once the baseline pipeline is green and maintainers need a repeatable maintenance cadence.

### Future Consideration (v2+)

- [ ] Static analysis and corrected application coverage configuration — valuable quality work, but not required to establish PHP 8.4 installation compatibility.
- [ ] A broader Laravel application-structure migration — defer because it is explicitly unrelated to the focused runtime upgrade.

## Feature Prioritization Matrix

| Feature | User Value | Implementation Cost | Priority |
|---------|------------|---------------------|----------|
| PHP 8.4-resolvable constraints and clean install | HIGH | MEDIUM | P1 |
| Committed, validated lockfile | HIGH | LOW | P1 |
| Security-check replacement/retention | HIGH | MEDIUM | P1 |
| PHP 8.4 boot and MySQL/PostgreSQL test validation | HIGH | MEDIUM | P1 |
| PHP 8.4 CI enforcement | HIGH | LOW | P1 |
| Core-package smoke contract | HIGH | MEDIUM | P2 |
| Upgrade evidence artifact | MEDIUM | LOW | P2 |
| Static analysis / coverage repair | MEDIUM | MEDIUM | P3 |

**Priority key:** P1 = required for this compatibility release; P2 = hardens continued reliability after the baseline works; P3 = useful but outside this focused milestone.

## Sources

- [Composer basic usage and lockfile guidance](https://getcomposer.org/doc/01-basic-usage.md) — MEDIUM confidence (official Composer documentation; verified through current web lookup).
- [Composer command reference: validation and platform requirement flags](https://getcomposer.org/doc/03-cli.md) — MEDIUM confidence (official Composer documentation; verified through current web lookup).
- [Composer configuration: platform checks and dependency security/abandonment policy](https://getcomposer.org/doc/06-config.md) — MEDIUM confidence (official Composer documentation; policy details can vary with Composer version).
- [Composer platform dependencies](https://getcomposer.org/doc/articles/composer-platform-dependencies.md) — MEDIUM confidence (official Composer documentation; verified through current web lookup).
- [Laravel 11 release notes and supported PHP versions](https://laravel.com/docs/11.x/releases) — MEDIUM confidence (official Laravel documentation; verified through current web lookup).
- [Laravel 11 deployment requirements](https://laravel.com/docs/11.x/deployment) — MEDIUM confidence (official Laravel documentation; verified through current web lookup).
- [PHP 8.4 release notes and compatibility changes](https://www.php.net/releases/8.4/en.php) — MEDIUM confidence (official PHP documentation; verified through current web lookup).
- Project evidence: `.planning/PROJECT.md`, `.planning/codebase/TESTING.md`, and `.planning/codebase/CONCERNS.md` — HIGH confidence for repository-specific scope and test/CI observations.

---
*Feature research for: SendPortal PHP 8.4 compatibility*
*Researched: 2026-07-22*
