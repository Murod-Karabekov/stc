#!/bin/sh
set -e

# First arg is `-f` or `--some-option`
if [ "${1#-}" != "$1" ]; then
    set -- php-fpm "$@"
fi

if [ "$1" = 'php-fpm' ] || [ "$1" = 'bin/console' ]; then
    mkdir -p var/cache var/log public/uploads
    chmod -R 777 var public/uploads 2>/dev/null || true
fi

exec "$@"
