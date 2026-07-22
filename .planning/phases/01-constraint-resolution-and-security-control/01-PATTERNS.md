# Phase 01: Constraint Resolution and Security Control - Pattern Map

**Mapped:** 2026-07-22  
**Files analyzed:** 1 Phase 1 change target  
**Analogs found:** 1 / 1

## Scope Boundary

`01-CONTEXT.md` and `01-RESEARCH.md` identify `composer.json` as the only repository file Phase 1 may change. It is the dependency-policy boundary. The existing CI workflow, PHPUnit configuration, and README are deliberately read as downstream patterns only:

- `.github/workflows/ci.yml` changes (PHP 8.4 matrix, Composer floor/policy assertions, and locked audit/platform checks) belong to Phase 3.
- `composer.lock` creation, `composer audit --locked`, and `composer check-platform-reqs --lock` belong to Phase 2 or later, after a human decision produces a stable compliant graph.
- `phpunit.xml.dist`, application PHP, and SendPortal Core integration files are not Phase 1 targets.

The planner must insert a `checkpoint:human-verify` before accepting any manifest candidate: current solver evidence says Composer-native blocking rejects every stable Laravel 11 release. Do not substitute `11.x-dev`, a policy/platform bypass, or broad advisory ignores.

## File Classification

| New/Modified File | Role | Data Flow | Closest Analog | Match Quality |
|---|---|---|---|---|
| `composer.json` | configuration / dependency manifest | dependency-resolution | `composer.json` | exact (in-place manifest policy/constraint update) |

## Pattern Assignments

### `composer.json` (configuration, dependency-resolution)

**Analog:** `composer.json` (the existing root manifest is the sole local analog and must be modified in place).

**Root dependency-constraint pattern** (lines 6-15):

```json
"require": {
    "php": "^8.2|^8.3",
    "guzzlehttp/guzzle": "^7.8.1",
    "laravel/framework": "^11.0",
    "laravel/horizon": "^5.24",
    "laravel/tinker": "^2.9",
    "livewire/livewire": "^3.4",
    "laravel/ui": "^4.5",
    "mettle/sendportal-core": "^3.0"
},
```

Keep the Laravel 11 and SendPortal Core entries intact unless a real isolated PHP 8.4 solver output directly justifies a minimal compatible change. Replace the redundant PHP expression with the accurate `^8.2` contract only; it covers PHP 8.2 through pre-9.0, including 8.4. Do not add `config.platform` as runtime-support evidence.

**Development-security dependency pattern** (lines 16-22):

```json
"require-dev": {
    "fakerphp/faker": "^1.23",
    "mockery/mockery": "^1.6",
    "nunomaduro/collision": "^8.1",
    "phpunit/phpunit": "^10.5",
    "roave/security-advisories": "dev-master",
    "spatie/laravel-ignition": "^2.5.1"
},
```

The Roave entry is the one removal authorized by D-02, and only after its replacement is an explicit Composer-native advisory policy under the existing `config` object. Preserve all unrelated development tools and their bounds.

**Composer configuration pattern** (lines 24-31):

```json
"config": {
    "optimize-autoloader": true,
    "preferred-install": "dist",
    "sort-packages": true,
    "allow-plugins": {
        "php-http/discovery": true
    }
},
```

Nest the reviewed Composer 2.10+ `policy.advisories` settings in this object; retain the existing optimization, preferred-install, package sorting, and plugin allow-list settings. The research-prescribed policy shape is:

```json
"policy": {
    "advisories": {
        "block": true,
        "audit": "fail"
    }
}
```

Do not add legacy `config.audit` advisory keys, `ignore-id`, `ignore`, `ignore-severity`, or policy-disabling settings. Do not use `--no-blocking`, `--no-security-blocking`, `COMPOSER_POLICY=0`, or `--ignore-platform-req*` in evidence commands or CI.

**Stability and install-hook pattern** (lines 49-61):

```json
"minimum-stability": "dev",
"prefer-stable": true,
"scripts": {
    "post-autoload-dump": [
        "Illuminate\\Foundation\\ComposerScripts::postAutoloadDump",
        "@php artisan package:discover --ansi"
    ],
    "post-root-package-install": [
        "@php -r \"file_exists('.env') || copy('.env.example', '.env');\""
    ],
    "post-create-project-cmd": [
        "@php artisan key:generate --ansi"
    ]
}
```

Treat `minimum-stability: dev` as a separately justified setting. It currently admits the forbidden `laravel/framework 11.x-dev` escape route, so do not accept a candidate lock or widen package bounds merely because that branch solves. Preserve script hooks; Phase 1 solver investigations must run in a temporary copy with `--no-scripts` and must not write `composer.lock`.

**Validation/error pattern:** manifest validation is the first failure boundary; run this after any candidate manifest edit:

```bash
composer validate --strict --no-check-publish
composer update --dry-run --prefer-dist --no-interaction --no-scripts --no-progress
```

Run the dry solver only in a clean temporary copy. A solver failure is decision evidence, not a reason to weaken the dependency-security or platform control.

## Shared Patterns

### CI installation and database-matrix preservation (downstream Phase 3)

**Source:** `.github/workflows/ci.yml`  
**Apply to:** Phase 3 CI changes; do not edit in Phase 1.

**Matrix/container pattern** (lines 4-16):

```yaml
phpunit:
  runs-on: ubuntu-latest
  strategy:
    matrix:
      container: [
        "kirschbaumdevelopment/laravel-test-runner:8.2",
        "kirschbaumdevelopment/laravel-test-runner:8.3"
      ]

  container:
    image: ${{ matrix.container }}

  name: ${{ matrix.container }}
```

Extend this matrix with PHP 8.4 rather than replacing the 8.2/8.3 coverage. In the same container-based job, assert Composer is 2.10+ and that policy-disabling environment variables are unset/false before the install command.

**Install and database test pattern** (lines 40-53):

```yaml
steps:
  - uses: actions/checkout@v3
  - name: Install composer dependencies
    run: composer install -q --no-ansi --no-interaction --no-scripts --no-progress --prefer-dist
  - name: Run Testsuite against MySQL
    run: vendor/bin/phpunit
    env:
      DB_CONNECTION: mysql
      DB_HOST: mysql
  - name: Run Testsuite against Postgres
    run: vendor/bin/phpunit
    env:
      DB_CONNECTION: pgsql
      DB_HOST: postgres
```

Keep `--no-scripts` only for the CI test install convention; it is not a platform/security bypass. After Phase 2 supplies the reviewed lockfile, add blocking `composer audit --locked` and `composer check-platform-reqs --lock` before both database suite legs. Do not use a no-blocking or ignore-platform option.

### PHPUnit environment contract (no Phase 1 edit)

**Source:** `phpunit.xml.dist`  
**Apply to:** Phase 3 test verification; the manifest policy change does not require a new PHPUnit test file.

**Bootstrap and suite pattern** (lines 1-7):

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" bootstrap="vendor/autoload.php" backupGlobals="false" colors="true" processIsolation="false" stopOnFailure="false" xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/10.5/phpunit.xsd" cacheDirectory=".phpunit.cache" backupStaticProperties="false">
  <testsuites>
    <testsuite name="Test Suite">
      <directory>tests</directory>
    </testsuite>
  </testsuites>
```

**Database environment pattern** (lines 8-18):

```xml
<php>
  <env name="APP_ENV" value="testing"/>
  <env name="CACHE_DRIVER" value="array"/>
  <env name="SESSION_DRIVER" value="array"/>
  <env name="QUEUE_DRIVER" value="sync"/>
  <env name="DB_DATABASE" value="sendportal_testing"/>
  <env name="DB_USERNAME" value="laravel"/>
  <env name="DB_PASSWORD" value="secret"/>
</php>
```

CI supplies only connection/host overrides while PHPUnit owns the baseline test environment. Preserve this division when adding PHP 8.4 verification.

### Installation documentation baseline (no Phase 1 edit)

**Source:** `README.md`  
**Apply to:** Phase 3 or release documentation only if runtime evidence changes published requirements.

**Requirements/documentation pattern** (lines 20-33):

```markdown
## Installation

If you would like to install SendPortal as a stand-alone application, please follow the [installation guide](https://sendportal.io/docs/v2/getting-started/installation).

## Requirements
SendPortal V3 requires:

- PHP 8.2+
- Laravel 10+
- MySQL (≥ 5.7) or PostgreSQL (≥ 9.4)
```

The published `PHP 8.2+` statement already encompasses 8.4. Do not change docs merely to claim compatibility; revise only after a real locked install and runtime checks establish the support contract.

### Formatting

**Source:** `.php-cs-fixer.dist.php` lines 19-55 and `.github/workflows/format.yml` lines 1-29  
**Apply to:** only any future PHP source files. This Phase 1 target is JSON and does not require a PHP-CS-Fixer workflow change.

## No Analog Found

None. The only Phase 1 change target is an in-place root-manifest update, so the existing `composer.json` is an exact structural analog. Composer 2.10 native policy syntax is intentionally sourced from the phase research/official Composer documentation because no local policy block exists yet.

## Metadata

**Analog search scope:** `composer.json`, `.github/workflows/`, `phpunit.xml.dist`, `README.md`, `.php-cs-fixer.dist.php`, and repository-wide Composer/policy references  
**Files scanned:** 6 primary analog/configuration files; 4 relevant Composer/policy references outside planning artifacts  
**Pattern extraction date:** 2026-07-22
