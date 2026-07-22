# Project Research Summary

**Project:** SendPortal PHP 8.4 Compatibility
**Domain:** Production runtime and dependency-installation compatibility for an existing Laravel 11 SendPortal host
**Researched:** 2026-07-22
**Confidence:** MEDIUM

## Executive Summary

This is a focused operational compatibility release, not a product rewrite: the Laravel 11 host must install reproducibly and keep its existing SendPortal Core, tenancy, routes, and database-backed behavior working on patched PHP 8.4. The recommended approach is to resolve the existing Composer graph on a real PHP 8.4 runner, retain PHP 8.2–8.4 support only if that is the intended deployment contract, and use the resulting `composer.lock` as the single install contract for local development, CI, and production.

The present blocker is the incompatible `roave/security-advisories` development metapackage, not a PHP constraint that needs widening: both the root and `mettle/sendportal-core` caret constraints already admit PHP 8.4. Replace Roave only with Composer 2.10+ native security policy plus a failing `composer audit --locked` CI gate; never bypass platform or advisory checks. The main risks are an unreviewed broad first lockfile update and accepting a successful solve as runtime evidence. Mitigate them with a reviewed lock diff, script-enabled Laravel/Core boot checks, and a PHP × database CI matrix that preserves MySQL and PostgreSQL coverage.

Laravel 11 itself supports PHP 8.4, so no Laravel-major migration is necessary for this milestone. Laravel 11 security support has already ended, however; that is an explicit follow-up risk rather than justification to bundle a framework upgrade into this compatibility release.

## Key Findings

### Recommended Stack

Keep the current application architecture and Laravel 11 major; the compatibility work belongs at the Composer, lockfile, and CI boundaries. Resolve under a patched PHP 8.4 release, use Composer 2.10+ for its native policy controls, and pin the successful application graph in a committed lockfile. `mettle/sendportal-core` `^3.0` / 3.0.2 is Composer-compatible with PHP 8.4 and should not be forked merely to add an explicit `^8.4` branch.

**Core technologies:**

- **PHP 8.4.x:** target runtime — Laravel 11 documents support through PHP 8.4; use an actively patched release for the initial resolution and CI lane.
- **Laravel `^11.0`:** framework and Mail integration — retain the major version for this narrow runtime milestone; select and lock one tested patch graph.
- **Composer `>=2.10 <3`:** resolution, platform verification, and dependency security policy — replaces the incompatible advisory metapackage without adding a graph conflict.
- **Composer lockfile and platform check:** deployment contract — generate on real PHP 8.4, commit it, use `composer install` thereafter, and keep generated platform checks enabled.
- **`mettle/sendportal-core` `^3.0`:** newsletter/campaign domain package — retain the package boundary and prove it by booting providers and registered routes.

### Expected Features

This milestone's features are operator guarantees: a real PHP 8.4 solve, a repeatable locked install, a retained security control, application boot plus database regression evidence, and continuous PHP 8.4 enforcement in CI.

**Must have (table stakes):**

- **PHP 8.4-resolvable graph:** normal install on the actual runtime, with no platform emulation or ignored requirements.
- **Committed, validated `composer.lock`:** `composer validate --strict` passes and normal CI/deploy paths use `composer install`.
- **Compatible security safeguard:** no Roave conflict bypass; Composer policy and `composer audit --locked` protect the final graph.
- **Runtime proof:** script-enabled Laravel boot and PHPUnit against existing MySQL and PostgreSQL services.
- **PHP 8.4 CI lane:** failures in install, platform check, audit, boot, or tests block Composer-changing pull requests.

**Should have (reliability hardening):**

- **SendPortal Core smoke coverage:** provider and route registration plus one safe representative integration path on PHP 8.4.
- **Dependency-change guardrails and upgrade evidence:** lock consistency checks and recorded PHP/Composer, audit, and matrix results.
- **Focused PHP 8.4 deprecation triage:** fix only compatibility blockers; document a package/version follow-up for third-party warnings.

**Defer (v2+):**

- **Static analysis and coverage-configuration repair:** worthwhile quality work, but not evidence required to establish runtime compatibility.
- **Broader Laravel restructuring or security modernization:** Laravel 11 lifecycle risk needs a separately scoped upgrade decision.
- **User-facing features, UI cleanup, or Core forks:** none advance the PHP 8.4 installation objective.

### Architecture Approach

Treat `composer.json` and the new `composer.lock` as a single source-of-truth dependency boundary. The frozen graph is installed on each declared runtime, then verified through the unchanged Laravel host: package discovery, `AppServiceProvider` integration, and SendPortal Core route registration must boot before the existing database-backed suite runs. The compatibility flow changes vendor selection and verification only; it must not duplicate Core behavior or alter workspace tenancy, routes, providers, migrations, or application design without a reproduced PHP 8.4 defect.

