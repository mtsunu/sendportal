# Phase 2: Reproducible Dependency Snapshot - Context

**Gathered:** 2026-07-24
**Status:** Ready for planning

<domain>
## Phase Boundary

Freeze the Phase-1-approved PHP 8.4 dependency graph into a reviewed, committed `composer.lock` and make that lockfile the standard installation contract for local, CI, and deployment paths (DEPS-01…04). This phase produces the tracked lockfile, the review/verification evidence that proves it, the advisory-exception decision at lockfile review, and the documentation of the lockfile install contract. It does NOT add the dedicated PHP 8.4 CI job, the permanent CI gating of validate/platform/audit, the Laravel boot check, or the PHPUnit matrix run — those belong to Phase 3 (CI-01, CI-02, RUNTIME-01…04). It changes no application/product behavior and does not re-resolve or upgrade the approved graph.

</domain>

<decisions>
## Implementation Decisions

The user delegated these decisions ("aku ikuti rekomendasi terbaikmu" — follow the best recommendation). Each is locked to the Phase-1 decisions (D-01…D-03) and this phase's constraints.

### Advisory exception at lockfile review (resolves the D-02 expiry)
- **D-01:** The Phase-1 `laravel/framework` advisory exception was pinned to expire "at Phase 2 lockfile review" — which is this phase — so that wording is now consumed and must not survive verbatim into the committed lockfile. At lockfile review, run `composer audit --locked` against the frozen graph and verify whether the locked `laravel/framework` version still surfaces the three IDs (`PKSA-3r5d-mb8f-1qw9`, `PKSA-m5cs-t1y6-qpcs`, `PKSA-mdq4-51ck-6kdq`). **If patched in the locked version → drop the `ignore-id` entries entirely** (preferred; zero residual risk). **If still present → retain exactly those three**, rewrite each justification to name the exact locked `laravel/framework` version, and reset the expiry to a real forward condition: "the next dependency-upgrade review, or when a stable `mettle/sendportal-core` release permits an upgraded Laravel line, whichever comes first." — **Reversibility:** costly — the ignore-id set and its expiry are the security-policy contract; changing them later means re-auditing and re-obtaining owner residual-risk acceptance.
- **D-02:** Keep every other advisory blocking and failing. No package-wide ignores, no severity-wide ignores, no non-blocking audit mode, no platform/ignore bypass flags. The owner residual-risk acceptance recorded in Phase 1 carries forward for these three IDs only; do not broaden it.

