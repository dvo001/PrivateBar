#!/bin/sh
set -eu
php tools/build.php
php vendor/bin/pint --test
php vendor/bin/phpstan analyse --memory-limit=512M
php vendor/bin/phpunit --fail-on-risky --fail-on-warning
