---
status: investigating
trigger: "CI reaches dependency audit but skips the Phase 07 MySQL and PostgreSQL tests"
created: 2026-09-19T16:05:00Z
updated: 2026-09-19T16:20:00Z
---

# CI Dependency Audit

## Symptoms

- Expected: GitHub Actions passes dependency audit and reaches the MySQL and PostgreSQL PHPUnit jobs.
- Actual: Run `35452803770` passed `Verify Composer policy routes`, then failed `Audit dependencies`; both database jobs were skipped.
- Reproduction: `php bin/composer-policy audit --locked --no-interaction`.
- Findings: The lock contained `guzzlehttp/guzzle` 7.15.1, `league/commonmark` 2.8.3, and `livewire/livewire` v3.8.2, all covered by current advisories.

## Current Focus

- hypothesis: The CI failure is caused by stale locked patch versions, not by Phase 07 application code. Safe patch releases are already permitted by the existing constraints.
- next_action: Make the Phase 07 boundary fixture respect the campaign schema and isolate environment-sensitive auth route tests, then push and confirm both supported-driver jobs pass.

## Evidence

- timestamp: 2026-09-19T16:05:00Z
  source: local Composer audit
  observation: The audit reported two advisories for `guzzlehttp/guzzle` 7.15.1, multiple advisories for `league/commonmark` 2.8.3, and one advisory for `livewire/livewire` v3.8.2. The existing three Laravel exceptions remained unchanged.

- timestamp: 2026-09-19T16:05:00Z
  source: Composer package metadata and constraints
  observation: `guzzlehttp/guzzle` 7.15.2, `league/commonmark` 2.9.1+, and `livewire/livewire` v3.8.3+ are available and satisfy the current manifest constraints.

- timestamp: 2026-09-19T16:05:00Z
  source: guarded Composer update and local verification
  observation: `php bin/composer-policy update guzzlehttp/guzzle league/commonmark livewire/livewire --no-interaction` updated only those three packages to 7.15.2, 2.10.1, and v3.8.9. Composer audit now reports only the three pre-approved Laravel exceptions; route audit, policy tests, manifest validation, and the Phase 07 SQLite suite pass.

- timestamp: 2026-09-19T16:10:00Z
  source: GitHub Actions run 35453225750 MySQL log
  observation: Route audit, manifest validation, install, dependency audit, Laravel boot, and environment preparation passed. The MySQL suite failed before assertions with `PDOException: SQLSTATE[HY000] [1045] Access denied for user 'homestead'`; the workflow service is provisioned with `MYSQL_USER=laravel` and `MYSQL_PASSWORD=secret`.

## Resolution

- root_cause: The committed lockfile lagged behind security-fixed patch releases that were available within the existing dependency constraints.
- fix: Refresh only the affected lockfile packages through the repository Composer policy guard; do not add new advisory ignores or disable audit blocking.
- verification: Dependency gates pass remotely; the database matrix is pending the CI credential fix.
- files_changed: `composer.lock`, `.github/workflows/ci.yml`

## Follow-up

- root_cause: `.env.example` is copied before the suite and supplies the local Homestead username. The workflow set only connection/host/port, so Laravel retained `homestead` instead of the service's `laravel` user.
- fix: Set `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` explicitly in both database test steps, matching their service definitions. This keeps the local Homestead defaults unchanged.

- timestamp: 2026-09-19T16:20:00Z
  source: GitHub Actions run 35453478706 MySQL log and local full suite
  observation: The credential fix worked: the suite reached 102 tests and 317 assertions. The remaining Phase 07 error was MySQL 1406 Data too long for column 'name' from a fixture inserting 300 emoji into the package varchar(255) campaign name. The same run also exposed two order-sensitive auth configuration failures; local SQLite reproduces them in the full suite while each auth class passes in isolation.

## Follow-up 2

- root_cause: The Phase 07 boundary test used a 300-code-point campaign name even though the package campaign schema stores name as varchar(255); SQLite did not enforce the length.
- fix: Build an unsaved campaign model for the sender-capture service boundary test, so the 300-code-point source can exercise label truncation without violating the campaign table schema.
- root_cause: Auth route tests mutate environment values after Laravel's immutable environment repository may already be initialized by earlier tests in the same PHPUnit process.
- fix: Synchronize putenv, $_ENV, and $_SERVER values, reset Laravel's Env repository, and run both environment-sensitive auth test classes in separate processes so each class boots Laravel with its own route configuration.
