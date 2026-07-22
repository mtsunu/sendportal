# Phase 1: Constraint Resolution and Security Control - Context

**Gathered:** 2026-07-22
**Status:** Ready for planning

<domain>
## Phase Boundary

Establish a genuinely resolvable PHP 8.2–8.4 Composer contract for the existing Laravel 11 / SendPortal Core application, replacing the Roave conflict with a compatible dependency-security safeguard. This phase defines the allowed manifest and security-policy changes; it does not introduce product behavior, a Laravel major upgrade, or the committed lockfile work assigned to Phase 2.

</domain>

<decisions>
## Implementation Decisions

### PHP support contract
- **D-01:** Retain PHP 8.2–8.4 support, with PHP 8.4 as the primary compatibility target. The user initially considered PHP 8.4-only for simplicity, but accepted the lower-risk route after confirming that the current range already admits PHP 8.4 and the real blocker is the Roave/Laravel conflict. — **Reversibility:** costly — narrowing or reopening the published runtime contract changes Composer metadata and the PHP CI matrix.

### Dependency-security safeguard
- **D-02:** Remove the incompatible `roave/security-advisories` metapackage only when it is replaced by Composer's native dependency-security policy and a blocking `composer audit --locked` gate. Do not use platform-emulation, ignore flags, broad advisory ignores, or non-blocking audit flags.

### Constraint-resolution boundary
- **D-03:** Make the smallest compatible manifest/policy changes that a real PHP 8.4 Composer resolution requires. Preserve Laravel 11 and the existing `mettle/sendportal-core` integration; do not fork Core, upgrade Laravel major versions, or perform unrelated application refactors.

### the agent's Discretion
- Choose the exact current Composer 2.10+ policy syntax, toolchain floor, and compatible package bounds from official documentation and a real PHP 8.4 solver result.
- Use solver/prohibits evidence to determine whether any package constraint beyond the PHP declaration and Roave replacement must change. Keep every such change reviewable and directly tied to the compatibility objective.

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Milestone contract
- `.planning/ROADMAP.md` — Phase 1 goal, requirements, dependencies, and success criteria.
- `.planning/REQUIREMENTS.md` — COMP-01 through COMP-03 and non-negotiable safeguards.
- `.planning/PROJECT.md` — project constraints, current known Roave/Laravel resolver blocker, and out-of-scope boundary.
- `.planning/STATE.md` — current milestone decisions and known solver evidence gap.

### Research and implementation boundary
- `.planning/research/SUMMARY.md` — recommended Composer-native safeguard, known pitfalls, and evidence required from a real PHP 8.4 resolution.
- `composer.json` — current PHP constraints, dependencies, scripts, Roave requirement, stability settings, and plugin allow-list.
- `.github/workflows/ci.yml` — existing PHP 8.2/8.3 and MySQL/PostgreSQL matrix that later phases must retain while adding PHP 8.4.

No external specifications were referenced during discussion; use current official Composer documentation when selecting the exact policy syntax.

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `composer.json`: the only dependency-policy boundary; its Composer scripts already perform Laravel package discovery after a normal install.
- `.github/workflows/ci.yml`: existing container-based PHP × MySQL/PostgreSQL test topology, to be extended rather than replaced in Phase 3.

### Established Patterns
- The host delegates newsletter behavior to `mettle/sendportal-core` while Laravel 11 owns the application runtime. Compatibility changes must preserve this package boundary.
- CI already uses `composer install`, but currently has no lockfile and deliberately skips scripts for test installation; normal script-enabled install/boot validation is a later runtime concern.

### Integration Points
- Root Composer PHP and dependency constraints, `require-dev` security configuration, and Composer `config` are the Phase 1 integration points.
- The unchanged host/Core composition is exercised through `app/Providers/AppServiceProvider.php`, `routes/web.php`, and `routes/api.php` in Phase 3; do not modify them unless a reproduced PHP 8.4 defect demands it.

</code_context>

<specifics>
## Specific Ideas

The user prioritizes the smoothest reliable installation path and delegated the choice of the security mechanism and exact constraint changes, provided the application runs cleanly.

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within the phase scope.

</deferred>

---

*Phase: 1-Constraint Resolution and Security Control*
*Context gathered: 2026-07-22*
