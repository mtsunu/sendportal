# Phase 3: PHP 8.4 Runtime, Core Integration, and CI Verification - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-07-25
**Phase:** 3-PHP 8.4 Runtime, Core Integration, and CI Verification
**Areas discussed:** (all delegated to Claude's recommendation)

---

## Gray-area selection

| Option | Description | Selected |
|--------|-------------|----------|
| CI job shape & gates | Add `:8.4` to the container matrix; gates on all versions vs 8.4-only | |
| Script-enabled install | Drop `--no-scripts` vs add explicit `artisan package:discover` | |
| Boot / Core proof | Artisan CLI checks in CI vs committed PHPUnit smoke test | |
| Delegate all | Follow best minimal recommendation for all three | ✓ |

**User's choice:** Delegate all — follow best minimal recommendation, consistent with Phases 1–2 (honest, minimal, reproducible).
**Notes:** Same delegation pattern as Phase 2. No area was opened for interactive discussion; recommendations were locked directly into CONTEXT.md as D-01…D-07.

---

## Claude's Discretion

- CI job shape: add `:8.4` to existing container matrix (image tag confirmed to exist).
- Gate coverage: apply new gates uniformly across 8.2/8.3/8.4 (simpler than conditional 8.4-only, strictly stronger).
- Script-enabled install: drop `--no-scripts` so `post-autoload-dump → artisan package:discover` runs (faithful to RUNTIME-01).
- Boot/Core proof: read-only artisan `about` + `route:list` CI steps, no product/test code change.
- Step ordering, flag strings, artisan env provisioning, and the exact sendportal route asserted — left to planning/execution.

## Deferred Ideas

- HARD-01 (CI dependency-upgrade evidence summary) — v2.
- HARD-02 (tenant-safe Core behavior smoke test) — v2.
- HARD-03 (static analysis + coverage-config repair) — separate quality milestone.
- Laravel major-version / security modernization — separate milestone.
- Database engine version changes / CI trigger changes — out of scope.
