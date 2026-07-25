# Graph Report - sendportal  (2026-07-25)

## Corpus Check
- 234 files · ~436,958 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 1748 nodes · 2140 edges · 178 communities (148 shown, 30 thin omitted)
- Extraction: 98% EXTRACTED · 2% INFERRED · 0% AMBIGUOUS · INFERRED: 34 edges (avg confidence: 0.8)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `4e4d2d27`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- Workspace
- ComposerPolicyGuardTest.php
- Phase 1: Constraint Resolution and Security Control - Research
- AGENTS.md
- Setup
- InstallApplication
- RegisterController.php
- Illuminate\Database\Migrations\Migration
- Phase 2 Plan 02: Reproducible Dependency Snapshot Summary
- Architecture Research
- Illuminate\Contracts\View\View
- Illuminate\Foundation\Testing\RefreshDatabase
- Closure
- Controller
- Architecture
- Phase 2: Reproducible Dependency Snapshot - Context
- Implications for Roadmap
- Illuminate\Http\RedirectResponse
- Phase 1: Constraint Resolution and Security Control - Context
- Phase 2 Plan 01: Reproducible Dependency Snapshot Summary
- Pitfalls Research
- Illuminate\Foundation\Http\FormRequest
- User
- Phase 01 Plan 04: Composer Policy Entry-Point Guard Summary
- Stack Research
- TestCase
- Phase 01 Plan 01: Isolated PHP 8.4 Composer Solver Evidence Summary
- Phase 01 Plan 02: PHP Support and Native Advisory Policy Summary
- Phase 01 Plan 07: Hardened Composer Policy Boundary Summary
- Phase 01 Plan 12: PHP Provenance Audit Closure Summary
- Phase 01 Plan 05: Trusted Composer Distribution and Per-Chain Audit Summary
- Phase 01 Plan 09: Compound Shell and Inline PHP Audit Summary
- Phase 01 Plan 10: Route Audit Closure Summary
- Phase 01 Plan 11: Composer Script and PHP Dispatch Closure Summary
- Feature Research
- self
- Phase 01 Plan 03: Fail-Closed Composer Policy Summary
- Phase 01 Plan 08: Nested Shell Evaluator Audit Summary
- Phase 01: Constraint Resolution and Security Control - Pattern Map
- Goal Achievement
- Phase 2: Reproducible Dependency Snapshot - Research
- composer-policy
- Route Audit Completeness
- Goal Achievement
- SendPortal PHP 8.4 Compatibility
- AppServiceProvider.php
- SetupTest
- Sendportal
- Codebase Concerns
- Requirements: SendPortal PHP 8.4 Compatibility
- Project State
- ComposerPolicyLivePackagistTest.php
- WorkspaceInvitationStoreRequest
- composer.json
- Coding Conventions
- Testing Patterns
- Phase 2 — Validation Strategy
- ChangePasswordRequest
- require
- External Integrations
- Phase 01 Plan 06: Canonical Composer Boundary and Live PHP 8.4 Evidence
- Phase 01 — Validation Strategy
- Roadmap: SendPortal PHP 8.4 Compatibility
- config
- scripts
- Technology Stack
- Codebase Structure
- Phase 2: Reproducible Dependency Snapshot - Discussion Log
- Architecture Patterns
- VerificationController
- ApiTokenController
- RouteServiceProvider
- ignore-id
- Phase 1: Constraint Resolution and Security Control - Discussion Log
- README.md
- Kernel
- Handler
- WorkspacesController.php
- require-dev
- 01-03-PLAN.md
- 01-04-PLAN.md
- Phase 01: Code Review Report
- Common Pitfalls
- RESEARCH COMPLETE
- NewUserInvitationTest
- ComposerPolicyCommandContract
- Invitation.php
- HorizonServiceProvider
- psr-4
- 01-02-PLAN.md
- 01-05-PLAN.md
- 01-06-PLAN.md
- 01-07-PLAN.md
- 01-08-PLAN.md
- Validation Architecture
- WorkspaceInvitationsControllerTest
- Authenticate
- TrustHosts
- Workspace
- AuthServiceProvider
- EventServiceProvider
- DatabaseSeeder
- ExampleTest
- 01-01-PLAN.md
- 01-09-PLAN.md
- 01-10-PLAN.md
- 01-11-PLAN.md
- Standard Stack
- User Constraints (from CONTEXT.md)
- Kernel
- CheckForMaintenanceMode
- EncryptCookies
- TrimStrings
- TrustProxies
- VerifyCsrfToken
- WritesToEnvironment.php
- autoload-dev
- 01-12-PLAN.md
- 02-01-PLAN.md
- 02-02-PLAN.md
- Security Domain
- Code Examples
- Sources
- env.blade.php
- key.blade.php
- migrations.blade.php
- WINDOWS.md
- login.blade.php
- email.blade.php
- reset.blade.php
- register.blade.php
- database.blade.php
- url.blade.php
- Phase 3: PHP 8.4 Runtime, Core Integration, and CI Verification - Discussion Log
- Phase 3 — Validation Strategy
- Setup
- Setup.php
- ApiTokenController
- SetupTest
- 03-01-PLAN.md
- VerificationController

