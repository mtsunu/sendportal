---
status: partial
phase: 07-sender-auto-capture-data-integrity
source: [07-01-SUMMARY.md, 07-02-SUMMARY.md, 07-VERIFICATION.md]
started: 2026-08-07T01:28:33Z
updated: 2026-09-19T15:32:50Z
---

## Current Test

number: 1
name: Supported-driver sender-capture concurrency
expected: |
  In both the CI MySQL and PostgreSQL jobs, two concurrent Campaign creates succeed,
  exactly one normalized sender row exists for the pair, and neither child logs
  campaign_sender_auto_capture_failed.
awaiting: CI prerequisite resolution

## Tests

### 1. Supported-driver sender-capture concurrency
expected: Run the focused sender-capture and integrity suites against the CI MySQL and PostgreSQL services; both drivers must pass the process-level duplicate-convergence assertion.
result: blocked
blocked_by: other
reason: "GitHub Actions run 35451862312 failed at Verify Composer policy routes before the MySQL and PostgreSQL test steps; both database test steps were skipped."

## Summary

total: 1
passed: 0
issues: 0
pending: 0
skipped: 0
blocked: 1

## Gaps
