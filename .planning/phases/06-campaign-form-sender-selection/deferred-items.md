# Deferred Items

## Full-suite Composer Manifest Guard

- **Status:** Open / pre-existing
- **Found during:** 06-01 plan-level verification
- **Issue:** `vendor/bin/phpunit` has one existing failure in `Tests\\Feature\\Ses\\SesDoubleSendTest::no_vendor_core_or_composer_manifest_was_changed` because the working tree already contains unrelated `composer.json` and `composer.lock` modifications.
- **Scope:** Unrelated to the campaign sender-selection changes; no production fix was made.
- **Next action:** Re-run the full suite from a clean baseline or after the pre-existing Composer changes are reconciled.
