#!/bin/sh
set -eu
# The local HTTPS virtual host must resolve directly to 127.0.0.1 (no reverse proxy).
exec chromium --kiosk --no-first-run --disable-session-crashed-bubble --disable-translate --incognito https://privatebar.local
