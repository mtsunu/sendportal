<p align="center"><img src="https://sendportal.io/img/sendportal.png" width="250"></p>


Modern open-source self-hosted email marketing.

- [Website](https://sendportal.io)
- [Documentation](https://sendportal.io/docs)

## Introduction

The core functionality of SendPortal is contained within the [SendPortal Core](https://github.com/mettle/sendportal-core) package. If you would like to add SendPortal to an existing application that already handles user authentication, you only require [SendPortal Core](https://github.com/mettle/sendportal-core).

## Features
SendPortal includes subscriber and list management, email campaigns, message tracking, reports and multiple workspaces/domains in a modern, flexible and scalable application.

SendPortal integrates with [Amazon SES](https://aws.amazon.com/ses), [Postmark](https://postmarkapp.com), [Sendgrid](https://sendgrid.com), [Mailgun](https://www.mailgun.com/) and [Mailjet](https://www.mailjet.com).

The [SendPortal](https://github.com/mettle/sendportal) application acts as a wrapper around SendPortal Core. This will allow you to run your own copy of SendPortal as a stand-alone application, including user authentication and multiple workspaces.

## Installation

If you would like to install SendPortal as a stand-alone application, please follow the [installation guide](https://sendportal.io/docs/v2/getting-started/installation).

If you would like to add SendPortal to an existing application, please follow the [package installation guide](https://sendportal.io/docs/v2/getting-started/package-installation).

## Requirements
SendPortal V3 requires:

- PHP 8.2+
- Laravel 10+
- MySQL (≥ 5.7) or PostgreSQL (≥ 9.4)

### Dependency management

The guarded commands use this checkout's tracked Composer 2.10.2 distribution, so they do not download Composer on each invocation. They fail closed if that distribution or its checked-in integrity record is missing or invalid.

The committed `composer.lock` is the install contract for every path. An `install` consumes that lock as-is and does not re-resolve versions, so local, CI, and deployment machines all get the exact reviewed dependency graph. `update` re-resolves and may change versions — reserve it for intentional dependency-upgrade work, and re-review the lockfile (see "Lockfile review" below) before committing the result.

Ordinary operator install (installs the committed lock):

```sh
php bin/composer-policy install --prefer-dist --no-interaction
```

Production deployment install (committed lock, no dev dependencies, optimized autoloader):

```sh
php bin/composer-policy install --no-dev --optimize-autoloader --no-interaction
```

Intentional dependency-upgrade work only (re-resolves; re-review the lockfile afterwards):

```sh
php bin/composer-policy update --prefer-dist --no-interaction
```

`COMPOSER_BIN` is intentionally unsupported: the guard must not be redirected to an arbitrary PHP program. A repository-owned deployment install script was not found during the route audit, so this project does not invent deployment automation; operators following the external installation guide must use the guarded command after entering this checkout. The native policy remains blocking, and this guidance preserves the existing Laravel 11 and SendPortal Core integration.

#### Lockfile review

Before committing a regenerated `composer.lock` (or when auditing the frozen graph), run these three read-only checks on PHP 8.4. All exit 0 on a healthy lockfile:

```sh
php bin/composer-policy validate --strict --no-interaction
php tools/composer/composer-2.10.2.phar check-platform-reqs --lock --no-interaction
php bin/composer-policy audit --locked --no-interaction
```

`validate --strict` proves `composer.json` and `composer.lock` are synchronized; `check-platform-reqs --lock` proves the locked graph is satisfiable on the running PHP (this read-only check has no guarded route, so it runs through the tracked, integrity-verified Composer distribution directly); `audit --locked` proves the locked graph carries no non-ignored security advisory (the three owner-accepted `laravel/framework` exceptions are documented in `composer.json` `config.policy.advisories.ignore-id`).

If you are on an earlier version of PHP (7.3+) or Laravel (8+), please use [SendPortal V2](https://github.com/mettle/sendportal/releases/tag/v2.0.4)