## God Nodes (most connected - your core abstractions)
1. `Communities (183 total, 36 thin omitted)` - 111 edges
2. `User` - 47 edges
3. `Workspace` - 37 edges
4. `Controller` - 32 edges
5. `TestCase` - 29 edges
6. `auditRoutes()` - 26 edges
7. `Invitation` - 23 edges
8. `InstallApplication` - 22 edges
9. `Phase 2: Reproducible Dependency Snapshot - Research` - 21 edges
10. `Phase 1: Constraint Resolution and Security Control - Research` - 19 edges

## Surprising Connections (you probably didn't know these)
- `loginUser()` --references--> `User`  [EXTRACTED]
  tests/TestSupportTrait.php → app/Models/User.php
- `createWorkspaceUser()` --references--> `Workspace`  [EXTRACTED]
  tests/TestSupportTrait.php → app/Models/Workspace.php
- `parseInvocation()` --calls--> `ComposerPolicyCommandContract`  [INFERRED]
  tests/Composer/ComposerPolicyGuardTest.php → tools/composer/ComposerPolicyCommandContract.php
- `createUserWithWorkspace()` --references--> `User`  [EXTRACTED]
  tests/TestSupportTrait.php → app/Models/User.php
- `createWorkspaceUser()` --references--> `User`  [EXTRACTED]
  tests/TestSupportTrait.php → app/Models/User.php

## Import Cycles
- None detected.

## Communities (178 total, 30 thin omitted)

### Community 0 - "Workspace"
Cohesion: 0.23
Nodes (14): Workspace, currentWorkspace(), currentWorkspaceId(), getCurrentWorkspaceAttribute(), hasWorkspaces(), invitations(), onWorkspace(), ownsCurrentWorkspace() (+6 more)

### Community 1 - "ComposerPolicyGuardTest.php"
Cohesion: 0.08
Nodes (58): assertFixtureRouteFails(), assertFixtureRouteHasNoMutation(), assertGuardStructure(), assertionEnvironment(), assertNoComposerRan(), assertOnlyVersionProbeRan(), assertParserRejects(), assertTrue() (+50 more)

### Community 2 - "Phase 1: Constraint Resolution and Security Control - Research"
Cohesion: 0.04
Nodes (44): Alternatives Considered, Anti-Patterns to Avoid, Applicable ASVS Categories, Architectural Responsibility Map, Architecture Patterns, Assumptions Log, Code Examples, Common Pitfalls (+36 more)

### Community 3 - "AGENTS.md"
Cohesion: 0.05
Nodes (39): Anti-Patterns, API token tenancy flow, Architectural Constraints, Architecture, Bypassing the workspace boundary, Code Style, Comments, Component Responsibilities (+31 more)

### Community 4 - "Setup"
Cohesion: 0.07
Nodes (9): Setup, Database, Env, Key, Migrations, Url, Livewire\Component, StepInterface (+1 more)

### Community 5 - "InstallApplication"
Cohesion: 0.12
Nodes (8): InstallApplication, Workspace, UpgradeApplication, checkMigrations(), getPastMigrations(), pendingMigrations(), runMigrations(), Illuminate\Database\Console\Migrations\BaseCommand

### Community 6 - "RegisterController.php"
Cohesion: 0.04
Nodes (44): Alternatives Considered, Anti-Patterns to Avoid, Applicable ASVS Categories, Architectural Responsibility Map, Architecture Patterns, Assumptions Log, Code Examples, Common Pitfalls (+36 more)

### Community 7 - "Illuminate\Database\Migrations\Migration"
Cohesion: 0.10
Nodes (9): CreateUsersTable, CreatePasswordResetsTable, CreateWorkspacesTable, CreateInvitationsTable, CreateFailedJobsTable, AdjustUsersTable, CreateApiTokensTable, RemoveApiTokenFromUsersTable (+1 more)

### Community 8 - "Phase 2 Plan 02: Reproducible Dependency Snapshot Summary"
Cohesion: 0.08
Nodes (24): Accomplishments, Audit table (audit --locked), Auto-fixed Issues, ci.yml unchanged (Phase 3 boundary), Decisions Made, Dependency graph, Deviations from Plan, Files Created/Modified (+16 more)

### Community 9 - "Architecture Research"
Cohesion: 0.08
Nodes (24): Anti-Pattern 1: Ignoring PHP Platform Requirements, Anti-Pattern 2: Treating a Security Metapackage Conflict as a Reason to Drop Security Checks, Anti-Pattern 3: Rewriting CI or Core Integration for a Runtime Upgrade, Anti-Patterns, Architectural Patterns, Architecture Research, Build Order and Minimal File-Level Changes, Compatibility-Release Flow (+16 more)

### Community 10 - "Illuminate\Contracts\View\View"
Cohesion: 0.15
Nodes (6): LoginController, ProfileController, ProfileUpdateRequest, Illuminate\Contracts\View\View, Illuminate\Foundation\Auth\AuthenticatesUsers, Illuminate\Validation\Rule

