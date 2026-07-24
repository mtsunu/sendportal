---
phase: 02-reproducible-dependency-snapshot
plan: 02
subsystem: infra
tags: [composer, advisory-policy, php84, dependency-management, security, documentation]

# Dependency graph
requires:
  - phase: 02-reproducible-dependency-snapshot
    provides: "Tracked, drift-proven composer.lock (content-hash 41abd56c5581800607cc9d3c28862a76); guarded Composer wrapper; native advisory policy in composer.json"
provides:
  - "Resolved Laravel advisory exception on the RETAIN branch (three PKSA IDs re-justified against locked laravel/framework v11.55.0 with a real forward expiry; guard $rationale in lockstep)"
  - "Captured PHP 8.4 lockfile-review evidence: validate --strict, check-platform-reqs --lock, audit --locked (all EXIT 0)"
  - "README install contract: guarded install (committed lock) vs update (intentional upgrades), --no-dev deploy install, repeatable Lockfile-review procedure"
affects: [phase-03-ci, deployment]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Lockstep advisory contract: composer.json config.policy.advisories.ignore-id reasons byte-identical to bin/composer-policy $rationale; a guarded install exiting 0 is the drift detector (Pitfall 2)"
    - "Composer content-hash excludes the config section — editing advisory reasons legitimately leaves composer.lock byte-identical while json<->lock stays synchronized under validate --strict"
    - "Read-only lockfile-review checks run through the tracked, SHA-256-verified phar where no guarded route exists (check-platform-reqs)"

key-files:
  created: []
  modified:
    - composer.json
    - bin/composer-policy
    - README.md

key-decisions:
  - "Used guarded `update --lock` (freeze-only) instead of the plan-prescribed `update --prefer-dist` to refresh lock metadata — the full update re-resolves and drifts aws/aws-sdk-php (proven in Wave 1). This honors the freeze-only / zero-drift hard gate."
  - "RETAIN branch confirmed: audit --locked shows all three PKSA IDs still affect locked laravel/framework v11.55.0, so all three ignore-id entries are kept and re-justified (not dropped)."

patterns-established:
  - "Advisory-reason edits do not change the Composer content-hash (config section is excluded from the hash); json<->lock synchronization is proven by validate --strict, not by a hash delta."

requirements-completed: [DEPS-01, DEPS-02, DEPS-03, DEPS-04]

coverage:
  - id: D1
    description: "Three PKSA reasons re-justified on locked laravel/framework v11.55.0 with forward expiry; consumed 'Phase 2 lockfile review' clause removed; guard $rationale byte-identical"
    requirement: "DEPS-03"
    verification:
      - kind: automated
        ref: "grep -c 'PKSA-' composer.json -> 3; grep -c 'v11.55.0' composer.json -> 3; grep -c 'Phase 2 lockfile review' composer.json -> 0; identical string present in bin/composer-policy"
        status: pass
    human_judgment: false
  - id: D2
    description: "Guard manifest policy in lockstep (Pitfall 2 detector): guarded install exits 0"
    requirement: "DEPS-03"
    verification:
      - kind: automated
        ref: "php bin/composer-policy install --prefer-dist --no-interaction -> EXIT 0"
        status: pass
    human_judgment: false
  - id: D3
    description: "json<->lock synchronized after the advisory edit"
    requirement: "DEPS-01"
    verification:
      - kind: automated
        ref: "php bin/composer-policy validate --strict --no-interaction -> './composer.json is valid', EXIT 0"
        status: pass
    human_judgment: false
  - id: D4
    description: "Frozen graph passes the non-bypassed audit with exactly 3 ignored advisories affecting 1 package"
    requirement: "DEPS-03"
    verification:
      - kind: automated
        ref: "php bin/composer-policy audit --locked --no-interaction -> 'Found 3 ignored security vulnerability advisories affecting 1 package', EXIT 0"
        status: pass
    human_judgment: false
  - id: D5
    description: "Locked graph is PHP 8.4 platform-clean"
    requirement: "DEPS-02"
    verification:
      - kind: automated
        ref: "php tools/composer/composer-2.10.2.phar check-platform-reqs --lock --no-interaction -> every row 'success' on PHP 8.4.23, EXIT 0"
        status: pass
    human_judgment: false
  - id: D6
    description: "Regenerated lock has zero name->version drift from Plan 01"
    verification:
      - kind: automated
        ref: "diff of '\"name\"|\"version\"' lines (Plan 01 committed lock vs regenerated) empty; composer.lock byte-identical, content-hash unchanged 41abd56c5581800607cc9d3c28862a76"
        status: pass
    human_judgment: false
  - id: D7
    description: "README documents install-vs-update contract, --no-dev deploy install, and Lockfile-review procedure; ci.yml unchanged with no PHP 8.4 job"
    requirement: "DEPS-04"
    verification:
      - kind: automated
        ref: "grep -q 'no-dev' README.md; grep -q 'Lockfile review' README.md; grep -q 'no-scripts' .github/workflows/ci.yml; git diff --quiet HEAD -- .github/workflows/ci.yml (EXIT 0)"
        status: pass
    human_judgment: false

