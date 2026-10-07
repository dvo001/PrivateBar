#!/bin/sh
set -eu
# The local HTTPS virtual host must resolve directly to 127.0.0.1 (no reverse proxy).
# Wait for the PIN page before opening Chromium during desktop autostart.
attempt=0
while [ "$attempt" -lt 60 ]; do
    status=$(curl --silent --output /dev/null --write-out '%{http_code}' --max-time 2 https://privatebar.local/anmelden) || status=000
    case "$status" in
        200|302|303) break ;;
    esac
    attempt=$((attempt + 1))
    sleep 2
done
if [ "$attempt" -eq 60 ]; then
    printf '%s\n' 'PrivateBar-Anmeldung nicht erreichbar. HTTPS, Namensauflösung und Webserver prüfen.' >&2
    exit 1
fi
exec chromium --ozone-platform=wayland --enable-wayland-ime --wayland-text-input-version=3 --kiosk --no-first-run --disable-session-crashed-bubble --disable-translate --incognito https://privatebar.local