### Community 11 - "Illuminate\Foundation\Testing\RefreshDatabase"
Cohesion: 0.09
Nodes (13): CreatesApplication, Illuminate\Foundation\Testing\RefreshDatabase, Illuminate\Foundation\Testing\TestCase, Illuminate\Foundation\Testing\WithFaker, AuthConfigDisabledTest, AuthConfigEnabledTest, NewUserInvitationTest, SetupControllerTest (+5 more)

### Community 12 - "Closure"
Cohesion: 0.16
Nodes (6): LocaleMiddleware, OwnsCurrentWorkspace, OwnsRequestedWorkspace, RedirectIfAuthenticated, RequireWorkspace, Closure

### Community 13 - "Controller"
Cohesion: 0.14
Nodes (10): ConfirmPasswordController, ForgotPasswordController, Controller, SetupController, Illuminate\Foundation\Auth\Access\AuthorizesRequests, Illuminate\Foundation\Auth\ConfirmsPasswords, Illuminate\Foundation\Auth\SendsPasswordResetEmails, Illuminate\Foundation\Bus\DispatchesJobs (+2 more)

### Community 14 - "Architecture"
Cohesion: 0.11
Nodes (18): Anti-Patterns, API token tenancy flow, Architectural Constraints, Architecture, Bypassing the workspace boundary, Component Responsibilities, Cross-Cutting Concerns, Data Flow (+10 more)

### Community 15 - "Phase 2: Reproducible Dependency Snapshot - Context"
Cohesion: 0.11
Nodes (18): Advisory exception at lockfile review (resolves the D-02 expiry), Canonical References, Claude's Discretion, Deferred Ideas, Established Patterns, Existing Code Insights, Files this phase touches or verifies, Implementation Decisions (+10 more)

### Community 16 - "Implications for Roadmap"
Cohesion: 0.11
Nodes (18): Architecture Approach, Confidence Assessment, Critical Pitfalls, Executive Summary, Expected Features, Gaps to Address, Implications for Roadmap, Key Findings (+10 more)

### Community 17 - "Illuminate\Http\RedirectResponse"
Cohesion: 0.23
Nodes (6): ResetPasswordController, SwitchWorkspaceController, WorkspaceUsersController, Illuminate\Foundation\Auth\ResetsPasswords, Illuminate\Http\RedirectResponse, Illuminate\Http\Request

### Community 18 - "Phase 1: Constraint Resolution and Security Control - Context"
Cohesion: 0.12
Nodes (16): Canonical References, Constraint-resolution boundary, Deferred Ideas, Dependency-security safeguard, Established Patterns, Existing Code Insights, Implementation Decisions, Integration Points (+8 more)

### Community 19 - "Phase 2 Plan 01: Reproducible Dependency Snapshot Summary"
Cohesion: 0.12
Nodes (16): Accomplishments, Auto-fixed Issues, Decisions Made, Dependency graph, Deviations from Plan, Files Created/Modified, Issues Encountered, Metrics (+8 more)

### Community 20 - "Pitfalls Research"
Cohesion: 0.12
Nodes (16): Critical Pitfalls, Evidence Scope, Integration Gotchas, "Looks Done But Isn't" Checklist, Performance Traps, Pitfall 1: Bypassing the advisory conflict instead of resolving the vulnerable constraint, Pitfall 2: Changing the PHP range without understanding its real semantics, Pitfall 3: Treating a successful solver run as PHP 8.4 application compatibility (+8 more)

### Community 21 - "Illuminate\Foundation\Http\FormRequest"
Cohesion: 0.16
Nodes (6): Workspace, WorkspacesController, CreateWorkspaceRequest, WorkspaceStoreRequest, WorkspaceUpdateRequest, Illuminate\Foundation\Http\FormRequest

### Community 22 - "User"
Cohesion: 0.17
Nodes (6): WorkspacesRepository, AddWorkspaceMember, Workspace, CreateWorkspace, Illuminate\Contracts\Pagination\LengthAwarePaginator, Sendportal\Base\Repositories\BaseEloquentRepository

### Community 23 - "Phase 01 Plan 04: Composer Policy Entry-Point Guard Summary"
Cohesion: 0.12
Nodes (15): Accomplishments, Auto-fixed Issues, Complete tracked-route audit, Decisions Made, Deviations from Plan, Files Created/Modified, Issues Encountered, Known Stubs (+7 more)

### Community 24 - "Stack Research"
Cohesion: 0.12
Nodes (15): 1. Keep the PHP constraint semantically correct; simplify rather than widen it, 2. Remove Roave and use Composer’s native security policy, 3. Make stable, locked resolution the installation contract, Alternatives Considered, Core Technologies, Development Tools, Installation and Verification, Recommended Stack (+7 more)

### Community 25 - "TestCase"
Cohesion: 0.02
Nodes (111): Communities (183 total, 36 thin omitted), Community 0 - "Workspace", Community 103 - "01-01-PLAN.md", Community 104 - "01-09-PLAN.md", Community 105 - "01-10-PLAN.md", Community 106 - "01-11-PLAN.md", Community 107 - "Standard Stack", Community 108 - "User Constraints (from CONTEXT.md)" (+103 more)

### Community 26 - "Phase 01 Plan 01: Isolated PHP 8.4 Composer Solver Evidence Summary"
Cohesion: 0.13
Nodes (14): Accomplishments, Auto-fixed Issues, Decisions Made, Deviations from Plan, Files Created/Modified, Issues Encountered, Known Stubs, Next Phase Readiness (+6 more)