**Major components:**

1. **Composer manifest and security policy** — accurately express the PHP contract, remove the Roave graph conflict, and prohibit unsafe solver workarounds.
2. **Committed dependency lock** — freeze and document the deliberate, reviewed PHP 8.4 resolution for every ordinary installation.
3. **Laravel host/Core boot boundary** — package discovery, provider boot, and route registration validate unchanged host-to-Core composition.
4. **CI runtime × database matrix** — consumes one frozen graph across supported PHP versions and both `mysql` and `postgres` service hosts.
5. **Composer safety gates** — `validate --strict`, `check-platform-reqs`, and `audit --locked` check the exact installed graph.

### Critical Pitfalls

1. **Removing Roave and losing the security gate** — replace it only with a version-pinned Composer native policy and failing locked audit; no `--no-blocking`, broad advisory ignore, or `--ignore-platform-reqs` flags.
2. **Misstating the PHP contract** — `^8.2` already includes PHP 8.4; decide whether PHP 8.2/8.3 remain supported before narrowing anything, then test every advertised runtime for real.
3. **Treating a solve as application compatibility** — require script-enabled package discovery, Laravel boot, route registration, and host/Core behavior against both database engines.
4. **Creating an uncontrolled first lockfile** — resolve intentionally on real PHP 8.4, review the whole diff, commit it, and use lock-based installs rather than fresh updates.
5. **Adding nominal-only CI coverage** — verify the 8.4 image, Composer, extensions, and database drivers; retain both service legs and do not let a `--no-scripts` install substitute for deployment boot.

## Implications for Roadmap

Based on research, suggested phase structure:

### Phase 1: Constraint Resolution and Security Control

**Rationale:** The dependency graph cannot be frozen until its PHP support contract and the Roave/Laravel advisory conflict are resolved. Establish the security decision before changing the graph so an installable result is also safe and reviewable.

**Delivers:** A clear decision on retaining PHP 8.2–8.4 support; accurate root constraints (normally simplified to `^8.2`); removal of the incompatible Roave development dependency; Composer 2.10+ policy configuration; and real PHP 8.4 solver/prohibits evidence for the smallest compatible root set.

**Addresses:** PHP 8.4-resolvable constraints and a retained dependency-security safeguard.

**Avoids:** Incorrect caret-range edits, platform/security bypasses, and deleting Roave without a replacement control.

### Phase 2: Reproducible Dependency Snapshot

**Rationale:** Once the graph is safe and solvable, lock it before expanding verification. This gives every later test the same reviewable production artifact rather than a moving registry result.

**Delivers:** A generated and reviewed `composer.lock`; stable installation policy (including removing global development stability if it only served Roave); passing `composer validate --strict`, `composer check-platform-reqs`, and `composer audit --locked`; and a documented `composer install` contract for CI/deployments.

**Addresses:** Reproducible install and dependency-change guardrail table stakes.

**Uses:** Composer 2.10+ native policy, platform check, and the locked application graph.

**Avoids:** A broad unreviewed first update, hand-edited/stale locks, fresh dependency resolution in CI, and stale `vendor/` caching.

### Phase 3: PHP 8.4 Runtime, Core Integration, and CI Verification

**Rationale:** A locked graph only proves installability. This final phase proves the unmodified Laravel host and SendPortal Core survive PHP 8.4, then turns that proof into an ongoing project guarantee.

**Delivers:** Script-enabled package discovery plus `artisan about`/`route:list` smoke evidence; existing PHPUnit coverage on PHP 8.4 with MySQL and PostgreSQL; a verified 8.4 test-runner image in the existing matrix; lockfile-based Composer safety checks; and, where fixtures permit, a small SendPortal Core provider/route and representative integration smoke test. Update operator documentation only if it states a superseded PHP/install contract.

**Addresses:** PHP 8.4 regression validation, automated enforcement, Core integration smoke coverage, deprecation triage, and upgrade evidence.

**Implements:** The Laravel host/Core boot boundary and the runtime × database verification boundary without redesigning application code.

**Avoids:** Solver-only acceptance, scriptless deployment proof, image/tag drift, database coverage loss, and unrelated host/Core refactors.

### Phase Ordering Rationale

- Phase 1 must precede all others because security policy and accurate support constraints decide which graph may legally be resolved.
- Phase 2 separates the intentional one-time `composer update` from all ordinary installs, creating the immutable artifact that CI and deployment must exercise.
- Phase 3 is last because boot and behavior evidence is meaningful only for the exact locked graph operators will receive; it extends the existing CI topology instead of replacing it.
- The grouping keeps dependency, security, and runtime risks independently reviewable while protecting the existing Laravel host/SendPortal Core boundary.

