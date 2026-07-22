# Phase 1: Constraint Resolution and Security Control - Research

**Researched:** 2026-07-22
**Domain:** Composer dependency policy, PHP platform constraints, and Laravel 11 security compatibility
**Confidence:** MEDIUM

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions
- **D-01:** Retain PHP 8.2–8.4 support, with PHP 8.4 as the primary compatibility target. The user initially considered PHP 8.4-only for simplicity, but accepted the lower-risk route after confirming that the current range already admits PHP 8.4 and the real blocker is the Roave/Laravel conflict. — **Reversibility:** costly — narrowing or reopening the published runtime contract changes Composer metadata and the PHP CI matrix.

- **D-02:** Remove the incompatible `roave/security-advisories` metapackage only when it is replaced by Composer's native dependency-security policy and a blocking `composer audit --locked` gate. Do not use platform-emulation, ignore flags, broad advisory ignores, or non-blocking audit flags.

- **D-03:** Make the smallest compatible manifest/policy changes that a real PHP 8.4 Composer resolution requires. Preserve Laravel 11 and the existing `mettle/sendportal-core` integration; do not fork Core, upgrade Laravel major versions, or perform unrelated application refactors.

### the agent's Discretion
- Choose the exact current Composer 2.10+ policy syntax, toolchain floor, and compatible package bounds from official documentation and a real PHP 8.4 solver result.
- Use solver/prohibits evidence to determine whether any package constraint beyond the PHP declaration and Roave replacement must change. Keep every such change reviewable and directly tied to the compatibility objective.