### Community 27 - "Phase 01 Plan 02: PHP Support and Native Advisory Policy Summary"
Cohesion: 0.13
Nodes (14): Accomplishments, Auto-fixed Issues, Decisions Made, Deviations from Plan, Files Created/Modified, Isolated PHP 8.4 Evidence, Issues Encountered, Known Stubs (+6 more)

### Community 28 - "Phase 01 Plan 07: Hardened Composer Policy Boundary Summary"
Cohesion: 0.13
Nodes (14): Accomplishments, Auto-fixed Issues, Decisions Made, Deviations from Plan, Files Created/Modified, Issues Encountered, Known Stubs, Next Phase Readiness (+6 more)

### Community 29 - "Phase 01 Plan 12: PHP Provenance Audit Closure Summary"
Cohesion: 0.13
Nodes (14): Accomplishments, Auto-fixed Issues, Decisions Made, Deviations from Plan, Files Created/Modified, Known Stubs, Mutation checks (assertions proven non-vacuous), Next Phase Readiness (+6 more)

### Community 30 - "Phase 01 Plan 05: Trusted Composer Distribution and Per-Chain Audit Summary"
Cohesion: 0.14
Nodes (13): Accomplishments, Auto-fixed Issues, Decisions Made, Deviations from Plan, Files Created/Modified, Issues Encountered, Next Phase Readiness, Performance (+5 more)

### Community 31 - "Phase 01 Plan 09: Compound Shell and Inline PHP Audit Summary"
Cohesion: 0.14
Nodes (13): Accomplishments, Decisions Made, Deviations from Plan, Files Created/Modified, Issues Encountered, Known Stubs, Next Phase Readiness, Performance (+5 more)

### Community 32 - "Phase 01 Plan 10: Route Audit Closure Summary"
Cohesion: 0.14
Nodes (13): Accomplishments, Auto-fixed Issues, Decisions Made, Deviations from Plan, Files Created/Modified, Known Stubs, Next Phase Readiness, Performance (+5 more)

### Community 33 - "Phase 01 Plan 11: Composer Script and PHP Dispatch Closure Summary"
Cohesion: 0.14
Nodes (13): Accomplishments, Auto-fixed Issues, Decisions Made, Deviations from Plan, Files Created/Modified, Known Stubs, Next Phase Readiness, Performance (+5 more)

### Community 34 - "Feature Research"
Cohesion: 0.14
Nodes (13): Add After Validation (v1.x), Anti-Features (Commonly Requested, Often Problematic), Dependency Notes, Differentiators (Reliability Beyond a One-Time Green Install), Feature Dependencies, Feature Landscape, Feature Prioritization Matrix, Feature Research (+5 more)

### Community 35 - "self"
Cohesion: 0.11
Nodes (4): self, ExistingUserInvitationTest, SetupTest, SwitchWorkspaceTest

### Community 36 - "Phase 01 Plan 03: Fail-Closed Composer Policy Summary"
Cohesion: 0.15
Nodes (12): Accomplishments, Auto-fixed Issues, Decisions Made, Deviations from Plan, Files Created/Modified, Known Stubs, Next Phase Readiness, Performance (+4 more)

### Community 37 - "Phase 01 Plan 08: Nested Shell Evaluator Audit Summary"
Cohesion: 0.15
Nodes (12): Accomplishments, Auto-fixed Issues, Decisions Made, Deviations from Plan, Issues Encountered, Known Stubs, Next Phase Readiness, Performance (+4 more)

### Community 38 - "Phase 01: Constraint Resolution and Security Control - Pattern Map"
Cohesion: 0.15
Nodes (12): CI installation and database-matrix preservation (downstream Phase 3), `composer.json` (configuration, dependency-resolution), File Classification, Formatting, Installation documentation baseline (no Phase 1 edit), Metadata, No Analog Found, Pattern Assignments (+4 more)

### Community 39 - "Goal Achievement"
Cohesion: 0.15
Nodes (12): Anti-Patterns Found, Behavioral Spot-Checks, Data-Flow Trace (Level 4), Deferred Items, Gaps Summary, Goal Achievement, Key Link Verification, Observable Truths (+4 more)

### Community 40 - "Phase 2: Reproducible Dependency Snapshot - Research"
Cohesion: 0.15
Nodes (12): Architectural Responsibility Map, Assumptions Log, Don't Hand-Roll, Environment Availability, Metadata, Open Questions (RESOLVED), Package Legitimacy Audit, Phase 2: Reproducible Dependency Snapshot - Research (+4 more)

### Community 41 - "composer-policy"
Cohesion: 0.29
Nodes (9): delegateComposer(), isolatedComposerEnvironment(), isReadableRegularFile(), reject(), rejectOverrides(), removeComposerHome(), repositoryDistributionPaths(), runComposerProbe() (+1 more)

### Community 42 - "Route Audit Completeness"
Cohesion: 0.17
Nodes (11): Current Focus, Eliminated, Evidence, Normalized grammar to support, Precise exclusions, Required invariant, Required regression fixture matrix, Resolution (+3 more)