### Research Flags

Phases likely needing deeper research during planning:

- **Phase 1:** Run a networked real-PHP-8.4 solver and `composer prohibits` analysis. Research could not reproduce the final Packagist solver tree because DNS resolution failed, so exact patched root/package versions and any audit exceptions remain unverified.
- **Phase 3:** Verify the exact PHP 8.4 test-runner image tag/digest, installed extensions, driver availability, and the most stable Core integration smoke target against the final lockfile.

Phases with standard patterns (skip research-phase):

- **Phase 2:** Composer lockfile generation, strict manifest validation, lock-based installs, platform checks, and locked auditing are established Composer patterns; planning can proceed from the documented command contract.

## Confidence Assessment

| Area | Confidence | Notes |
|------|------------|-------|
| Stack | MEDIUM | Official PHP, Laravel, Composer, Packagist, and package metadata support the recommendation; the precise final resolve was not reproduced in this environment. |
| Features | MEDIUM | Strong repository-specific acceptance criteria and official documentation; Core smoke-test fixture feasibility needs final-graph inspection. |
| Architecture | MEDIUM | Based on existing host/Core and CI boundaries plus documented Composer/GitHub Actions patterns; no application redesign is proposed. |
| Pitfalls | MEDIUM | Cross-checked with official documentation and repository evidence, but Packagist DNS prevented confirmation of the exact live blocker tree. |

**Overall confidence:** MEDIUM

### Gaps to Address

- **Exact resolution and package versions:** Perform the intentional solve on a networked PHP 8.4 environment; attach solver/prohibits output, full lock diff, and locked audit result to review.
- **Support-window decision:** Confirm whether PHP 8.2 and 8.3 remain production targets. If yes, test the final lockfile on each actual runtime; if no, explicitly approve the support-policy change before narrowing constraints or CI.
- **Composer toolchain floor:** Verify every CI/release image uses Composer 2.10+ before relying on `config.policy`; use documented legacy audit configuration only as a temporary, recorded bridge.
- **Runtime image and extensions:** Confirm the PHP 8.4 container's tag/digest, Composer version, required PHP modules, MySQL/PgSQL drivers, and service connectivity before modifying the matrix.
- **Core behavioral proof:** Choose a minimal, non-flaky provider/route plus tenant-safe campaign/subscriber, queue, mail, webhook, or API smoke path after the locked Core release and available fixtures are known.
- **Laravel lifecycle:** Record the end of Laravel 11 security support as a separately prioritized framework-upgrade decision; do not broaden this compatibility milestone to solve it.

## Sources

### Primary (HIGH confidence)

- [Composer basic usage and lockfile guidance](https://getcomposer.org/doc/01-basic-usage.md) — update/install and committed-lockfile contract.
- [Composer CLI](https://getcomposer.org/doc/03-cli.md) and [Composer configuration](https://getcomposer.org/doc/06-config.md) — validation, platform checks, audit, policy, and solver diagnostics.
- [Composer version constraints](https://getcomposer.org/doc/articles/versions.md) and [platform dependencies](https://getcomposer.org/doc/articles/composer-platform-dependencies.md) — caret semantics and actual-platform verification.
- [PHP supported versions](https://www.php.net/supported-versions.php) and [PHP 8.4 migration guide](https://www.php.net/migration84) — patched release/support context and migration risks.
- [Laravel 11 releases](https://laravel.com/docs/11.x/releases) and [deployment requirements](https://laravel.com/docs/11.x/deployment) — PHP support and platform requirements.
- [GitHub Actions service containers](https://docs.github.com/en/actions/tutorials/use-containerized-services/use-docker-service-containers) and [container jobs](https://docs.github.com/en/actions/how-tos/write-workflows/choose-where-workflows-run/run-jobs-in-a-container) — CI network topology.

### Secondary (MEDIUM confidence)

- [Mettle SendPortal Core package metadata](https://packagist.org/packages/mettle/sendportal-core) — declared PHP/Illuminate compatibility.
- [Roave Security Advisories package metadata](https://packagist.org/packages/roave/security-advisories) and [Packagist advisories](https://packagist.org/security-advisories/) — incompatible advisory-metapackage constraint and advisory feed.
- [Laravel test runner tags](https://hub.docker.com/r/kirschbaumdevelopment/laravel-test-runner/tags) — PHP 8.4 runner availability; pin and verify the final image in implementation.
- Repository evidence in `.planning/PROJECT.md`, `.planning/codebase/TESTING.md`, and `.planning/codebase/CONCERNS.md` — current host/Core scope, test topology, and unrelated risks.

---
*Research completed: 2026-07-22*
*Ready for roadmap: yes*
