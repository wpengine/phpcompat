# PHP Compatibility Checker

An easy way to check your site for PHP Compatibility.

## Setup Development Environment

Before starting your workstation will need the following:

* [Docker](https://www.docker.com/)

1. Clone the repository

`git@github.com:wpengine/phpcompat.git`

2. Setup WP-Env

```bash
make setup && make start
```

When finished, a local WordPress will be configured at http://localhost:8888/wp-admin.

WordPress Credentials:

__URL:__ _http://localhost:8888/wp-admin_

__Admin User:__ _admin_

__Admin Password:__ _password_

If anything goes wrong and you suspect your local is frozen, you can always `make choose-violence` to rebuild the local environment and containers. Hopefully you never have to choose violence. :)

## Build and Testing

```bash
make build
```

Note, assets will also build during the install phase.

You can run all testing (all lints and unit tests) together with the following:

```bash
make test
```

The project uses the [Brain Monkey](https://brain-wp.github.io/BrainMonkey/) library for unit testing. Once setup run the following for unit tests:

```bash
make test-unit
```

The project uses the WordPress e2e tests. Run the following for e2e tests:

```bash
make test-e2e
```

We also use [PHP_CodeSniffer](https://github.com/squizlabs/PHP_CodeSniffer) with [WordPress Coding Standards](https://github.com/WordPress/WordPress-Coding-Standards) and [JSHint](http://jshint.com/) with [WordPress' JS Standards](https://make.wordpress.org/core/handbook/best-practices/coding-standards/javascript/#installing-and-running-jshint). Linting will automagically be setup for you if you use [Visual Studio Code](https://code.visualstudio.com/). If you want to run it manually use the following:

```bash
make test-lint
```

or, to run an individual lint (php or javascript), use one of the following:

```bash
make test-lint-php
```

```bash
make test-lint-javascript
```

Screw something up? You can reset your environment with the following. It will stop the environment and cleanup and the build files as well as anything downloaded.

```bash
make reset
```

## Preparing for release

To generate a .zip that can be uploaded through any normal WordPress plugin installation workflow, simply run the following:

```bash
make release
```
