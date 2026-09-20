#!/usr/bin/env bash

# Installiert ein geprüftes PrivateBar-Pi-Tarball als neuen Release und schaltet
# den bestehenden /srv/privatebar/current-Symlink erst nach dem Healthcheck um.
set -Eeuo pipefail
IFS=$'\n\t'

ROOT=${PRIVATEBAR_ROOT:-/srv/privatebar}
RELEASES="$ROOT/releases"
SHARED="$ROOT/shared"
CURRENT="$ROOT/current"
SERVICE_USER=${PRIVATEBAR_USER:-privatebar}
PHP_BIN=${PRIVATEBAR_PHP_BIN:-/usr/bin/php8.3}
TIMER_UNIT=${PRIVATEBAR_TIMER_UNIT:-privatebar-tick.timer}
SERVICE_UNIT=${PRIVATEBAR_SERVICE_UNIT:-privatebar-tick.service}

ARCHIVE=''
VERSION=''
TMP_DIR=''
TIMER_WAS_ACTIVE=0
TIMER_RESTARTED=0
LOG_FILE=''

usage() {
    cat <<'EOF'
PrivateBar Pi Releasewechsel

Aufruf:
  sudo bash install-release.sh /pfad/privatebar-VERSION-pi-installation.tar.gz

Das Skript erwartet eine bestehende Installation unter /srv/privatebar. Die
gemeinsame .env und storage werden nie aus dem Archiv ersetzt. Vor dem Wechsel
werden artisan optimize und privatebar:health ausgeführt.

Optionale Umgebungsvariablen:
  PRIVATEBAR_ROOT       Installationswurzel (Standard: /srv/privatebar)
  PRIVATEBAR_USER       Anwendungskonto (Standard: privatebar)
  PRIVATEBAR_PHP_BIN    PHP-Pfad (Standard: /usr/bin/php8.3)
EOF
}

fail() {
    printf 'Fehler: %s\n' "$*" >&2
    return 1
}

run_as_service_user() {
    if command -v runuser >/dev/null 2>&1; then
        runuser -u "$SERVICE_USER" -- "$@"
    elif command -v sudo >/dev/null 2>&1; then
        sudo -u "$SERVICE_USER" -- "$@"
    else
        fail 'Weder runuser noch sudo ist verfügbar.'
    fi
}

cleanup_on_exit() {
    local status=$?

    if [[ -n "$TMP_DIR" && -d "$TMP_DIR" ]]; then
        rm -rf -- "$TMP_DIR"
    fi

    if (( status != 0 && TIMER_WAS_ACTIVE == 1 && TIMER_RESTARTED == 0 )); then
        printf 'Der vorher aktive Timer wird nach dem Fehler wieder gestartet.\n' >&2
        systemctl start "$TIMER_UNIT" || printf 'Warnung: Timer konnte nicht wieder gestartet werden.\n' >&2
    fi

    exit "$status"
}
trap cleanup_on_exit EXIT

