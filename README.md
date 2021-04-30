# PHP Compatibility Checker

The WP Engine PHP Compatibility Checker can be used by any WordPress website on any web host to check PHP version compatibility.

## Setup Development Environment

Before starting your workstation will need the following:

* [Docker](https://www.docker.com/)
* [Lando](https://lando.dev/)

1. Clone the repository

`git@github.com:wpengine/wpe-php-compat.git`

2. Start Lando

```bash
cd wpe-php-compat
make start
```

When finished, Lando will give you the local URL of your site. You can finish the WordPress setup there. WooCommerce will be configured with enough sample data to get you started.

WordPress Credentials:

__URL:__ _https://wpe-php-compat.lndo.site/wp-admin_

__Admin User:__ _admin_

__Admin Password:__ _password_

## Using Xdebug

Xdebug 3 released a [number of changes](https://xdebug.org/docs/upgrade_guide) that affect the way Xdebug works. Namely, it no longer listens on every request and requires a "trigger" to enable the connection. Use one of the following plugins to enable the trigger on your machine:


* [Xdebug Helper for Firefox](https://addons.mozilla.org/en-GB/firefox/addon/xdebug-helper-for-firefox/) ([source](https://github.com/BrianGilbert/xdebug-helper-for-firefox)).
* [Xdebug Helper for Chrome](https://chrome.google.com/extensions/detail/eadndfjplgieldjbigjakmdgkmoaaaoc) ([source](https://github.com/mac-cain13/xdebug-helper-for-chrome)).
* [XDebugToggle for Safari](https://apps.apple.com/app/safari-xdebug-toggle/id1437227804?mt=12) ([source](https://github.com/kampfq/SafariXDebugToggle)).


## Build and Testing

The only current build asset is the .pot file for internationalization. Build it with the following:

```bash
make build
```

Note, assets will also build during the install phase.

The project uses the [Brain Monkey](https://brain-wp.github.io/BrainMonkey/) library for unit testing. Once setup run the following for unit tests:

```bash
make test-unit
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

You can run all testing (all lints and unit tests) together with the following:

```bash
make test
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
