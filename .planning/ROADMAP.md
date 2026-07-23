# Roadmap: SendPortal PHP 8.4 Compatibility

## Overview

This milestone establishes a secure PHP 8.4 installation contract for the existing Laravel 11 and SendPortal Core application. It first resolves the PHP and security-policy constraints, then freezes the approved graph in a reviewed lockfile, and finally proves the exact snapshot boots and passes the existing database matrix in CI—without a Laravel major upgrade or new product behavior.

## Phases

**Phase Numbering:**

- Integer phases (1, 2, 3): Planned milestone work
- Decimal phases (2.1, 2.2): Urgent insertions (marked with INSERTED)

Decimal phases appear between their surrounding integers in numeric order.

- [ ] **Phase 1: Constraint Resolution and Security Control** - Establish an honest PHP 8.2–8.4 Composer contract and a compatible dependency-security safeguard.
- [ ] **Phase 2: Reproducible Dependency Snapshot** - Freeze the approved PHP 8.4 graph into a reviewed lockfile and make it the standard installation contract.
- [ ] **Phase 3: PHP 8.4 Runtime, Core Integration, and CI Verification** - Prove the locked application boots and its existing test matrix continues to work under PHP 8.4.

## Phase Details

### Phase 1: Constraint Resolution and Security Control

**Goal**: Operators can resolve a secure, accurately declared PHP 8.2–8.4 dependency graph on real PHP 8.4 without bypassing Composer safeguards.
**Depends on**: Nothing (first phase)
**Requirements**: COMP-01, COMP-02, COMP-03
**Success Criteria** (what must be TRUE):

  1. An operator on PHP 8.4 can resolve the application's dependencies through standard Composer commands without platform-emulation or ignore flags.
  2. The published Composer metadata declares PHP 8.2, 8.3, and 8.4 as the supported runtime contract.
  3. Dependency resolution no longer fails because of the Roave/Laravel conflict, while Composer platform checks and a dependency-security safeguard remain enabled.

**Plans**: 6/6 plans executed

Plans:

- [x] 01-05-PLAN.md
- [x] 01-06-PLAN.md

- [x] 01-04-PLAN.md

- [x] 01-03-PLAN.md

**Wave 1**

- [x] 01-01-PLAN.md — Capture real PHP 8.4 solver evidence and obtain the required security/scope decision.

**Wave 2** *(blocked on Wave 1 completion)*

- [x] 01-02-PLAN.md — Apply the approved minimal Composer policy contract and prove it in an isolated PHP 8.4 install.

### Phase 2: Reproducible Dependency Snapshot

**Goal**: Operators, CI, and deployments install one validated, security-checked dependency graph rather than independently resolving packages.
**Depends on**: Phase 1
**Requirements**: DEPS-01, DEPS-02, DEPS-03, DEPS-04
**Success Criteria** (what must be TRUE):

  1. The repository contains a reviewed `composer.lock` synchronized with `composer.json`, and strict Composer metadata validation succeeds.
  2. The exact locked graph passes Composer's PHP 8.4 platform-requirement check.
  3. The exact locked graph passes a non-bypassed dependency security audit or an equivalent configured Composer policy.
  4. Local, CI, and deployment installation instructions and automation install the committed lockfile instead of freshly resolving dependencies.

**Plans**: TBD

### Phase 3: PHP 8.4 Runtime, Core Integration, and CI Verification

**Goal**: Operators have ongoing evidence that the locked PHP 8.4 application, including its existing SendPortal Core integration, boots and passes both supported database test paths.
**Depends on**: Phase 2
**Requirements**: RUNTIME-01, RUNTIME-02, RUNTIME-03, RUNTIME-04, CI-01, CI-02
**Success Criteria** (what must be TRUE):

  1. A normal script-enabled install on PHP 8.4 completes Laravel package discovery and the application passes a safe boot check.
  2. On PHP 8.4, the existing PHPUnit suite passes against both MySQL and PostgreSQL.
  3. The existing SendPortal Core provider and route-registration integration boot on PHP 8.4 without a product-behavior change.
  4. CI retains PHP 8.2 and 8.3 coverage and adds a PHP 8.4 job that uses the committed lockfile.
  5. The PHP 8.4 CI path fails if Composer metadata, platform requirements, dependency auditing, Laravel/Core boot, or either database-engine PHPUnit run fails.

**Plans**: TBD

## Progress

**Execution Order:**
Phases execute in numeric order: 1 → 2 → 3

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 1. Constraint Resolution and Security Control | 6/6 | In Progress|  |
| 2. Reproducible Dependency Snapshot | 0/TBD | Not started | - |
| 3. PHP 8.4 Runtime, Core Integration, and CI Verification | 0/TBD | Not started | - |
