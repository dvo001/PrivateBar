#!/bin/bash
# Grundkomponenten für PrivateBar; ausschliesslich auf dem Ziel-Pi ausführen.
set -euo pipefail

fail() { printf 'Fehler: %s\n' "$*" >&2; return 1; }

validate_platform() {
    local os_id=$1 codename=$2 architecture=$3 model=$4
    case "$os_id" in debian|raspbian) ;; *) fail 'Raspberry Pi OS auf Debian-Basis erforderlich.'; return 1 ;; esac
    case "$codename" in bookworm|trixie) ;; *) fail 'Nur Bookworm und Trixie werden unterstützt.'; return 1 ;; esac
    [[ "$architecture" == arm64 ]] || { fail 'Raspberry Pi OS 64-Bit (arm64) erforderlich.'; return 1; }
    [[ "$model" == *'Raspberry Pi'* ]] || { fail 'Dieses Skript ist ausschliesslich für einen Raspberry Pi bestimmt.'; return 1; }
}

packages() {
    printf '%s\n' nginx mariadb-server mariadb-client php8.3-cli php8.3-fpm \
        php8.3-common php8.3-curl php8.3-gd php8.3-mbstring php8.3-mysql \
        php8.3-xml php8.3-zip php8.3-intl php8.3-opcache \
        python3 cifs-utils chromium wlr-randr openssl ca-certificates curl \
        unzip tar nano
}

has_php_candidate() {
    local candidate
    candidate=$(apt-cache policy "$1" | awk '/Candidate:/ {print $2; exit}')
    [[ -n "$candidate" && "$candidate" != '(none)' ]]
}

install_php_repository() {
    local codename=$1 temporary source_file=/etc/apt/sources.list.d/privatebar-php.list
    local entry="deb [signed-by=/usr/share/keyrings/debsuryorg-archive-keyring.gpg] https://packages.sury.org/php/ $codename main"
    if [[ -e "$source_file" || -L "$source_file" ]]; then
        fail "PHP 8.3 fehlt trotz bestehender $source_file. Paketquelle manuell prüfen."
        return 1
    fi
    printf '%s\n' 'PHP 8.3 fehlt in den vorhandenen Quellen. Ergänze die signierte Sury-PHP-Paketquelle.'
    apt-get install -y --no-remove ca-certificates curl
    temporary=$(mktemp -d /tmp/privatebar-php-keyring.XXXXXXXX)
    # Kein fremdes Shell-Skript ausführen; nur das offizielle Keyring-Paket laden.
    if ! curl --fail --show-error --silent --location --proto '=https' --proto-redir '=https' \
        https://packages.sury.org/debsuryorg-archive-keyring.deb -o "$temporary/keyring.deb"; then
        rm -rf -- "$temporary"
        fail 'Keyring-Download fehlgeschlagen.'
        return 1
    fi
    if ! dpkg -i "$temporary/keyring.deb"; then
        rm -rf -- "$temporary"
        fail 'Keyring konnte nicht installiert werden.'
        return 1
    fi
    rm -rf -- "$temporary"
    [[ -r /usr/share/keyrings/debsuryorg-archive-keyring.gpg ]] || { fail 'Signaturschlüssel fehlt.'; return 1; }
    # noclobber schützt auch bei einer zwischenzeitlich angelegten Datei.
    (set -o noclobber; printf '%s\n' "$entry" > "$source_file")
    chmod 644 "$source_file"
    apt-get update
}

main() {
    local action=${1:---check}
    if [[ $# -gt 1 ]]; then fail 'Nur --check, --install oder --help angeben.'; return 1; fi
    case "$action" in
        --help|-h)
            printf '%s\n' 'PrivateBar: Grundkomponenten für Raspberry Pi OS 64-Bit (Bookworm/Trixie).' \
                'bash install-prerequisites.sh --check    Voraussetzungen und Paketplan anzeigen (Standard).' \
                'sudo bash install-prerequisites.sh --install    Pakete installieren und Dienste starten.' \
                'Bei Bedarf wird die signierte PHP-Paketquelle packages.sury.org ergänzt.' \
                'Keine Datenbankkonten, Zertifikate, Desktop-Anmeldung oder Anwendungskonfiguration werden angelegt.'
            return 0 ;;
        --check|--install) ;;
        *) fail 'Unbekannte Option. Hilfe: --help'; return 1 ;;
    esac
    local ID='' VERSION_CODENAME='' architecture model
    # Vertrauenswürdige, vom Betriebssystem verwaltete Datei.
    source /etc/os-release
    architecture=$(dpkg --print-architecture)
    model=''
    if [[ -r /proc/device-tree/model ]]; then model=$(tr -d '\0' < /proc/device-tree/model); fi
    validate_platform "$ID" "$VERSION_CODENAME" "$architecture" "$model" || return 1
    printf 'System: %s, %s, %s\n' "$model" "$VERSION_CODENAME" "$architecture"
    printf '%s\n' 'Vorgesehene Pakete:'
    packages
    if [[ "$action" == --check ]]; then
        printf '%s\n' 'Prüfung abgeschlossen. Es wurde nichts verändert. Installation: sudo bash install-prerequisites.sh --install'
        return 0
    fi
    [[ $EUID -eq 0 ]] || { fail 'Installation mit sudo starten.'; return 1; }
    [[ -d /run/systemd/system ]] || { fail 'Ein gestartetes System mit systemd ist erforderlich.'; return 1; }
    export LC_ALL=C
    export DEBIAN_FRONTEND=noninteractive
    # Keine vorhandenen Pakete entfernen und keine Konfigurationsdateien ersetzen.
    apt-get update
    if ! has_php_candidate php8.3-cli || ! has_php_candidate php8.3-fpm; then
        install_php_repository "$VERSION_CODENAME"
    fi
    has_php_candidate php8.3-cli && has_php_candidate php8.3-fpm || { fail 'Keine PHP-8.3-Pakete verfügbar.'; return 1; }
    local -a selected
    mapfile -t selected < <(packages)
    apt-get install -y --no-remove -o Dpkg::Options::=--force-confdef -o Dpkg::Options::=--force-confold "${selected[@]}"
    /usr/bin/php8.3 -r '
        if (PHP_MAJOR_VERSION !== 8 || PHP_MINOR_VERSION !== 3) { exit(1); }
        foreach (["ctype", "curl", "dom", "fileinfo", "filter", "gd", "iconv", "mbstring", "openssl", "pdo_mysql", "session", "tokenizer", "xml", "zip"] as $extension) {
            if (!extension_loaded($extension)) { fwrite(STDERR, "PHP-Erweiterung fehlt: ".$extension."\n"); exit(1); }
        }
        echo "PHP 8.3 und Erweiterungen bereit.\n";
    '
    /usr/sbin/php-fpm8.3 --test
    nginx -t
    systemctl enable --now mariadb php8.3-fpm nginx
    local service
    for service in mariadb php8.3-fpm nginx; do systemctl is-active --quiet "$service"; done
    printf '%s\n' 'Grundkomponenten installiert und Dienste aktiv.' \
        'Weiter mit INSTALLATION-PI.md: Anwendung entpacken, Datenbank, PHP-FPM-Pool und HTTPS einrichten.' \
        'Die Pi-Dienste verwenden /usr/bin/php8.3; eine globale Umschaltung ist für PrivateBar nicht erforderlich.' \
        'Chromium benötigt eine eingerichtete grafische Desktop-Sitzung; diese wird hier nicht eingerichtet.'
}

if [[ "${BASH_SOURCE[0]}" == "$0" ]]; then
    main "$@"
fi