# Metrics
duration: 3min
completed: 2026-07-24
status: complete
---

# Phase 2 Plan 02: Reproducible Dependency Snapshot Summary

**Resolved the expiring Phase-1 Laravel advisory exception on the RETAIN branch — three PKSA IDs re-justified against locked laravel/framework v11.55.0 with a real forward expiry and the guard $rationale in lockstep — captured the PHP 8.4 lockfile-review evidence (validate/platform/audit all EXIT 0), and documented the committed-lock install contract, all with zero graph drift.**

## Performance

- **Duration:** ~3 min
- **Started:** 2026-07-24T19:44:52Z
- **Completed:** 2026-07-24T19:47:44Z
- **Tasks:** 2
- **Files modified:** 3 (composer.lock byte-identical, not re-committed)

## Accomplishments
- Rewrote the three advisory reasons (PKSA-3r5d-mb8f-1qw9, PKSA-m5cs-t1y6-qpcs, PKSA-mdq4-51ck-6kdq) in `composer.json` to name `laravel/framework v11.55.0`, record owner-accepted residual risk, and set a real forward expiry ("next dependency-upgrade review or when a stable mettle/sendportal-core release permits an upgraded Laravel line"). The consumed self-referential "Phase 2 lockfile review" clause no longer appears in the committed manifest.
- Edited the single `bin/composer-policy` `$rationale` constant byte-identically so the guard's strict-equality manifest-policy check stays in lockstep — proven by a guarded `install` exiting 0 (Pitfall-2 detector).
- Confirmed the RETAIN branch: `audit --locked` shows all three IDs still affect locked v11.55.0 (1 package, 3 ignored advisories, no non-ignored advisory).
- Captured DEPS-02 evidence: `check-platform-reqs --lock` reports every requirement row `success` on PHP 8.4.23, EXIT 0.
- Proved zero graph drift: name->version diff between the Plan 01 lock and the regenerated lock is empty; `composer.lock` is byte-identical (content-hash unchanged at `41abd56c5581800607cc9d3c28862a76`).
- Documented in `README.md` the committed-lock install contract (guarded `install` vs `update`), the production `install --no-dev --optimize-autoloader` deploy install, and a repeatable "Lockfile review" three-command procedure.
- Verified `.github/workflows/ci.yml` is byte-unchanged (still guarded `install ... --no-scripts` with dev deps; no PHP 8.4 job, no new gate — Phase 3 boundary respected).

## Task Commits

Each task was committed atomically:

1. **Task 1: Lockstep advisory refresh + lock re-sync + audit** - `6b2df99` (fix)
2. **Task 2: Platform-check evidence + install-contract documentation** - `ea04c01` (docs)

## Files Created/Modified
- `composer.json` - Three advisory reasons rewritten to name v11.55.0 with the forward expiry; consumed clause removed; `block: true` / `audit: fail` and exactly three IDs preserved.
- `bin/composer-policy` - `$rationale` constant edited byte-identically to the new composer.json reason (single occurrence, ~line 365).
- `README.md` - Install-vs-update contract, `--no-dev` deployment install, and "Lockfile review" three-command procedure.
- `composer.lock` - Unchanged (regenerated via guarded `update --lock`; byte-identical because the content-hash excludes the edited `config` section). Not re-committed; remains the Plan-01 committed snapshot.

## Verification Evidence (captured on PHP 8.4.23 + Composer 2.10.2)

### Guarded-install lockstep confirmation (Pitfall-2 detector)

