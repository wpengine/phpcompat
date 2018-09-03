#!/usr/bin/env bash

set -euo pipefail

PLUGIN_ROOT="/var/www/html/wp-content/plugins/phpcompat/"

main()
{
    cd "$PLUGIN_ROOT"

    composer update

    phpunit
}

main "$@"