# Phase 2: Reproducible Dependency Snapshot - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-07-24
**Phase:** 02-reproducible-dependency-snapshot
**Areas discussed:** Advisory exception review, Lockfile provenance, Install-path contract, Verification gate wiring

---

The user was presented four phase-specific gray areas (multiSelect) and responded: "aku ikuti rekomendasi terbaikmu" (follow your best recommendation). All four areas were therefore resolved by Claude on the recommended path, grounded in the Phase-1 decisions (D-01…D-03) and the DEPS-01…04 constraints. Alternatives considered are recorded below.

---

## Advisory exception review

| Option | Description | Selected |
|--------|-------------|----------|
| Keep as-is | Retain the three ignore-ids with the existing "expires at Phase 2 lockfile review" wording | |
| Verify-then-decide | Run `audit --locked`; drop the ids if patched in the locked version, else retain exactly three with refreshed justification and a new forward expiry | ✓ |
| Drop unconditionally | Remove all three ignore-ids regardless of locked version | |

**User's choice:** Follow recommendation → verify-then-decide (drop if patched; otherwise retain three with version-pinned justification and a real forward expiry). All other advisories stay blocking; no broadening.
**Notes:** The original "expires at Phase 2 lockfile review" clause is consumed by this phase and must not survive verbatim into the committed lockfile. Owner residual-risk acceptance from Phase 1 carries for these three IDs only.

---

## Lockfile provenance

| Option | Description | Selected |
|--------|-------------|----------|
| Commit on-disk lock as-is | Trust and commit the composer.lock left by Phase 1's isolated install | |
| Regenerate + validate | Treat on-disk lock as candidate; regenerate via guarded `update` on PHP 8.4, confirm no drift from the Phase-1 graph, validate `--strict`, then commit | ✓ |

**User's choice:** Follow recommendation → regenerate/normalize through the guard, verify sync with `composer validate --strict`, then commit. Also drop `composer.lock` from `.gitignore` so it can be tracked.
**Notes:** Avoids trusting a stray artifact of uncertain sync state; the committed lock is the reproducibility contract for all downstream installs.

---

## Install-path contract

| Option | Description | Selected |
|--------|-------------|----------|
| Docs-only guarded install | Standard `install` against the lock everywhere; clarify README (install vs update); production `--no-dev`; no new automation | ✓ |
| Add deploy automation | Introduce a deployment install script/pipeline that runs the lockfile install | |

**User's choice:** Follow recommendation → docs-only. Ordinary installs use guarded `install` (consumes lock); `update` reserved for intentional upgrades; CI keeps guarded `install --no-scripts` with dev deps; production documented as guarded `install --no-dev --optimize-autoloader`.
**Notes:** Honors Phase-1 "do not invent deployment automation" boundary and D-03 minimalism.

---

## Verification gate wiring

| Option | Description | Selected |
|--------|-------------|----------|
| Evidence-only this phase | Run validate --strict / check-platform-reqs / audit --locked on PHP 8.4 as one-time review evidence; permanent CI gating deferred to Phase 3 | ✓ |
| Wire CI gates now | Add validate/platform/audit gating (and PHP 8.4 job) into CI in Phase 2 | |

**User's choice:** Follow recommendation → evidence-only in Phase 2; permanent CI gating and the dedicated PHP 8.4 CI job stay in Phase 3 (CI-01/CI-02).
**Notes:** Explicit boundary drawn so the planner does not duplicate Phase 3 CI work.

---

## Claude's Discretion

- Exact command invocations/flag ordering; whether review checks are surfaced as `composer` script aliases, a README section, or both.
- Exact wording of refreshed advisory justifications/expiry, pinned to the observed locked `laravel/framework` version.
- Where verification evidence is recorded (verification artifact vs. inline commit evidence).

## Deferred Ideas

- Dedicated PHP 8.4 CI job + permanent validate/platform/audit gating → Phase 3 (CI-01/CI-02).
- Laravel boot check + PHPUnit MySQL/PostgreSQL matrix on PHP 8.4 → Phase 3 (RUNTIME-01…04).
- Laravel major-version / security modernization → separate milestone.