| Command | Result |
|---------|--------|
| `php bin/composer-policy install --prefer-dist --no-interaction` | Installs from lock file; **EXIT 0** (guard manifest policy passes -> $rationale in lockstep) |

### Three review-command outputs

| Check | Command | Result | EXIT |
|-------|---------|--------|------|
| validate (DEPS-01) | `php bin/composer-policy validate --strict --no-interaction` | `./composer.json is valid` | **0** |
| platform (DEPS-02) | `php tools/composer/composer-2.10.2.phar check-platform-reqs --lock --no-interaction` | every row `success` on PHP 8.4.23 (php 8.4.23, all ext-* success, no failed/missing) | **0** |
| audit (DEPS-03) | `php bin/composer-policy audit --locked --no-interaction` | `Found 3 ignored security vulnerability advisories affecting 1 package` (laravel/framework) | **0** |

### Audit table (audit --locked)

| Advisory ID | Package | Severity | Title | Ignore reason (excerpt) |
|-------------|---------|----------|-------|-------------------------|
| PKSA-m5cs-t1y6-qpcs | laravel/framework | medium | Temporary Signed URL Path Confusion | "...residual risk on the locked laravel/framework v11.55.0 accepted by the project owner. The exception expires at the next dependency-upgrade review or when a stable mettle/sendportal-core release permits an upgraded Laravel line..." |
| PKSA-3r5d-mb8f-1qw9 | laravel/framework | high | CRLF injection in default email rule | (same rationale, byte-identical) |
| PKSA-mdq4-51ck-6kdq | laravel/framework | (n/a) | Laravel CRLF injection in default email rule (CVE-2026-48019) | (same rationale, byte-identical) |

All three ignore reasons render the refreshed rationale naming v11.55.0; no non-ignored advisory surfaced.

### Name->version diff result (zero drift)

| Check | Command | Result |
|-------|---------|--------|
| Zero drift | `diff` of `"name"|"version"` lines (Plan 01 committed lock vs regenerated) | **empty** (EXIT 0) |
| Content hash | `grep '"content-hash"' composer.lock` | `41abd56c5581800607cc9d3c28862a76` (unchanged) |
| Lock file changed? | `git diff --stat composer.lock` | no change (byte-identical) |

### Grep-count gates (composer.json)

| Gate | Result |
|------|--------|
| `grep -c 'PKSA-' composer.json` | `3` (exactly the three owner-approved IDs) |
| `grep -c 'Phase 2 lockfile review' composer.json` | `0` (consumed clause removed) |
| `grep -c 'v11.55.0' composer.json` | `3` (each reason names the locked version) |
| identical rationale in `bin/composer-policy` | present (1 occurrence) |

### ci.yml unchanged (Phase 3 boundary)

| Check | Result |
|-------|--------|
| `grep -q 'no-scripts' .github/workflows/ci.yml` | present (guarded `install ... --no-scripts`, dev deps kept) |
| `git diff --quiet HEAD -- .github/workflows/ci.yml` | **EXIT 0** (byte-unchanged; no PHP 8.4 job, no new gate) |

