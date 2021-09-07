#!/bin/bash

find . -not \( -path ./vendor -prune \) -type f -name '*.php' -print0 | xargs -0 -n1 -P4 -I {} php -l -n {} | (! grep -v "No syntax errors detected" )
