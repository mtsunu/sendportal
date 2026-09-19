---
status: testing
phase: 07-sender-auto-capture-data-integrity
source: [07-01-SUMMARY.md, 07-02-SUMMARY.md, 07-VERIFICATION.md]
started: 2026-08-07T01:28:33Z
updated: 2026-09-19T15:16:37Z
---

## Current Test

number: 1
name: Supported-driver sender-capture concurrency
expected: |
  In both the CI MySQL and PostgreSQL jobs, two concurrent Campaign creates succeed,
  exactly one normalized sender row exists for the pair, and neither child logs
  campaign_sender_auto_capture_failed.
awaiting: user response

## Tests

### 1. Supported-driver sender-capture concurrency
expected: Run the focused sender-capture and integrity suites against the CI MySQL and PostgreSQL services; both drivers must pass the process-level duplicate-convergence assertion.
result: [pending]

## Summary

total: 1
passed: 0
issues: 0
pending: 1
skipped: 0
blocked: 0

## Gaps