### Community 43 - "Goal Achievement"
Cohesion: 0.17
Nodes (11): Anti-Patterns Found, Behavioral Spot-Checks, Gaps Summary, Goal Achievement, Human Verification Required, Key Link Verification, Observable Truths (Success Criteria), Orchestrator Deviation Checks (confirmed, not taken on faith) (+3 more)

### Community 44 - "SendPortal PHP 8.4 Compatibility"
Cohesion: 0.17
Nodes (11): Active, Constraints, Context, Core Value, Evolution, Key Decisions, Out of Scope, Requirements (+3 more)

### Community 45 - "AppServiceProvider.php"
Cohesion: 0.09
Nodes (21): Boot + Core integration proof — artisan CLI in CI, no product/test code change (RUNTIME-01, RUNTIME-04), Canonical References, CI trigger unchanged, Claude's Discretion, Database matrix unchanged (RUNTIME-02, RUNTIME-03), Deferred Ideas, Established Patterns, Existing Code Insights (+13 more)

### Community 46 - "SetupTest"
Cohesion: 0.13
Nodes (8): PendingInvitationController, Invitation, AcceptInvitation, Workspace, Workspace, SendInvitation, Carbon, Illuminate\Database\Eloquent\Model

### Community 47 - "Sendportal"
Cohesion: 0.18
Nodes (10): 1.0.0 - 2020-06-09, 1.0.1 - 2020-06-11, 1.0.2 - 2020-06-22, 1.0.3 - 2020-07-21, 2.0.0 - 2021-02-08, 2.0.1 - 2021-03-08, 2.0.2 - 2021-04-30, 2.0.3 - 2021-07-05 (+2 more)

### Community 48 - "Codebase Concerns"
Cohesion: 0.18
Nodes (10): Codebase Concerns, Dependencies at Risk, Fragile Areas, Known Bugs, Missing Critical Features, Performance Bottlenecks, Scaling Limits, Security Considerations (+2 more)

### Community 49 - "Requirements: SendPortal PHP 8.4 Compatibility"
Cohesion: 0.18
Nodes (10): Composer Compatibility, Continuous Integration, Dependency Security & Reproducibility, Out of Scope, Requirements: SendPortal PHP 8.4 Compatibility, Runtime Validation, Traceability, Upgrade Hardening (+2 more)

### Community 50 - "Project State"
Cohesion: 0.18
Nodes (10): Accumulated Context, Blockers/Concerns, Current Position, Decisions, Deferred Items, Pending Todos, Performance Metrics, Project Reference (+2 more)

### Community 51 - "ComposerPolicyLivePackagistTest.php"
Cohesion: 0.31
Nodes (8): liveAssert(), liveAssertDirectMetadata(), liveAssertExactManifest(), liveCopyRepository(), liveEnvironment(), liveFail(), livePackageVersion(), liveRun()

### Community 52 - "WorkspaceInvitationStoreRequest"
Cohesion: 0.11
Nodes (18): Direct (non-guarded) invocation for read-only diagnostics, Fail-closed via default GitHub Actions semantics — no custom gating logic, File Classification, `.github/workflows/ci.yml` — install step (drop `--no-scripts`), `.github/workflows/ci.yml` — matrix `container` list (add `:8.4`), `.github/workflows/ci.yml` — new step: Composer metadata validate, `.github/workflows/ci.yml` — new step: dependency audit, `.github/workflows/ci.yml` — new step: Laravel/Core boot + route-registration proof (+10 more)

### Community 53 - "composer.json"
Cohesion: 0.20
Nodes (9): description, extra, laravel, dont-discover, license, minimum-stability, name, prefer-stable (+1 more)

### Community 54 - "Coding Conventions"
Cohesion: 0.20
Nodes (9): Code Style, Coding Conventions, Comments, Error Handling, Function Design, Import Organization, Logging, Module Design (+1 more)

### Community 55 - "Testing Patterns"
Cohesion: 0.20
Nodes (9): Common Patterns, Coverage, Fixtures and Factories, Mocking, Test File Organization, Test Framework, Test Structure, Test Types (+1 more)

