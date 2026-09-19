---
phase: 07-sender-auto-capture-data-integrity
verified: 2026-09-19T16:19:16Z
status: passed
score: 6/6 must-haves verified
behavior_unverified: 0
overrides_applied: 0
behavior_unverified_items: []
human_verification: []
---

# Phase 07: Sender Auto-Capture & Data Integrity Verification Report

**Phase Goal:** The sender library grows automatically from campaign activity without duplicate entries, and previously created or sent campaigns are unaffected by later sender edits or deletes.

**Verified:** 2026-09-19T16:19:16Z

**Status:** PASSED

## Goal Achievement

| # | Observable truth | Status | Evidence |
|---|---|---|---|
| 1 | A newly created Campaign captures a normalized workspace sender through direct, web, and API creation paths without changing the campaign's submitted From values. | VERIFIED | Plan 07-01 focused suite passes direct, package web, package API, mode, normalization, blank-input, deletion-recreation, and label-boundary cases. |
| 2 | Capture failure never rolls back campaign persistence and uses bounded caller-specific warnings. | VERIFIED | `a_web_capture_failure_commits_the_campaign_and_flashes_a_safe_warning` and `an_api_capture_failure_commits_the_campaign_and_adds_a_safe_warning_header` pass; the API body/status remain package-owned and the header is fixed. |
| 3 | Editing or deleting a sender leaves campaign and existing message From snapshots unchanged for draft, queued, sending, and sent records. | VERIFIED | `sender_lifecycle_changes_do_not_mutate_historical_snapshots` passes four explicit timestamp-state fixtures and confirms rows remain queryable after sender deletion. |
| 4 | Later message generation reads stored campaign From fields, while a direct campaign From edit controls only messages generated afterward. | VERIFIED | `later_message_generation_uses_the_current_campaign_snapshot` passes against the installed SendPortal Core `CreateMessages` pipeline. |
| 5 | Blank/null inputs, normalized duplicates, deleted-pair recreation, equal/adjacent independent rows, and the no-relation/vendor boundary remain explicit. | VERIFIED | Focused suite passes; schema and model assertions find no `sender_id` column or Campaign `sender` relation, and `git diff --name-only -- vendor/mettle/sendportal-core` is empty. |
| 6 | Concurrent supported-driver duplicate campaign creation converges to one sender with no warning. | VERIFIED | GitHub Actions run `35454371717` passed both the MySQL and PostgreSQL testsuite steps, including the supported-driver concurrency coverage. |

**Score:** 6/6 truths verified.

## Required Artifacts

| Artifact | Expected | Status | Details |
|---|---|---|---|
| `app/Observers/CampaignObserver.php` | Creation-only, after-commit, best-effort capture recovery | VERIFIED | Implements `ShouldHandleEventsAfterCommit`, handles `created` only, logs bounded context, flashes web sessions, and sets a fixed API request attribute. |
| `app/Http/Middleware/AttachSenderCaptureWarningHeader.php` | Host-only fixed API warning transport | VERIFIED | Runs after the workspace route boundary and sets only `X-SendPortal-Warning` with the fixed warning text when the observer attribute is true. |
| `routes/api.php` | API middleware registration | VERIFIED | Registers the warning middleware after `RequireWorkspace` around package API routes. |
| `tests/Feature/Workspaces/CampaignSenderAutoCaptureTest.php` | Capture, failure, edge-case, and race coverage | VERIFIED | Focused tests cover direct/web/API paths, failure recovery, modes, normalization, no-op fields, deletion recreation, UTF-8 label bounds, and supported-driver concurrency. |
| `tests/Feature/Workspaces/CampaignSenderIntegrityTest.php` | Historical snapshot coverage | VERIFIED | Covers all four message lifecycle states, sender CRUD isolation, later generation, direct campaign edits, independent rows, and schema/relation boundaries. |

## Key Link Verification

| From | To | Via | Status | Details |
|---|---|---|---|---|
| Campaign creation | `CaptureCampaignSender` | Host `CampaignObserver::created()` | WIRED | App provider registration is present and the observer is creation-only/after-commit. |
| Capture failure | Web warning | `Request::hasSession()` → session flash | WIRED | The web failure test proves the fixed session warning and committed campaign. |
| Capture failure | API warning | fixed request attribute → `AttachSenderCaptureWarningHeader` | WIRED | The API failure test proves the additive fixed header without changing JSON/status. |
| Sender CRUD | Historical data | Independent campaign/message From columns | WIRED | Integrity tests and no-relationship/schema assertions prove no live sender lookup or foreign key exists. |
| `CreateMessages` | Message snapshot | `campaign.from_name` / `campaign.from_email` | WIRED | The later-generation test proves original and post-edit message snapshots. |

## Requirements Coverage

| Requirement | Status | Evidence |
|---|---|---|
| SENDER-07 | SATISFIED, pending supported-driver race proof | Host observer/service, fixed failure recovery, normalization/deduplication/no-op/recreation tests, and CI-only race test. |
| SENDER-09 | SATISFIED | Four lifecycle-state snapshot tests, sender edit/delete, later generation, direct campaign edit, and readable history after deletion. |

## Automated Checks

| Check | Result | Status |
|---|---|---|
| `DB_CONNECTION=sqlite DB_DATABASE=':memory:' vendor/bin/phpunit tests/Feature/Workspaces/CampaignSenderAutoCaptureTest.php tests/Feature/Workspaces/CampaignSenderIntegrityTest.php --testdox` | 20 tests, 82 assertions, 1 intentional SQLite concurrency skip | PASS |
| `DB_CONNECTION=sqlite DB_DATABASE=':memory:' vendor/bin/phpunit` | 102 tests, 336 assertions, 1 intentional SQLite concurrency skip | PASS |
| `vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php --diff --dry-run --sequential` | Found 0 of 149 files that can be fixed | PASS |
| PHP lint on changed implementation/tests | No syntax errors | PASS |
| `git diff --check` | No whitespace errors | PASS |
| `git diff --name-only -- vendor/mettle/sendportal-core` | No output | PASS |
| Package campaign route smoke | Named web/API campaign routes are registered; API route is wrapped by host middleware in `routes/api.php` | PASS |

## Human Verification Required

[none]

## Gaps Summary

The implementation satisfies the phase goal. Local SQLite verification covers the full suite and intentionally skips only the SQLite-incompatible concurrency race; GitHub Actions run `35454371717` supplies the supported-driver proof with passing MySQL and PostgreSQL testsuite steps. The dependency and CI setup fixes are recorded in `.planning/debug/ci-dependency-audit.md`.

---

_Verified: 2026-09-19T16:19:16Z_

_Verifier: CI-backed phase verification after MySQL and PostgreSQL tests passed_
