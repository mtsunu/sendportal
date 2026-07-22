# Requirements: SendPortal PHP 8.4 Compatibility

**Defined:** 2026-07-22
**Core Value:** Operators can install and run SendPortal reliably on PHP 8.4 without bypassing dependency or platform requirements.

## v1 Requirements

### Composer Compatibility

- [ ] **COMP-01**: A clean PHP 8.4 environment can run `composer install --prefer-dist --no-interaction` successfully without `--ignore-platform-req`, `--ignore-platform-reqs`, or platform emulation.
- [ ] **COMP-02**: The Composer PHP constraints accurately declare the supported PHP 8.2, 8.3, and 8.4 runtime contract.
- [ ] **COMP-03**: The `roave/security-advisories` conflict no longer blocks the Laravel 11 dependency graph, and the replacement configuration does not weaken platform checks.

### Dependency Security & Reproducibility

- [ ] **DEPS-01**: A reviewed `composer.lock` is committed and remains synchronized with `composer.json` under `composer validate --strict`.
- [ ] **DEPS-02**: The locked graph passes `composer check-platform-reqs` on PHP 8.4.
- [ ] **DEPS-03**: The locked graph passes a non-bypassed dependency security check (`composer audit --locked` or an equivalent configured Composer policy).
- [ ] **DEPS-04**: Standard local, CI, and deployment installation paths use `composer install` against the committed lockfile rather than a fresh dependency resolution.

### Runtime Validation

- [ ] **RUNTIME-01**: A normal, script-enabled lockfile install completes Laravel package discovery and a safe Laravel boot check on PHP 8.4.
- [ ] **RUNTIME-02**: The existing PHPUnit suite passes on PHP 8.4 against MySQL.
- [ ] **RUNTIME-03**: The existing PHPUnit suite passes on PHP 8.4 against PostgreSQL.
- [ ] **RUNTIME-04**: PHP 8.4 validation proves that the existing SendPortal Core provider and route-registration integration still boots without changing product behavior.

### Continuous Integration

- [ ] **CI-01**: CI includes a PHP 8.4 job using the committed lockfile and retains the existing supported PHP 8.2 and 8.3 coverage.
- [ ] **CI-02**: The PHP 8.4 CI job fails on invalid Composer metadata, platform requirement failures, dependency audit failures, Laravel boot failures, or PHPUnit failures for either database engine.

## v2 Requirements

### Upgrade Hardening

- **HARD-01**: CI records a concise dependency-upgrade evidence summary with PHP/Composer versions, audit result, and database-matrix test outcomes.
- **HARD-02**: A minimal tenant-safe SendPortal Core behavior smoke test covers one representative package flow under PHP 8.4.
- **HARD-03**: Static analysis and application coverage configuration are repaired in a separately scoped quality milestone.

## Out of Scope

| Feature | Reason |
|---------|--------|
| Laravel major-version upgrade | Laravel 11 lifecycle work is a separate decision and would obscure the PHP 8.4 compatibility change. |
| New user-facing features or UI redesign | They do not improve PHP 8.4 installation or runtime reliability. |
| Broad authorization, installer, or architecture refactors | Existing concerns are important but unrelated to the focused dependency/runtime release. |
| Composer platform or advisory bypass flags | They would make the result appear compatible while concealing real incompatibilities. |

## Traceability

| Requirement | Phase | Status |
|-------------|-------|--------|
| COMP-01 | Phase 1 | Pending |
| COMP-02 | Phase 1 | Pending |
| COMP-03 | Phase 1 | Pending |
| DEPS-01 | Phase 2 | Pending |
| DEPS-02 | Phase 2 | Pending |
| DEPS-03 | Phase 2 | Pending |
| DEPS-04 | Phase 2 | Pending |
| RUNTIME-01 | Phase 3 | Pending |
| RUNTIME-02 | Phase 3 | Pending |
| RUNTIME-03 | Phase 3 | Pending |
| RUNTIME-04 | Phase 3 | Pending |
| CI-01 | Phase 3 | Pending |
| CI-02 | Phase 3 | Pending |

**Coverage:**
- v1 requirements: 13 total
- Mapped to phases: 13
- Unmapped: 0 ✓

---
*Requirements defined: 2026-07-22*
*Last updated: 2026-07-22 after initial definition*
