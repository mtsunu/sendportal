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

The guarded commands use this checkout's tracked Composer 2.10.2 distribution, so they do not download Composer on each invocation. They fail closed if that distribution or its checked-in integrity record is missing or invalid. From this checkout, use the guarded command for both an operator install and intentional dependency-update work:

```sh
php bin/composer-policy install --prefer-dist --no-interaction
php bin/composer-policy update --prefer-dist --no-interaction
```

`COMPOSER_BIN` is intentionally unsupported: the guard must not be redirected to an arbitrary PHP program. A repository-owned deployment install script was not found during the route audit, so this project does not invent deployment automation; operators following the external installation guide must use the guarded command after entering this checkout. The native policy remains blocking, and this guidance preserves the existing Laravel 11 and SendPortal Core integration.

If you are on an earlier version of PHP (7.3+) or Laravel (8+), please use [SendPortal V2](https://github.com/mettle/sendportal/releases/tag/v2.0.4)
