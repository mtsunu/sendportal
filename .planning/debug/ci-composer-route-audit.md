---
status: complete
trigger: "CI fails before Phase 07 database tests because Composer route audit rejects tracked .codex tooling files"
created: 2026-09-19T15:36:00Z
updated: 2026-09-19T16:05:00Z
---

# CI Composer Route Audit

## Symptoms

- Expected: GitHub Actions reaches the MySQL and PostgreSQL PHPUnit steps so Phase 07 supported-driver concurrency can be verified.
- Actual: The `Verify Composer policy routes` step exits with status 1, and both database test steps are skipped.
- Errors: The route audit emits `unknown-source` records for tracked `.codex/*` tooling files whose prose contains tool or shell markers.
- Timeline: First reproduced after pushing the local branch to GitHub at commit `8bc07d6`; the follow-up tracker push at `a537642` reproduces the same failure.
- Reproduction: Run `php tests/Composer/ComposerPolicyGuardTest.php --route-audit` locally or inspect GitHub Actions runs `35451862312` and `35452179690`.

## Current Focus

- hypothesis: The production route audit scans tracked project tooling as if it were an approved Composer-bearing execution source; the safe fix is to align source provenance boundaries with the intended Composer-policy scope while preserving fail-closed behavior for application/deployment route sources.
- next_action: Keep the route-audit fix as a completed prerequisite while resolving the separate dependency-audit blocker exposed by the next CI run.

## Evidence

- timestamp: 2026-09-19T15:36:00Z
  source: GitHub Actions run 35452179690
  observation: `Verify Composer policy routes` failed; `Run Testsuite against MySQL` and `Run Testsuite against Postgres` were skipped.

- timestamp: 2026-09-19T15:36:00Z
  source: local `php tests/Composer/ComposerPolicyGuardTest.php --route-audit`
  observation: The audit reports tracked `.codex/agents/*`, `.codex/gsd-core/*`, and related tooling content as `unclassified-unknown-source` records.

- timestamp: 2026-09-19T15:50:00Z
  source: local focused regression and Composer policy checks
  observation: The explicit exclusion boundary removes committed `.codex/`, `.opencode/`, `graphify-out/`, `.phpunit.cache/`, and `.php-cs-fixer.cache` artifacts from production route evidence; `infra/` remains a failing unknown-source fixture. Route audit and full Composer policy guard both pass.

- timestamp: 2026-09-19T16:05:00Z
  source: GitHub Actions run 35452803770
  observation: The route-audit step passed after commit `f7f4c13`; the next `Audit dependencies` step failed, so database jobs were still skipped. This confirms the route-audit fix is effective and exposes a separate dependency blocker.

## Eliminated

- hypothesis: Phase 07 sender auto-capture code caused the CI failure.
  reason: The failing step runs before Composer installation, Laravel boot, or PHPUnit; its records point to route-audit provenance handling.

## Resolution

- root_cause: The route audit treated committed Codex/OpenCode metadata and generated graph/test/style caches as production route sources because their prose or serialized data contained generic shell/Composer markers.
- fix: Added an explicit route-audit exclusion predicate for those non-production tooling/artifact paths and regression fixtures; unknown marker-bearing paths such as `infra/` still produce fail-closed records.
- verification: Route audit passed with 8 classified records; full Composer policy guard passed; PHP lint, formatter dry-run, diff check, and Phase 07 SQLite focused suite passed (20 tests, 82 assertions, 1 intentional skip).
- files_changed: `tests/Composer/ComposerPolicyGuardTest.php`, `.planning/debug/ci-composer-route-audit.md`