### Community 56 - "Phase 2 — Validation Strategy"
Cohesion: 0.20
Nodes (9): audit-milestone §5.5 distinguishes NOT-VALIDATED (draft) from PARTIAL (validated + nyquist_compliant: false) (#2117), Manual-Only Verifications, Per-Task Verification Map, Phase 2 — Validation Strategy, Sampling Rate, status lifecycle: draft (seeded by plan-phase) → validated (set by validate-phase §6), Test Infrastructure, Validation Sign-Off (+1 more)

### Community 57 - "ChangePasswordRequest"
Cohesion: 0.28
Nodes (3): ChangePasswordController, ChangePasswordRequest, Illuminate\View\View

### Community 58 - "require"
Cohesion: 0.22
Nodes (9): require, guzzlehttp/guzzle, laravel/framework, laravel/horizon, laravel/tinker, laravel/ui, livewire/livewire, mettle/sendportal-core (+1 more)

### Community 59 - "External Integrations"
Cohesion: 0.22
Nodes (8): APIs & External Services, Authentication & Identity, CI/CD & Deployment, Data Storage, Environment Configuration, External Integrations, Monitoring & Observability, Webhooks & Callbacks

### Community 60 - "Phase 01 Plan 06: Canonical Composer Boundary and Live PHP 8.4 Evidence"
Cohesion: 0.22
Nodes (8): Accomplishments, Auto-fixed Issues, Deviations from Plan, Issues Encountered, Phase 01 Plan 06: Canonical Composer Boundary and Live PHP 8.4 Evidence, Self-Check: PASSED, Task Commits, Verification

### Community 61 - "Phase 01 — Validation Strategy"
Cohesion: 0.22
Nodes (8): Manual-Only Verifications, Per-Task Verification Map, Phase 01 — Validation Strategy, Sampling Rate, status lifecycle: draft (seeded by plan-phase) → validated (set by validate-phase §6), Test Infrastructure, Validation Sign-Off, Wave 0 Requirements

### Community 62 - "Roadmap: SendPortal PHP 8.4 Compatibility"
Cohesion: 0.22
Nodes (8): Overview, Phase 1: Constraint Resolution and Security Control, Phase 2: Reproducible Dependency Snapshot, Phase 3: PHP 8.4 Runtime, Core Integration, and CI Verification, Phase Details, Phases, Progress, Roadmap: SendPortal PHP 8.4 Compatibility

### Community 63 - "config"
Cohesion: 0.25
Nodes (8): php-http/discovery, config, allow-plugins, optimize-autoloader, policy, preferred-install, sort-packages, ignore-unreachable

### Community 64 - "scripts"
Cohesion: 0.25
Nodes (8): scripts, post-autoload-dump, post-create-project-cmd, post-root-package-install, Illuminate\\Foundation\\ComposerScripts::postAutoloadDump, @php artisan key:generate --ansi, @php artisan package:discover --ansi, @php -r \"file_exists('.env') || copy('.env.example', '.env');\

### Community 65 - "Technology Stack"
Cohesion: 0.25
Nodes (7): Configuration, Frameworks, Key Dependencies, Languages, Platform Requirements, Runtime, Technology Stack

### Community 66 - "Codebase Structure"
Cohesion: 0.25
Nodes (7): Codebase Structure, Directory Layout, Directory Purposes, Key File Locations, Naming Conventions, Special Directories, Where to Add New Code

### Community 67 - "Phase 2: Reproducible Dependency Snapshot - Discussion Log"
Cohesion: 0.25
Nodes (7): Advisory exception review, Claude's Discretion, Deferred Ideas, Install-path contract, Lockfile provenance, Phase 2: Reproducible Dependency Snapshot - Discussion Log, Verification gate wiring

### Community 68 - "Architecture Patterns"
Cohesion: 0.25
Nodes (8): Anti-Patterns to Avoid, Architecture Patterns, Pattern 1: Resolve the advisory exception (D-01) against the locked version — RETAIN branch, Pattern 2: Prove lockfile provenance / zero drift (D-03), Pattern 3: Route the platform check (DEPS-02) — no guarded route exists, Pattern 4: Install-path contract (DEPS-04, D-05/D-06), Recommended Change Surface (files this phase touches), System Architecture Diagram

### Community 69 - "VerificationController"
Cohesion: 0.27
Nodes (3): WorkspaceInvitationsController, WorkspaceInvitationStoreRequest, Illuminate\Contracts\Validation\Validator

### Community 70 - "ApiTokenController"
Cohesion: 0.19
Nodes (5): ApiTokenFactory, InvitationFactory, UserFactory, WorkspaceFactory, Illuminate\Database\Eloquent\Factories\Factory

### Community 72 - "ignore-id"
Cohesion: 0.29
Nodes (7): audit, block, ignore-id, PKSA-3r5d-mb8f-1qw9, PKSA-m5cs-t1y6-qpcs, PKSA-mdq4-51ck-6kdq, advisories

### Community 73 - "Phase 1: Constraint Resolution and Security Control - Discussion Log"
Cohesion: 0.29
Nodes (6): Constraint change boundary, Deferred Ideas, Phase 1: Constraint Resolution and Security Control - Discussion Log, PHP support window, Security safeguard, the agent's Discretion

### Community 74 - "README.md"
Cohesion: 0.29
Nodes (6): Dependency management, Features, Installation, Introduction, Lockfile review, Requirements

### Community 75 - "Kernel"
Cohesion: 0.40
Nodes (3): Kernel, Illuminate\Console\Scheduling\Schedule, Illuminate\Foundation\Console\Kernel

### Community 76 - "Handler"
Cohesion: 0.47
Nodes (3): Handler, Illuminate\Foundation\Exceptions\Handler, Throwable

### Community 77 - "WorkspacesController.php"
Cohesion: 0.22
Nodes (8): 1. PHP 8.4 CI job runs green on push, 2. App installs and boots on PHP 8.4, 3. Composer governance gates enforce on 8.4, 4. Full PHPUnit matrix passes on 8.4 (MySQL + Postgres), Current Test, Gaps, Summary, Tests

### Community 78 - "require-dev"
Cohesion: 0.33
Nodes (6): require-dev, fakerphp/faker, mockery/mockery, nunomaduro/collision, phpunit/phpunit, spatie/laravel-ignition

### Community 79 - "01-03-PLAN.md"
Cohesion: 0.33
Nodes (5): Artifacts this phase produces, Source Coverage Audit, STRIDE Threat Register, Trust Boundaries, Unresolved Assumptions

### Community 80 - "01-04-PLAN.md"
Cohesion: 0.33
Nodes (5): Artifacts this phase produces, Source Coverage Audit, STRIDE Threat Register (ASVS L1), Trust Boundaries, Unresolved Assumptions

### Community 81 - "Phase 01: Code Review Report"
Cohesion: 0.33
Nodes (5): CR-01: Unmodeled Composer dispatch in ordinary tracked PHP paths bypasses the route audit, Critical Issues, Narrative Findings (AI reviewer), Phase 01: Code Review Report, Summary

### Community 82 - "Common Pitfalls"
Cohesion: 0.33
Nodes (6): Common Pitfalls, Pitfall 1: The guard rejects `check-platform-reqs` — DEPS-02 has no guarded route, Pitfall 2: Advisory rationale is hardcoded in the guard — rewriting D-01 without lockstep breaks installs, Pitfall 3: `content-hash` drift when D-01 edits the manifest, Pitfall 4: Installing before `.gitignore:19` is removed / lock is committed, Pitfall 5: `minimum-stability: dev` misread as unstable lock

### Community 83 - "RESEARCH COMPLETE"
Cohesion: 0.33
Nodes (6): Confidence Assessment, File Created, Key Findings, Open Questions, Ready for Planning, RESEARCH COMPLETE

### Community 84 - "NewUserInvitationTest"
Cohesion: 0.18
Nodes (7): RegisterController, ValidInvitation, getInvitationFromToken(), isInvalidInvitation(), isValidInvitation(), Illuminate\Contracts\Validation\Rule, Illuminate\Foundation\Auth\RegistersUsers

### Community 85 - "ComposerPolicyCommandContract"
Cohesion: 0.18
Nodes (10): Community Hubs (Navigation), Corpus Check, God Nodes (most connected - your core abstractions), Graph Freshness, Graph Report - sendportal  (2026-07-25), Import Cycles, Knowledge Gaps, Suggested Questions (+2 more)

### Community 86 - "Invitation.php"
Cohesion: 0.09
Nodes (12): User, RemoveUserFromWorkspace, Admin, Illuminate\Foundation\Auth\User, Illuminate\Testing\TestResponse, WorkspaceInvitationsControllerTest, assertLoginRedirect(), createUserAndWorkspace() (+4 more)

### Community 88 - "psr-4"
Cohesion: 0.40
Nodes (5): autoload, psr-4, App\\, Database\\Factories\\, Database\\Seeders\\

### Community 89 - "01-02-PLAN.md"
Cohesion: 0.40
Nodes (4): Artifacts this phase produces, Source Coverage Audit, STRIDE Threat Register, Trust Boundaries

### Community 90 - "01-05-PLAN.md"
Cohesion: 0.40
Nodes (4): Artifacts this phase produces, Source Coverage Audit, STRIDE Threat Register (ASVS L1), Trust Boundaries

### Community 91 - "01-06-PLAN.md"
Cohesion: 0.40
Nodes (4): Artifacts this phase produces, Source Coverage Audit, STRIDE Threat Register (ASVS L1), Trust Boundaries

### Community 92 - "01-07-PLAN.md"
Cohesion: 0.40
Nodes (4): Artifacts this phase produces, Source Coverage Audit, STRIDE Threat Register (ASVS L1), Trust Boundaries

### Community 93 - "01-08-PLAN.md"
Cohesion: 0.40
Nodes (4): Artifacts this phase produces, Source Coverage Audit, STRIDE Threat Register (ASVS L1), Trust Boundaries

### Community 94 - "Validation Architecture"
Cohesion: 0.40
Nodes (5): Phase Requirements → Test Map, Sampling Rate, Test Framework, Validation Architecture, Wave 0 Gaps

### Community 95 - "WorkspaceInvitationsControllerTest"
Cohesion: 0.19
Nodes (6): ApiToken, Carbon\Carbon, Illuminate\Database\Eloquent\Factories\HasFactory, Illuminate\Database\Eloquent\Relations\BelongsTo, Sendportal\Base\Models\BaseModel, WorkspaceApiTokenTest

### Community 103 - "01-01-PLAN.md"
Cohesion: 0.50
Nodes (3): Artifacts this phase produces, STRIDE Threat Register, Trust Boundaries

### Community 104 - "01-09-PLAN.md"
Cohesion: 0.50
Nodes (3): Source Coverage Audit, STRIDE Threat Register, Trust Boundaries

### Community 105 - "01-10-PLAN.md"
Cohesion: 0.50
Nodes (3): Source Coverage Audit, STRIDE Threat Register, Trust Boundaries

### Community 106 - "01-11-PLAN.md"
Cohesion: 0.50
Nodes (3): Source Coverage Audit, STRIDE Threat Register, Trust Boundaries

### Community 107 - "Standard Stack"
Cohesion: 0.50
Nodes (4): Alternatives Considered, Core, Standard Stack, Supporting (the frozen graph — informational, not to be re-resolved)

### Community 108 - "User Constraints (from CONTEXT.md)"
Cohesion: 0.50
Nodes (4): Claude's Discretion, Deferred Ideas (OUT OF SCOPE), Locked Decisions, User Constraints (from CONTEXT.md)

### Community 116 - "autoload-dev"
Cohesion: 0.67
Nodes (3): autoload-dev, psr-4, Tests\\

### Community 120 - "Security Domain"
Cohesion: 0.67
Nodes (3): Applicable ASVS Categories, Known Threat Patterns for this change, Security Domain

### Community 121 - "Code Examples"
Cohesion: 0.67
Nodes (3): Code Examples, DEPS-04 install contract (documentation targets), The full lockfile-review procedure (repeatable evidence, D-07)

### Community 122 - "Sources"
Cohesion: 0.67
Nodes (3): Primary (HIGH confidence — executed this session, PHP 8.4.23 + Composer 2.10.2), Secondary (MEDIUM confidence — official docs), Sources

### Community 172 - "Phase 3: PHP 8.4 Runtime, Core Integration, and CI Verification - Discussion Log"
Cohesion: 0.40
Nodes (4): Claude's Discretion, Deferred Ideas, Gray-area selection, Phase 3: PHP 8.4 Runtime, Core Integration, and CI Verification - Discussion Log

### Community 173 - "Phase 3 — Validation Strategy"
Cohesion: 0.20
Nodes (9): audit-milestone §5.5 distinguishes NOT-VALIDATED (draft) from PARTIAL (validated + nyquist_compliant: false) (#2117), Manual-Only Verifications, Per-Task Verification Map, Phase 3 — Validation Strategy, Sampling Rate, status lifecycle: draft (seeded by plan-phase) → validated (set by validate-phase §6), Test Infrastructure, Validation Sign-Off (+1 more)

### Community 174 - "Setup"
Cohesion: 0.12
Nodes (15): 1. Live GitHub Actions `:8.4` run, Anti-Patterns Found, Behavioral Spot-Checks, Deferred / Known Outstanding Item, Gaps Summary, Goal Achievement, Guard/Contract File Integrity (Prohibition Check), Human Verification Required (+7 more)

### Community 175 - "Setup.php"
Cohesion: 0.13
Nodes (14): Accomplishments, Decisions Made, Deviations from Plan, Files Created/Modified, Issues Encountered, Live-CI Reconciliation (post-execution, 2026-07-25), Local Verification Results, Next Phase Readiness (+6 more)

### Community 176 - "ApiTokenController"
Cohesion: 0.17
Nodes (4): ApiTokenController, ApiTokenStoreRequest, ApiTokenRepository, Sendportal\Base\Repositories\BaseTenantRepository

### Community 177 - "SetupTest"
Cohesion: 0.15
Nodes (7): AppServiceProvider, BroadcastServiceProvider, Illuminate\Contracts\Auth\MustVerifyEmail, Illuminate\Notifications\Notifiable, Illuminate\Support\ServiceProvider, auth.partials.logo, setup

### Community 178 - "03-01-PLAN.md"
Cohesion: 0.40
Nodes (4): Artifacts This Phase Produces, Spec-less Edge Probe — Flagged Assumptions, STRIDE Threat Register, Trust Boundaries

## Knowledge Gaps
- **872 isolated node(s):** `name`, `type`, `description`, `license`, `php` (+867 more)
  These have ≤1 connection - possible missing edges or undocumented components.
- **30 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `User` connect `Invitation.php` to `Workspace`, `self`, `InstallApplication`, `ApiTokenController`, `Illuminate\Foundation\Testing\RefreshDatabase`, `Controller`, `SetupTest`, `Illuminate\Http\RedirectResponse`, `SetupTest`, `NewUserInvitationTest`, `User`, `WorkspaceInvitationsControllerTest`?**
  _High betweenness centrality (0.016) - this node is a cross-community bridge._
- **Why does `Controller` connect `Controller` to `VerificationController`, `Illuminate\Contracts\View\View`, `SetupTest`, `ApiTokenController`, `Illuminate\Http\RedirectResponse`, `NewUserInvitationTest`, `VerificationController`, `Illuminate\Foundation\Http\FormRequest`, `ChangePasswordRequest`?**
  _High betweenness centrality (0.016) - this node is a cross-community bridge._
- **Are the 15 inferred relationships involving `User` (e.g. with `.index()` and `.destroy()`) actually correct?**
  _`User` has 15 INFERRED edges - model-reasoned connections that need verification._
- **What connects `name`, `type`, `description` to the rest of the system?**
  _872 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `ComposerPolicyGuardTest.php` be split into smaller, more focused modules?**
  _Cohesion score 0.08125 - nodes in this community are weakly interconnected._
- **Should `Phase 1: Constraint Resolution and Security Control - Research` be split into smaller, more focused modules?**
  _Cohesion score 0.044444444444444446 - nodes in this community are weakly interconnected._
- **Should `AGENTS.md` be split into smaller, more focused modules?**
  _Cohesion score 0.05 - nodes in this community are weakly interconnected._