## Decisions Made
- **Guarded `update --lock` over the plan-prescribed `update --prefer-dist` for the lock refresh.** The plan (Task 1 step 3) says to run `update --prefer-dist` to refresh the content-hash. On this real environment that command re-resolves the full graph and drifts `aws/aws-sdk-php` (3.388.13 -> 3.389.0, published upstream since Phase 1), exactly as Wave 1 (Plan 01) proved. That violates the freeze-only / name->version-unchanged hard gate (D-03, Pitfall 3), which is an acceptance criterion of this plan too. `update --lock` re-writes lock metadata to sync with the edited composer.json **without** re-resolving versions ("Nothing to modify in lock file"), staying fully within the guarded, non-bypass path (`--lock` is not in the guard's bypass deny-list). See Deviations below.
- **RETAIN, not DROP.** D-01 required checking whether the locked version still surfaces the three IDs. `audit --locked` confirms all three still affect locked v11.55.0, so all three ignore-id entries were kept and re-justified rather than dropped.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Plan-prescribed `update --prefer-dist` re-resolves and drifts the graph; used guarded `update --lock`**
- **Found during:** Task 1 (Lockstep advisory refresh + lock re-sync)
- **Issue:** Task 1 step 3 literally instructs `php bin/composer-policy update --prefer-dist --no-interaction` to refresh the content-hash after the composer.json advisory edit. A full guarded `update` re-resolves the dependency graph and upgrades `aws/aws-sdk-php` (3.388.13 -> 3.389.0), a newer upstream release published since Phase 1. That is re-resolution drift and violates this plan's freeze-only hard gate (prohibition "Do not re-resolve or upgrade the approved graph"; acceptance criterion "name->version diff... empty"). Wave 1 (Plan 01) hit and documented the identical hazard.
- **Fix:** Refreshed the lock through the guarded, non-re-resolving path — `php bin/composer-policy update --lock --no-interaction` — which re-writes lock metadata to sync with the edited composer.json without re-resolving package versions. This was pre-authorized in the execution brief as the Wave-1-proven freeze-only route.
- **Verification:** name->version diff empty; `composer.lock` byte-identical; content-hash unchanged; `validate --strict` EXIT 0; guarded `install` consumes the lock with no fresh resolution.
- **Committed in:** `6b2df99` (Task 1 commit — composer.json + bin/composer-policy only; lock unchanged)
- **Contract compliance:** `--lock` is not in the guard's bypass-flag deny-list (`--working-dir`, `-d`, `--ignore-platform-req(s)`, `--no-blocking`, `--no-security-blocking`, `--no-audit`), so this stays within the guarded path.

### Observation (not a deviation)

**Content-hash did not change after the composer.json edit.** The critical-finding brief anticipated the content-hash would change because composer.json changed. In practice it did not: Composer computes the lock `content-hash` from hash-relevant manifest fields (require, require-dev, conflict, replace, provide, minimum-stability, prefer-stable, repositories, extra, etc.) and **deliberately excludes the `config` section**. The advisory edit touched only `config.policy.advisories.ignore-id`, so the hash is unaffected and `composer.lock` is byte-identical. json<->lock synchronization is proven by `validate --strict` (EXIT 0), which is the correct sync gate — a hash delta was never required for correctness. The freeze-only hard gate (name->version unchanged) holds trivially since the lock file did not change at all.

---

**Total deviations:** 1 auto-fixed (1 blocking) + 1 documented observation
**Impact on plan:** The deviation preserves the plan's stated invariant (freeze only, zero drift) against a real re-resolution hazard. No scope creep; exactly three IDs, `block: true`/`audit: fail` preserved, no bypass flags, ci.yml untouched, no new deploy automation, no Phase-3 CI work.

## Prohibitions Honored
- Exactly three PKSA IDs kept (no fourth); no package-wide or severity-wide ignore.
- `block: true` and `audit: fail` unchanged; no non-blocking audit mode.
- No bypass flags used (`--ignore-platform-reqs`, `--no-check-all`, `--no-check-platform-reqs`, `--no-audit`, `-d`, `--working-dir`) — the only non-guarded command was the read-only `check-platform-reqs --lock` via the tracked SHA-256-verified phar (Pattern 3: no guarded route exists).
- `bin/composer-policy` `$rationale` edited byte-identically to composer.json (lockstep mandatory).
- Consumed "Phase 2 lockfile review" clause removed from composer.json.
- `.github/workflows/ci.yml` byte-unchanged (verify-only).
- No new deploy automation (documentation only, D-05); no Phase-3 CI job/gate.

## Issues Encountered
- None beyond the pre-authorized `update --lock` deviation documented above, resolved within the guarded path.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- The frozen graph is now audit-clean (3 owner-accepted advisories, no non-ignored) and PHP 8.4 platform-clean, with json<->lock synchronized and the install contract documented for local/CI/deployment.
- Phase 3 owns the dedicated PHP 8.4 CI job, permanent validate/platform/audit gating, the Laravel boot check, and the PHPUnit matrix (CI-01, CI-02, RUNTIME-01..04) — all deliberately out of scope here.
- No blockers.

## Self-Check: PASSED
- FOUND: composer.json (PKSA count 3, v11.55.0 count 3, Phase-2-clause count 0)
- FOUND: bin/composer-policy (identical $rationale string present)
- FOUND: README.md (`no-dev` and `Lockfile review` present)
- FOUND commit: 6b2df99
- FOUND commit: ea04c01

---
*Phase: 02-reproducible-dependency-snapshot*
*Completed: 2026-07-24*