### Deferred Ideas (OUT OF SCOPE)
None — discussion stayed within the phase scope.
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|------------------|
| COMP-01 | A clean PHP 8.4 environment can run `composer install --prefer-dist --no-interaction` successfully without platform-ignore flags or platform emulation. | A real PHP 8.4.23 / Composer 2.10.1 solver reproduced the present Roave conflict and then tested the native-policy candidate. The stable candidate currently has no compliant solution; use an isolated clean directory for final `composer install` proof after the decision checkpoint. [VERIFIED: local PHP 8.4 solver] |
| COMP-02 | Composer PHP constraints accurately declare PHP 8.2, 8.3, and 8.4 support. | `^8.2` already covers `>=8.2.0 <9.0.0`; simplify the existing redundant `^8.2|^8.3` only after retaining real-runtime coverage in Phase 3. [CITED: https://getcomposer.org/doc/articles/versions.md] |
| COMP-03 | The Roave conflict no longer blocks Laravel 11 and the replacement does not weaken platform checks. | Use Composer 2.10+ `config.policy.advisories` with explicit blocking/audit settings; however, the current advisory feed blocks all stable Laravel 11 tags. This is an explicit human decision checkpoint, not a safe dev-branch workaround. [VERIFIED: local PHP 8.4 solver] |
</phase_requirements>

## Summary

The repository is blocked by a real resolver conflict, not by the PHP declaration. On PHP 8.4.23 with Composer 2.10.1, the current manifest fails because `roave/security-advisories:dev-master` conflicts with `illuminate/mail >=9,<12.60`, while `laravel/framework ^11.0` replaces `illuminate/mail`. The root PHP declaration is redundant: `^8.2` already includes PHP 8.2, 8.3, and 8.4. [VERIFIED: local PHP 8.4 solver] [CITED: https://getcomposer.org/doc/articles/versions.md]

Composer 2.10's native `config.policy` is the right mechanism to replace Roave: advisory blocking defaults to enabled and audit failure defaults to `fail`; explicitly setting those values makes the intended control reviewable. `composer audit --locked` later audits the exact lockfile and exits non-zero for dependency-policy findings. [CITED: https://getcomposer.org/doc/06-config.md] [CITED: https://getcomposer.org/doc/03-cli.md]

There is a newly verified incompatibility in the locked decisions: removing Roave and enabling a stable-only native policy excludes every tagged Laravel 11 release due to seven active Packagist advisories. Leaving the existing global `minimum-stability: dev` lets the solver select `laravel/framework 11.x-dev`, but that is an untagged development branch and must not become the production resolution. A plan can safely prepare the evidence and candidate manifest, but must stop before accepting a dependency graph until the user decides whether to expand the Laravel/Core scope, accept narrowly documented residual risk, or wait for an upstream stable remedy. [VERIFIED: local PHP 8.4 solver] [CITED: https://packagist.org/security-advisories/]

**Primary recommendation:** Do not approve `11.x-dev`, `--no-blocking`, any platform-ignore option, or a broad advisory ignore; put a `checkpoint:human-verify` before the manifest change because the current stable Laravel 11/security-policy combination cannot satisfy all locked decisions simultaneously.

## Architectural Responsibility Map

| Capability | Primary Tier | Secondary Tier | Rationale |
|------------|--------------|----------------|-----------|
| PHP support declaration | Dependency manifest | CI/runtime | `composer.json` declares the contract; real PHP jobs prove it. [VERIFIED: codebase grep] |
| Advisory rejection during resolution | Composer policy | Packagist advisory service | Composer applies the manifest policy while resolving package candidates from the advisory source. [CITED: https://getcomposer.org/doc/06-config.md] |
| Exact-graph audit | Lockfile / Composer CLI | CI | `composer audit --locked` evaluates the lockfile independently of `vendor/`. [CITED: https://getcomposer.org/doc/03-cli.md] |
| PHP and extension enforcement | Runtime PHP | Composer CLI | `check-platform-reqs` reads real PHP/extensions rather than `config.platform`. [CITED: https://getcomposer.org/doc/03-cli.md] |
| Laravel/Core behavior | Laravel application | SendPortal Core package | This phase must preserve the existing host/package boundary; runtime boot proof belongs to Phase 3. [VERIFIED: codebase grep] |

## Project Constraints (from AGENTS.md)

- PHP 8.4 must remain a supported installation target; do not disable Composer platform checks or silently drop vulnerability protection.
- Preserve Laravel 11 and the `mettle/sendportal-core` integration; this milestone is compatibility work, not an application refactor or Laravel major upgrade.
- Commit a reviewed `composer.lock` only after a valid graph is resolved; that lockfile work is assigned to Phase 2.
- Keep the existing Laravel/PHP conventions if any PHP file becomes necessary: strict types, PSR-12 through the existing PHP-CS-Fixer configuration, framework validation/error boundaries, and no unrelated static-analysis tooling.
- Do not make application edits outside the GSD workflow. This phase should be limited to Composer policy/constraint evidence and the smallest justified manifest change.
- Before completion, run relevant Composer validation and report the evidence; later runtime and CI work must retain both database paths.

## Standard Stack

### Core

| Tool / Library | Version | Purpose | Why Standard |
|----------------|---------|---------|--------------|
| PHP | 8.2–8.4 declared; 8.4.23 available locally | Supported application runtime contract | Laravel 11 documents PHP 8.2–8.4 support. [CITED: https://laravel.com/docs/11.x/releases] |
| Composer | >=2.10 <3; 2.10.1 available locally | Resolver, native dependency policy, manifest validation | `config.policy` was added in Composer 2.10; use a 2.x toolchain with explicit CI version evidence. [CITED: https://getcomposer.org/changelog/2.10.0-RC2] |
| Composer native policy | `config.policy.advisories` | Blocks advisory-affected versions and makes audit fail | It replaces deprecated `config.audit` controls and avoids a conflict-only metapackage. [CITED: https://getcomposer.org/doc/06-config.md] |
| Laravel framework | `^11.0` currently, but stable graph is blocked | Existing host framework | Laravel 11 supports PHP 8.4, while its tagged releases are currently excluded by the native advisory policy. [CITED: https://laravel.com/docs/11.x/releases] [VERIFIED: local PHP 8.4 solver] |
| SendPortal Core | `^3.0` / latest stable `v3.0.2` | Existing newsletter domain package | Its stable package requires PHP `^8.2|^8.3` and Illuminate Support `^10|^11`; do not fork it in this phase. [CITED: https://packagist.org/packages/mettle/sendportal-core] |

### Supporting

| Tool | Version | Purpose | When to Use |
|------|---------|---------|-------------|
| `composer validate --strict --no-check-publish` | Composer 2.10+ | Validate manifest structure before solving | Every manifest/policy edit. [VERIFIED: local Composer 2.10.1] |
| `composer update --dry-run --prefer-dist --no-interaction --no-scripts --no-progress` | Composer 2.10+ | Gather resolver evidence without changing repository files | Phase 1 discovery only; run in a clean temporary copy when testing an uncommitted candidate. [CITED: https://getcomposer.org/doc/03-cli.md] |
| `composer audit --locked` | Composer 2.10+ | Audit the committed exact graph | Phase 2 onward, after `composer.lock` exists. [CITED: https://getcomposer.org/doc/03-cli.md] |
| `composer check-platform-reqs --lock` | Composer 2.10+ | Check the actual PHP/extensions against locked requirements | Phase 2/3 on each real supported PHP runtime. [CITED: https://getcomposer.org/doc/03-cli.md] |

### Alternatives Considered

| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Composer native policy | Keep `roave/security-advisories` | Rejected by locked decision D-02 and real solver conflict; Roave presently conflicts with Laravel's replaced `illuminate/mail` version. [VERIFIED: local PHP 8.4 solver] |
| Stable Laravel release | `laravel/framework:11.x-dev` through existing `minimum-stability:dev` | Reject: it happens to evade the tagged-version advisory filter but is not a reviewed stable production release. [VERIFIED: local PHP 8.4 solver] |
| Enforced advisory policy | `--no-blocking`, `--no-security-blocking`, `COMPOSER_POLICY=0`, or advisory ignores | Reject: these disable or weaken the control D-02 requires. Composer documents these as policy overrides. [CITED: https://getcomposer.org/doc/03-cli.md] |

**Installation:** No external package should be installed by Phase 1. The phase changes policy and constraints only; Phase 2 creates the reviewed lockfile.

## Architecture Patterns

### System Architecture Diagram

```text
operator / CI command on real PHP 8.4
             |
             v
       composer.json
       | PHP ^8.2 contract
       | Composer 2.10 policy
             |
             v
  Composer resolver + Packagist advisory feed
             |
             +--> stable Laravel 11 candidate --[active advisories]--> reject
             |
             +--> 11.x-dev candidate --[minimum-stability: dev]--> DO NOT ACCEPT
             |
             v
    human security/scope decision checkpoint
             |
             v
  smallest reviewed manifest change --> Phase 2 lockfile --> audit/platform/runtime gates
```

### Recommended Project Structure

```text
composer.json                                      # Phase 1 manifest and policy boundary
.planning/phases/01-constraint-resolution-and-security-control/
└── 01-RESEARCH.md                                # Solver evidence and planning constraints
```

### Pattern 1: Explicit Composer-native advisory policy

**What:** Put the policy in the root manifest and explicitly retain both resolution blocking and failing audit behavior.

**When to use:** Only once the human checkpoint establishes a compliant stable package set or an explicitly approved security exception policy.

**Example:**

```json
// Source: https://getcomposer.org/doc/06-config.md
{
  "require": {
    "php": "^8.2"
  },
  "config": {
    "policy": {
      "advisories": {
        "block": true,
        "audit": "fail"
      }
    }
  }
}
```

`block` prevents advisory-affected candidates in `update`/`require`/`remove`, while `audit: "fail"` makes audit findings fail. Do not add `ignore-id`, `ignore`, `ignore-severity`, or `policy: false` as part of this phase without a new security decision. [CITED: https://getcomposer.org/doc/06-config.md]

### Pattern 2: Separate evidence from the committed lockfile

**What:** Run exploratory solver commands in a temporary clean directory, preserve their output for review, and leave the first committed lockfile to Phase 2.

**When to use:** While Phase 1 must establish whether a candidate manifest is solvable but must not yet adopt a broad generated graph.

**Example:**

```bash
# Temporary copy only; no --ignore-platform-req*, --no-blocking, or --no-security-blocking.
composer validate --strict --no-check-publish
composer update --dry-run --prefer-dist --no-interaction --no-scripts --no-progress
```

Composer documents `--dry-run` as simulation and reserves `update` for writing exact versions into a lockfile. [CITED: https://getcomposer.org/doc/03-cli.md]

### Anti-Patterns to Avoid

- **Using `11.x-dev` as the “fix”:** It resolves only because the existing global `minimum-stability: dev` admits an untagged branch; it is not a reviewed stable dependency graph. [VERIFIED: local PHP 8.4 solver]
- **Using `config.platform` as compatibility proof:** It is useful only for a temporary solver experiment. `check-platform-reqs` intentionally ignores it and tests the real platform. [CITED: https://getcomposer.org/doc/03-cli.md]
- **Moving `composer.lock` into this phase:** The roadmap explicitly assigns lock creation and review to Phase 2. [VERIFIED: ROADMAP.md]

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Advisory version exclusions | A manually maintained conflict list or ad-hoc script | Composer 2.10 native policy backed by repository advisory data | Composer applies policy during resolution and `audit` evaluates it consistently. [CITED: https://getcomposer.org/doc/06-config.md] |
| PHP compatibility proof | A hand-authored list of “compatible” PHP versions | Real runtime solve/install plus `check-platform-reqs` | Actual PHP/extensions can differ from a simulated platform. [CITED: https://getcomposer.org/doc/03-cli.md] |
| Resolver diagnosis | Guessing package bounds or editing constraints blindly | Composer solver output and `prohibits` / `why-not` evidence | Composer exposes dependency-solving failure as exit code 2 and has dedicated diagnostics. [CITED: https://getcomposer.org/doc/03-cli.md] |

**Key insight:** Dependency policy is part of the resolver's input. Removing a conflicting metapackage is not a solution unless the replacement policy can select a stable, audited graph.

## Common Pitfalls

### Pitfall 1: Treating a dev branch as a compatible stable release

**What goes wrong:** Removing Roave while leaving `minimum-stability: dev` permits the solver to choose `laravel/framework 11.x-dev`, creating an apparently successful PHP 8.4 resolution.

**Why it happens:** Composer policy filters active advisories against tagged Laravel 11 releases; the dev branch is a different candidate outside those tagged version ranges.

**How to avoid:** Require stable-only solver evidence before accepting a graph. Keep the existing `minimum-stability: dev` under review; do not remove it mechanically until its actual purpose and every direct dev dependency are established.

**Warning signs:** Solver output says `11.x-dev`, a commit changes the lock's framework reference to a branch hash, or the review rationale calls it “patched” without an upstream tagged release. [VERIFIED: local PHP 8.4 solver]

### Pitfall 2: Replacing Roave but allowing environment overrides to disable policy

**What goes wrong:** The manifest is correct but CI/release commands set `COMPOSER_POLICY=0`, `COMPOSER_NO_BLOCKING=1`, or pass `--no-blocking`.

**Why it happens:** Composer environment variables and command flags override policy configuration.

**How to avoid:** Add a Phase 3 CI assertion that the policy-disabling variables are unset/false and grep workflow/install documentation for the prohibited flags; make `composer audit --locked` a blocking command after the lockfile exists.

**Warning signs:** A resolver suddenly accepts a known-vulnerable stable Laravel 11 tag, or CI contains a no-blocking/security-blocking flag. [CITED: https://getcomposer.org/doc/03-cli.md]

### Pitfall 3: Claiming PHP 8.2–8.4 support from a PHP 8.4-only resolution

**What goes wrong:** A lock selected on 8.4 may use newer transitive packages that cannot run on 8.2.

**Why it happens:** The root declaration is a promise, not runtime evidence. A `config.platform` simulation also does not test extensions or the actual executable.

**How to avoid:** Keep `php: "^8.2"`, use a temporary lowest-platform solver check for planning, then run Phase 3 real PHP 8.2, 8.3, and 8.4 verification with `composer check-platform-reqs --lock`.

**Warning signs:** A plan adds a `config.platform` entry to committed manifest solely to “support” an older PHP version, or removes the 8.2/8.3 CI legs. [CITED: https://getcomposer.org/doc/03-cli.md]

### Pitfall 4: Smuggling Phase 2 lockfile work into a manifest-only phase

**What goes wrong:** A broad `composer update` is run in the repository merely to test a candidate policy, producing a lock diff with unrelated dependency movement.

**Why it happens:** There is no current lockfile, and a normal Composer install/update creates one after resolution.

**How to avoid:** Perform Phase 1 solver experiments in an isolated directory with `--dry-run`; after the human decision, make lock creation/review a deliberate Phase 2 task.

**Warning signs:** `composer.lock` appears in a Phase 1 commit or an unexplained large package update is attached to a policy-only PR. [VERIFIED: composer.json and ROADMAP.md]

## Code Examples

Verified patterns from official sources:

### Diagnose the current resolver without changing repository files

```bash
# Source: https://getcomposer.org/doc/03-cli.md
composer validate --strict --no-check-publish
composer update --dry-run --prefer-dist --no-interaction --no-scripts --no-progress
composer prohibits laravel/framework 11.55.0 --tree
```

Use `prohibits` only to explain the actual candidate selected for diagnosis; do not pin `11.55.0` or an unverified release because it is mentioned in diagnostic output. [CITED: https://getcomposer.org/doc/03-cli.md]

### Locked-graph checks for the next phase

```bash
# Source: https://getcomposer.org/doc/03-cli.md
composer validate --strict --no-check-publish
composer audit --locked
composer check-platform-reqs --lock
```

These commands belong after Phase 2 creates `composer.lock`; audit uses the lock rather than the installed vendor directory, and platform checking reads the real platform. [CITED: https://getcomposer.org/doc/03-cli.md]

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| `config.audit` configuration and a third-party conflict metapackage | Composer `config.policy` plus blocking audit | Composer 2.10 added `policy`; legacy `audit` keys are deprecated | Use one native policy surface; do not mix policy advisories with legacy `audit.*` advisory settings. [CITED: https://getcomposer.org/doc/06-config.md] |
| PHP version list expressed with redundant OR branches | One accurate caret range, `^8.2` | Current Composer version-constraint semantics | It clearly declares PHP 8.2 through pre-9.0 support, including 8.4. [CITED: https://getcomposer.org/doc/articles/versions.md] |

**Deprecated/outdated:**

- `config.audit` advisory configuration: Composer documents it as deprecated in favor of `config.policy`; do not mix the two advisory configuration models. [CITED: https://getcomposer.org/doc/06-config.md]
- `--no-security-blocking`: Composer documents it as deprecated and notes that it disables the protection this phase must retain. [CITED: https://getcomposer.org/doc/03-cli.md]

## Assumptions Log

| # | Claim | Section | Risk if Wrong |
|---|-------|---------|---------------|
| A1 | A future upstream Laravel 11 tagged release may remediate the active advisories without a Laravel/Core major upgrade. | Open Questions | The phase could resume unchanged if verified; it is not true today. |
| A2 | The existing `minimum-stability: dev` may have historical uses beyond Roave. | Common Pitfalls | Removing it without checking could invalidate a legitimate direct dependency constraint. |

## Open Questions

1. **RESOLVED — How should the milestone resolve the current security/scope contradiction?**
   - Decision (2026-07-22): The project owner approved a narrowly documented internal-only residual-risk exception instead of a Laravel 12 upgrade, because stable `mettle/sendportal-core` currently permits only Illuminate 10/11. This is a risk acceptance, not proof that the advisories are unreachable.
   - Scope: permit only `PKSA-m5cs-t1y6-qpcs`, `PKSA-3r5d-mb8f-1qw9`, and `PKSA-mdq4-51ck-6kdq` through Composer 2.10+ `config.policy.advisories.ignore-id`, each with a reason. These are the current `laravel/framework` advisories whose affected ranges include Laravel 11 according to the Packagist API queried on 2026-07-22. Do not use package-wide/severity-wide ignores, `policy: false`, `--no-blocking`, platform-ignore flags, or `11.x-dev`.
   - Expiry and review: remove the exception at Phase 2 lockfile review or as soon as a compatible stable SendPortal Core release permits upgrading Laravel, whichever is first. All advisory IDs outside this exact list remain blocking and audit-failing.

2. **RESOLVED — Why is `minimum-stability` globally `dev`?**
   - Decision: Retain the existing setting in Phase 1 to avoid an unrelated constraint change without historical evidence. The exception must still produce a tagged stable Laravel 11 release; `prefer-stable: true` remains, and the isolated resolver/lockfile proof must reject every `*-dev` framework version.
   - Follow-up: Phase 2 lockfile review must reject an untagged Laravel framework version and record any other selected dev dependency for separate review.

3. **RESOLVED — How will Composer >=2.10 be enforced in all CI/release images?**
   - What we know: Composer 2.10.1 is present locally and `config.policy` was introduced in 2.10; the current CI uses third-party PHP images without an explicit Composer-version assertion. [VERIFIED: local Composer 2.10.1 and codebase grep] [CITED: https://getcomposer.org/changelog/2.10.0-RC2]
   - Decision: Phase 3 owns the CI assertion. It must print and require Composer 2.10+ before relying on `config.policy`; no CI image change is planned in this manifest-only phase.

## Environment Availability

| Dependency | Required By | Available | Version | Fallback |
|------------|-------------|-----------|---------|----------|
| PHP CLI | Real compatibility solver | ✓ | 8.4.23 | None; this is the target runtime. |
| Composer CLI | Manifest validation, resolver, policy | ✓ | 2.10.1 | None; Composer <2.10 cannot be relied on for `config.policy`. |
| Packagist access | Real solver/advisory feed | ✓ (with approved network access) | Current live metadata | Cache is not sufficient for final resolver evidence. |
| Required PHP extensions | Laravel/Composer solver preflight | ✓ locally | `curl`, `mbstring`, `pdo_mysql`, `pdo_pgsql`, `xml`, and other core extensions detected | Phase 3 must check the actual CI image extensions. |
| `composer.lock` | Locked audit/platform checks | ✗ | — | Phase 2 creates and reviews it. |

**Missing dependencies with no fallback:**

- A stable, native-policy-compliant Laravel 11 release set is currently unavailable according to the real solver. This is a decision/scope blocker, not a machine dependency. [VERIFIED: local PHP 8.4 solver]

**Missing dependencies with fallback:**

- None. Using `11.x-dev`, policy-disabling flags, or broad ignores is not an acceptable fallback under D-02/D-03.

## Validation Architecture

### Test Framework

| Property | Value |
|----------|-------|
| Framework | PHPUnit `^10.5` declared; unavailable until dependencies are installed. [VERIFIED: composer.json] |
| Config file | `phpunit.xml.dist` |
| Quick run command | `composer validate --strict --no-check-publish` |
| Full suite command | `vendor/bin/phpunit` (Phase 3 after a locked install) |

### Phase Requirements → Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| COMP-01 | Candidate solves and installs on real PHP 8.4 with no platform/security bypass | Clean-environment integration | Temporary copy: `composer install --prefer-dist --no-interaction --no-scripts --no-progress` | ❌ Wave 0 decision gate |
| COMP-02 | Manifest honestly declares all supported PHP releases | Manifest / solver | `composer validate --strict --no-check-publish`; then isolated PHP 8.2/8.4 solver evidence | ✅ `composer.json` |
| COMP-03 | Roave is replaced without disabling policy/platform checks | Resolver/security | `composer update --dry-run --prefer-dist --no-interaction --no-scripts --no-progress`; inspect for no bypass flags | ❌ Wave 0 decision gate |

### Sampling Rate

- **Per task commit:** `composer validate --strict --no-check-publish`
- **Per wave merge:** Isolated PHP 8.4 solver result, including the selected Laravel stability/version and policy status.
- **Phase gate:** Human resolution of the stable Laravel 11/native-advisory-policy conflict; do not proceed to Phase 2 lockfile work without it.

### Wave 0 Gaps

- [ ] `checkpoint:human-verify` — decide the explicit security/scope response to the seven active stable-Laravel-11 advisories.
- [ ] Isolated clean-directory command wrapper/documented procedure — ensures Phase 1 solver evidence cannot add `composer.lock` or `vendor/` to the repository.
- [ ] Phase 3 CI assertion — require Composer >=2.10 and reject policy-disabling environment variables/flags.

## Security Domain

### Applicable ASVS Categories

| ASVS Category | Applies | Standard Control |
|---------------|---------|-----------------|
| V2 Authentication | No | No authentication surface changes in this phase. |
| V3 Session Management | No | No session surface changes in this phase. |
| V4 Access Control | No | No authorization surface changes in this phase. |
| V5 Input Validation | Yes | `composer validate --strict` validates root package metadata before a solver run. [CITED: https://getcomposer.org/doc/03-cli.md] |
| V6 Cryptography | No | No cryptographic implementation changes; do not hand-roll security controls. |
| V14 Configuration | Yes | Commit native Composer policy, prohibit policy/platform bypasses, and assert the real runtime platform. [CITED: https://getcomposer.org/doc/06-config.md] |

### Known Threat Patterns for Composer/PHP dependency management

| Pattern | STRIDE | Standard Mitigation |
|---------|--------|---------------------|
| Policy bypass through `--no-blocking` or `COMPOSER_POLICY=0` | Tampering | Explicit policy, CI environment/command review, and blocking `composer audit --locked`. [CITED: https://getcomposer.org/doc/03-cli.md] |
| Platform bypass through `--ignore-platform-req*` | Tampering | Ban the flags and run `check-platform-reqs --lock` against real PHP/extensions. [CITED: https://getcomposer.org/doc/03-cli.md] |
| Dev-branch dependency selected under global dev stability | Supply-chain tampering | Require a stable-only reviewed resolution; do not lock `11.x-dev`. [VERIFIED: local PHP 8.4 solver] |
| Security control removed with Roave | Elevation of privilege | Replace it only with native policy plus a locked audit gate; no broad suppressions. [CITED: https://getcomposer.org/doc/06-config.md] |

## Sources

### Verified local evidence

- PHP 8.4.23 / Composer 2.10.1 — current manifest validation and solver output; Roave vs. `illuminate/mail` conflict reproduced.
- Isolated native-policy candidate — only `laravel/framework 11.x-dev` resolved under global dev stability; stable-only resolution rejected all Laravel 11 tags with seven PKSA IDs.
- `composer.json`, `.github/workflows/ci.yml`, `phpunit.xml.dist`, and roadmap/context files — current repository boundary and test topology.

### Official documentation (MEDIUM confidence)

- [Composer configuration](https://getcomposer.org/doc/06-config.md) — `config.policy`, advisory blocking/audit defaults, legacy `audit` migration, and policy override behavior.
- [Composer CLI](https://getcomposer.org/doc/03-cli.md) — install/update options, `audit --locked`, `check-platform-reqs`, policy and platform bypass flags, and diagnostics.
- [Composer version constraints](https://getcomposer.org/doc/articles/versions.md) — caret range semantics.
- [Composer 2.10 release notes](https://getcomposer.org/changelog/2.10.0-RC2) — native `policy` block introduction.
- [Laravel 11 release notes](https://laravel.com/docs/11.x/releases) — PHP 8.2–8.4 support and security-support end date.
- [Packagist SendPortal Core metadata](https://packagist.org/packages/mettle/sendportal-core) — latest stable Core PHP/Illuminate constraints and release date.
- [Packagist security advisories](https://packagist.org/security-advisories/) — current advisory authority referenced by Composer audit/policy.

## Metadata

**Confidence breakdown:**

- Standard stack: MEDIUM — Composer policy and PHP/Laravel support are officially documented; the stable Laravel 11 condition changes with the live advisory feed.
- Architecture: HIGH — the manifest, CI, and host/Core boundary are directly inspected in this repository.
- Pitfalls: HIGH — the most serious failure modes were reproduced with PHP 8.4.23 / Composer 2.10.1 solver runs.

**Research date:** 2026-07-22
**Valid until:** 2026-07-29 — Packagist advisories and available Laravel tags are fast-changing inputs.
