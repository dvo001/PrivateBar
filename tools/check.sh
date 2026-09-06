#!/bin/sh
set -eu
php tools/build.php
php vendor/bin/pint --test
php vendor/bin/phpstan analyse --memory-limit=512M
php vendor/bin/phpunit --fail-on-risky --fail-on-warning
bash -n deploy/pi/install-prerequisites.sh
sh -n deploy/pi/kiosk.sh
python3 tests/Unit/test_pi_prerequisites.py
python3 tests/Unit/test_monitor.py