if (( $# != 1 )); then
    usage >&2
    exit 64
fi
if [[ "$1" == '-h' || "$1" == '--help' ]]; then
    usage
    exit 0
fi

[[ $EUID -eq 0 ]] || { fail 'Das Skript muss mit sudo oder als root ausgeführt werden.'; exit 1; }
command -v tar >/dev/null 2>&1 || { fail 'tar ist nicht installiert.'; exit 1; }
command -v systemctl >/dev/null 2>&1 || { fail 'systemctl ist nicht verfügbar.'; exit 1; }
command -v sha256sum >/dev/null 2>&1 || { fail 'sha256sum ist nicht installiert.'; exit 1; }
[[ -x "$PHP_BIN" ]] || { fail "PHP wurde unter $PHP_BIN nicht gefunden."; exit 1; }
id "$SERVICE_USER" >/dev/null 2>&1 || { fail "Anwendungskonto $SERVICE_USER wurde nicht gefunden."; exit 1; }

ARCHIVE=$1
[[ -f "$ARCHIVE" ]] || { fail "Archiv nicht gefunden: $ARCHIVE"; exit 1; }
ARCHIVE=$(readlink -f -- "$ARCHIVE")

ARCHIVE_NAME=$(basename -- "$ARCHIVE")
if [[ "$ARCHIVE_NAME" =~ ^privatebar-([0-9]+\.[0-9]+\.[0-9]+)-pi-installation\.tar\.gz$ ]]; then
    VERSION=${BASH_REMATCH[1]}
else
    fail 'Archivname muss privatebar-VERSION-pi-installation.tar.gz entsprechen.'
    exit 1
fi

[[ -d "$ROOT" ]] || { fail "Installationswurzel fehlt: $ROOT"; exit 1; }
[[ -d "$RELEASES" ]] || { fail "Releaseverzeichnis fehlt: $RELEASES"; exit 1; }
[[ -f "$SHARED/.env" ]] || { fail "Gemeinsame Konfiguration fehlt: $SHARED/.env"; exit 1; }
[[ -d "$SHARED/storage" ]] || { fail "Gemeinsamer Speicher fehlt: $SHARED/storage"; exit 1; }
[[ -L "$CURRENT" ]] || { fail "$CURRENT ist kein Symlink; der Wechsel wird abgebrochen."; exit 1; }
systemctl cat "$TIMER_UNIT" >/dev/null 2>&1 || { fail "Systemd-Timer fehlt: $TIMER_UNIT"; exit 1; }

TARGET="$RELEASES/$VERSION"
if [[ -e "$TARGET" || -L "$TARGET" ]]; then
    fail "Der Releasepfad existiert bereits: $TARGET"
    exit 1
fi

# Vor dem Entpacken werden absolute Pfade und Parent-Traversal im Tarball
# abgewiesen. Die Anwendung selbst bleibt danach auf die erwarteten Dateien
# beschränkt.
while IFS= read -r entry; do
    entry=${entry#./}
    [[ -z "$entry" ]] && continue
    case "$entry" in
        /*|..|../*|*/../*|*/..)
            fail "Unsicherer Pfad im Archiv: $entry"
            exit 1
            ;;
    esac
done < <(tar -tzf "$ARCHIVE")

if [[ -f "$ARCHIVE.sha256" ]]; then
    expected=$(awk 'NF { print $1; exit }' "$ARCHIVE.sha256")
    actual=$(sha256sum "$ARCHIVE" | awk '{print $1}')
    [[ "$expected" =~ ^[[:xdigit:]]{64}$ && "$expected" == "$actual" ]] || {
        fail "SHA-256-Prüfung fehlgeschlagen: $ARCHIVE"
        exit 1
    }
fi

timestamp=$(date '+%Y%m%d-%H%M%S')
LOG_FILE="$SHARED/storage/logs/pi-release-$VERSION-$timestamp.log"
install -d -m 0750 "$SHARED/storage/logs"
chown "$SERVICE_USER:$SERVICE_USER" "$SHARED/storage/logs"
touch "$LOG_FILE"
chown "$SERVICE_USER:$SERVICE_USER" "$LOG_FILE"
chmod 0640 "$LOG_FILE"
exec > >(tee -a "$LOG_FILE") 2>&1

printf 'PrivateBar Pi Releasewechsel %s\n' "$VERSION"
printf 'Archiv: %s\n' "$ARCHIVE"
printf 'Protokoll: %s\n' "$LOG_FILE"

TMP_DIR=$(mktemp -d "$RELEASES/.${VERSION}.install.XXXXXX")
printf 'Entpacke nach %s ...\n' "$TMP_DIR"
tar --extract --gzip --file "$ARCHIVE" --directory "$TMP_DIR" \
    --no-same-owner --no-same-permissions

[[ -f "$TMP_DIR/artisan" ]] || { fail 'artisan fehlt im Archiv.'; exit 1; }
[[ -f "$TMP_DIR/vendor/autoload.php" ]] || { fail 'vendor/autoload.php fehlt im Archiv.'; exit 1; }
[[ -f "$TMP_DIR/public/index.php" ]] || { fail 'public/index.php fehlt im Archiv.'; exit 1; }

# Runtime-Daten und Geheimnisse kommen ausschliesslich aus shared. Die beiden
# Links werden im temporären Release vor dem Healthcheck neu angelegt.
rm -rf -- "$TMP_DIR/.env" "$TMP_DIR/storage"
ln -s "$SHARED/.env" "$TMP_DIR/.env"
ln -s "$SHARED/storage" "$TMP_DIR/storage"
# Die Cache-Dateien können aus einer Entwicklungsinstallation stammen und auf
# Provider zeigen, die im Produktions-Vendor absichtlich nicht enthalten sind.
# Laravel erzeugt sie mit optimize für genau diesen Release neu.
rm -f -- "$TMP_DIR/bootstrap/cache"/*.php
install -d -o "$SERVICE_USER" -g "$SERVICE_USER" -m 0750 "$TMP_DIR/bootstrap/cache"
chown -R "$SERVICE_USER:$SERVICE_USER" "$TMP_DIR"

if systemctl is-active --quiet "$TIMER_UNIT"; then
    TIMER_WAS_ACTIVE=1
    printf 'Stoppe %s während des Releasewechsels.\n' "$TIMER_UNIT"
    systemctl stop "$TIMER_UNIT"
else
    printf '%s war nicht aktiv; dieser Zustand bleibt erhalten.\n' "$TIMER_UNIT"
fi
systemctl stop "$SERVICE_UNIT" >/dev/null 2>&1 || true

printf 'Baue Laravel-Cache ...\n'
run_as_service_user "$PHP_BIN" "$TMP_DIR/artisan" optimize
printf 'Führe Gesundheitsprüfung aus ...\n'
run_as_service_user "$PHP_BIN" "$TMP_DIR/artisan" privatebar:health

OLD_TARGET=$(readlink -- "$CURRENT")
mv -- "$TMP_DIR" "$TARGET"
TMP_DIR=''
NEXT_LINK="$ROOT/.current.$$.next"
rm -f -- "$NEXT_LINK"
ln -s "releases/$VERSION" "$NEXT_LINK"
mv -Tf -- "$NEXT_LINK" "$CURRENT"
printf 'current wurde atomar von %s auf releases/%s umgeschaltet.\n' "$OLD_TARGET" "$VERSION"

if (( TIMER_WAS_ACTIVE == 1 )); then
    systemctl start "$TIMER_UNIT"
    systemctl is-active --quiet "$TIMER_UNIT" || { fail "$TIMER_UNIT wurde nicht aktiv."; exit 1; }
    TIMER_RESTARTED=1
    printf '%s ist wieder aktiv.\n' "$TIMER_UNIT"
fi

printf 'Release %s erfolgreich installiert.\n' "$VERSION"