### Lockfile provenance
- **D-03:** Treat the `composer.lock` currently on disk (produced during Phase 1's isolated PHP 8.4 install) as a **candidate, not the deliverable**. Regenerate/normalize it through the guarded path (`php bin/composer-policy update --prefer-dist --no-interaction`) on real PHP 8.4, confirm the resolved graph matches the Phase-1-approved resolution (no unexpected version drift), then commit the validated result. Do not trust the stray file's sync state — prove synchronization with `composer validate --strict` before committing. — **Reversibility:** costly — the committed lock is the reproducibility contract every downstream install consumes; a wrong or drifted snapshot propagates to CI and deployment.
- **D-04:** Remove `composer.lock` from `.gitignore` (`.gitignore:19`) so the reviewed lockfile can be tracked and committed. This is the enabling change for DEPS-01.

### Install-path contract (DEPS-04)
- **D-05:** Standard install = `install` against the committed lock, on every path; **do not invent new deployment automation** (honors the Phase-1 "no invented deploy scripts" boundary and D-03 minimalism). Clarify README so ordinary operator installs use the guarded `install` (which consumes the lock) and reserve `update` for intentional dependency-upgrade work only.
- **D-06:** CI keeps the existing guarded `php bin/composer-policy install --no-scripts …` with dev dependencies present (the test job needs them). Document the production deployment install as guarded `install --no-dev --optimize-autoloader` against the committed lock. — **Reversibility:** reversible — documentation and flag guidance, cheap to adjust.

### Verification gate wiring
- **D-07:** Phase 2 **establishes, runs, and captures evidence** for the three review checks on real PHP 8.4 — `composer validate --strict` (DEPS-01), `composer check-platform-reqs` (DEPS-02), `composer audit --locked` (DEPS-03) — and documents them as the repeatable lockfile-review procedure (all routed through the Phase-1 guard where a guarded route exists). **Permanent CI gating of these checks and the dedicated PHP 8.4 CI job are explicitly out of scope here and belong to Phase 3 (CI-01/CI-02).** This boundary is deliberate — the planner must not duplicate Phase 3 CI work.

### Claude's Discretion
- Exact command invocations, flag ordering, and whether the review commands are surfaced as `composer` script aliases, a README "Lockfile review" section, or both — choose the minimal reviewable form consistent with the Phase-1 guard.
- Exact wording of the refreshed advisory justifications/expiry, pinned to the real locked version observed at review time.
- Where the verification evidence is recorded (verification artifact vs. inline commit evidence).

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Milestone contract
- `.planning/ROADMAP.md` — Phase 2 goal, requirements (DEPS-01…04), dependency on Phase 1, and success criteria; also the Phase 3 boundary this phase must not cross.
- `.planning/REQUIREMENTS.md` — DEPS-01, DEPS-02, DEPS-03, DEPS-04 exact wording and the non-bypass safeguards; note DEPS-04 spans local/CI/deployment.
- `.planning/PROJECT.md` — reproducibility constraint (commit `composer.lock`), dependency-safety constraint, and out-of-scope bypass-flag boundary.
- `.planning/STATE.md` — accumulated Phase-1 decisions (guard wrapper, native policy, Composer floor, real-PHP-8.4 solver evidence) that this snapshot must preserve.

### Phase 1 carry-forward (MUST honor)
- `.planning/phases/01-constraint-resolution-and-security-control/01-CONTEXT.md` — D-01 (PHP 8.2–8.4, 8.4 primary), D-02 (Composer-native policy + `audit --locked`; the three ignore-ids and their "expires at Phase 2 lockfile review" clause this phase now resolves), D-03 (smallest compatible changes, preserve Laravel 11 / SendPortal Core).
- `.planning/research/SUMMARY.md` — recommended Composer-native safeguard and known pitfalls from the real PHP 8.4 resolution.

### Files this phase touches or verifies
- `composer.json` — the resolved constraints, `config.policy` advisory block (block/audit/ignore-id), stability settings, and scripts; the advisory-exception decision (D-01/D-02) edits this file's `ignore-id` map.
- `composer.lock` — the deliverable snapshot (currently on disk, untracked, gitignored); regenerate/validate/commit.
- `.gitignore` §19 — must drop the `composer.lock` ignore entry (D-04).
- `bin/composer-policy` — the Phase-1 guarded Composer wrapper; all supported mutation/verification commands route through it.
- `README.md` §"Dependency management" (lines ~33–46) — install docs to clarify `install` vs `update` and add the production `--no-dev` deployment guidance (D-05/D-06).
- `.github/workflows/ci.yml` §"Install composer dependencies" (line ~46) — already uses the guarded `install --no-scripts`; Phase 2 must keep it lockfile-consuming and NOT add the PHP 8.4 job or new gates (Phase 3 owns that).

No external (third-party) specifications were referenced; use current official Composer documentation for exact `validate --strict`, `check-platform-reqs`, and `audit --locked` semantics.

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `bin/composer-policy`: the Phase-1 guarded wrapper (Composer 2.10.2 tracked distribution, fail-closed, allows only canonical validate/audit/install/update). Every Phase-2 Composer mutation and verification runs through it.
- `.github/workflows/ci.yml`: already installs via `php bin/composer-policy install --no-scripts …` — lockfile-ready; extend documentation/behavior minimally, don't rewrite.
- `README.md` "Dependency management" section: already documents guarded `install`/`update`; refine rather than replace.

### Established Patterns
- Native Composer advisory policy lives in `composer.json` `config.policy` (block=true, audit=fail, scoped `ignore-id`), not a metapackage. The advisory-exception decision edits this map only.
- The host/Core boundary (`mettle/sendportal-core ^3.0`, Laravel `^11.0`) is fixed; the snapshot must freeze the exact approved graph without upgrading or re-resolving product behavior.

### Integration Points
- `composer.json` ↔ `composer.lock` synchronization is the core integration this phase proves (`validate --strict`).
- The lockfile flows into three consumers: local (README guarded `install`), CI (`ci.yml` guarded `install --no-scripts`), and deployment (documented guarded `install --no-dev --optimize-autoloader`).
- The advisory `ignore-id` map in `composer.json` is coupled to the exact locked `laravel/framework` version — the review must re-verify this coupling before finalizing D-01.

</code_context>

<specifics>
## Specific Ideas

The user explicitly delegated all four gray areas to the recommended path, prioritizing an honest, minimal, reproducible snapshot over adding new automation. The strongest owner-relevant point is the advisory-exception resolution (D-01/D-02): the "expires at Phase 2 lockfile review" clause must be actively resolved — verified against the locked version and either dropped or re-justified with a new forward expiry — never left as dead/self-contradictory text in the committed lockfile.

</specifics>

<deferred>
## Deferred Ideas

- **Dedicated PHP 8.4 CI job + permanent validate/platform/audit gating** — Phase 3 (CI-01, CI-02). Phase 2 only runs these as one-time review evidence.
- **Laravel boot check and PHPUnit MySQL/PostgreSQL matrix on PHP 8.4** — Phase 3 (RUNTIME-01…04).
- **Laravel major-version / security modernization** — separate milestone (already deferred in PROJECT.md / STATE.md).

None outside these — discussion stayed within phase scope.

</deferred>

---

*Phase: 2-Reproducible Dependency Snapshot*
*Context gathered: 2026-07-24*
