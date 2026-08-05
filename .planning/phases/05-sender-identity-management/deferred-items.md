# Deferred Items

- The default focused PHPUnit command cannot connect to the configured MySQL test database (`laravel` access denied). The focused suite passes with the documented SQLite in-memory fallback: `DB_CONNECTION=sqlite DB_DATABASE=':memory:' vendor/bin/phpunit tests/Feature/Workspaces/SenderControllerTest.php`.
- The full SQLite suite has one unrelated pre-existing failure in `tests/Feature/Setup/SetupTest.php::the_setup_command_should_stop_on_the_admin_step_if_there_are_not_users` (expected 5, received 0); all sender tests pass.
- `vendor/bin/php-cs-fixer` is not installed in the existing Composer vendor tree, so the requested formatter dry run could not be executed. PHP lint and `git diff --check` passed.
