# PrivateBar 1.0.0 – Raspberry-Pi-Erstinstallation

Das Paket `privatebar-1.0.0-pi-installation.tar.gz` enthält die Anwendung,
Produktionsbibliotheken, gebaute Frontend-Dateien und die Vorlagen für Pi-Dienste.
Es ist ein Anwendungspaket für die Erstinstallation, kein SD-Karten-Abbild und
kein signiertes Update für „Freigegebene Version installieren“.
Die Abnahme auf dem echten Raspberry Pi steht noch aus.

## Voraussetzungen

Raspberry Pi 4 mit Raspberry Pi OS 64-Bit, mindestens 2 GB RAM und 32 GB Speicher.
Auf dem Pi müssen MariaDB, PHP 8.3 mit CLI/FPM und den Laravel-Erweiterungen
(insbesondere GD und PDO MySQL), Nginx, Python 3, Chromium, cifs-utils und für
Wayland wlr-randr eingerichtet sein. PHP und Erweiterungen müssen aus einer
zum installierten Betriebssystem passenden, gepflegten Paketquelle stammen.
Die Auswahl ist in `DEPLOYMENT.md` beschrieben; das Archiv installiert keine
Betriebssystempakete. Composer und ein Frontend-Build sind für dieses Paket
nicht erforderlich.

Die Grundkomponenten lassen sich mit `deploy/pi/install-prerequisites.sh`
vorbereiten. Das Skript ist auch separat unter
`artifacts/install-prerequisites.sh` verfügbar. Auf den Pi kopieren und dort:

```sh
bash install-prerequisites.sh --check
sudo bash install-prerequisites.sh --install
```

Unterstützt werden Raspberry Pi OS 64-Bit auf Bookworm oder Trixie und echte
Raspberry-Pi-Hardware. Ohne Option erfolgt nur die Prüfung. Die Installation
benötigt Internet, ergänzt bei fehlendem PHP 8.3 die signierte Sury-Paketquelle
und installiert die Grundpakete. Bestehende Paketkonfigurationen bleiben
bevorzugt erhalten; Pakete werden nicht entfernt. Bereits installierte Pakete
können dabei auf die angebotene Paketversion aktualisiert werden.
MariaDB, PHP-FPM 8.3 und Nginx werden aktiviert und gestartet.

Es werden keine Datenbankkonten, Zertifikate, Anwendungsdateien oder grafischen
Benutzerkonten eingerichtet. Für den Kiosk Raspberry Pi OS mit bereits
funktionsfähigem Desktop verwenden. Ein Lite-System erhält durch das Skript
keinen vollständigen Desktop.

`/usr/bin/php8.3 -v` muss PHP 8.3 anzeigen. Die aktuellen Dienstvorlagen verwenden
diesen festen Pfad; eine globale Umschaltung ist für PrivateBar nicht nötig.
Die Paketverwaltung kann bei der Installation die automatische php-Alternative
aktualisieren; bestehende andere PHP-Anwendungen danach entsprechend prüfen.
Ein bereits eingerichtetes System muss seine kopierten Dienstdateien bei Bedarf
anpassen. Benutzer, Datenbankzugänge, Zertifikate und Bildschirmgeräte werden
anschliessend gemäss den folgenden Schritten eingerichtet.

Quellen: [Raspberry Pi OS](https://www.raspberrypi.com/software/operating-systems/)
und [Sury-PHP-Paketquelle](https://packages.sury.org/php/README.txt).

## 1. Dateien auf einem neuen Pi einrichten

Die folgenden Befehle gelten für eine **neue Installation ohne bestehende
PrivateBar-Daten**. Das Archiv zuerst auf den Pi kopieren und im Verzeichnis
mit dem Archiv ausführen. Ein vorhandenes /srv/privatebar nicht überschreiben.

```sh
sudo useradd --system --user-group --home-dir /srv/privatebar --shell /usr/sbin/nologin privatebar
sudo mkdir -p /srv/privatebar/releases/1.0.0 /srv/privatebar/shared
sudo tar --no-same-owner -xzf privatebar-1.0.0-pi-installation.tar.gz -C /srv/privatebar/releases/1.0.0
sudo mv /srv/privatebar/releases/1.0.0/storage /srv/privatebar/shared/storage
sudo cp /srv/privatebar/releases/1.0.0/.env.example /srv/privatebar/shared/.env
sudo ln -s /srv/privatebar/shared/storage /srv/privatebar/releases/1.0.0/storage
sudo ln -s /srv/privatebar/shared/.env /srv/privatebar/releases/1.0.0/.env
sudo ln -s /srv/privatebar/releases/1.0.0 /srv/privatebar/current
sudo chown -R privatebar:privatebar /srv/privatebar
sudo chmod 600 /srv/privatebar/shared/.env
```

Das Systemkonto privatebar führt PHP und Hintergrundaufgaben aus. Für den
Chromium-Kiosk ein separates grafisches Benutzerkonto mit Desktop-Anmeldung
verwenden; das oben angelegte Systemkonto hat bewusst keine Anmeldeshell.

## 2. Datenbank und Konfiguration

In MariaDB eine neue Datenbank `privatebar` mit utf8mb4 und einen eigenen
Datenbankbenutzer mit Rechten ausschliesslich auf diese Datenbank anlegen.
Benutzerhost und DB_HOST müssen zusammenpassen; die Vorlage verwendet
127.0.0.1 und damit eine TCP-Verbindung.

```sh
sudo nano /srv/privatebar/shared/.env
```

DB_HOST, DB_DATABASE, DB_USERNAME und DB_PASSWORD eintragen. Die enthaltene
Vorlage ist auf `APP_ENV=production`, `PRIVATEBAR_MODE=pi` und
`APP_URL=https://privatebar.local` vorbereitet. URL bei anderem Hostnamen anpassen.
Den auf Cyon erzeugten Geräte-Token in PRIVATEBAR_DEVICE_TOKEN eintragen.
APP_KEY zunächst leer lassen. Azure-Zugangsdaten gehören ausschliesslich auf Cyon.

Erstinitialisierung mit dem Anwendungskonto:

```sh
cd /srv/privatebar/current
sudo -u privatebar /usr/bin/php8.3 artisan key:generate
sudo -u privatebar /usr/bin/php8.3 artisan migrate --seed --force
sudo -u privatebar /usr/bin/php8.3 artisan privatebar:pin
sudo -u privatebar /usr/bin/php8.3 artisan optimize
sudo -u privatebar /usr/bin/php8.3 artisan privatebar:health
```

Eine eigene sechsstellige PIN eingeben. key:generate nur beim Erstaufbau
aufrufen; einen bestehenden APP_KEY später niemals unkontrolliert ersetzen.

## 3. Webserver und HTTPS

Einen PHP-8.3-FPM-Pool mit `user = privatebar`, `group = privatebar` und auf
dem 2-GB-Pi wenigen Prozessen einrichten. Der Nginx-Benutzer muss auf den
FPM-Socket zugreifen können; dazu die Socket-Rechte im FPM-Pool passend setzen.
Der PHP-Prozess braucht Schreibrechte auf shared/storage und bootstrap/cache,
die mit der obigen Eigentümerzuordnung vorhanden sind.

`deploy/pi/nginx.conf` als Vorlage verwenden: Hostname, Zertifikatpfade und
fastcgi_pass an den tatsächlichen FPM-Socket anpassen. Webroot ist ausschliesslich
`/srv/privatebar/current/public`. Nginx muss die öffentlichen Dateien lesen können.
Die Konfiguration vor dem Neuladen mit `sudo nginx -t` prüfen.

Für privatebar.local ist eine lokale CA mit einer auf Kiosk und Smartphones
installierten Vertrauenskette erforderlich. Alternativ eine eigene DNS-Adresse
mit gültigem Zertifikat verwenden. Auf dem Pi muss der Kioskhostname direkt
auf 127.0.0.1 auflösen; auf anderen Geräten auf die LAN-Adresse des Pi.
TLS-Prüfungen nicht abschalten. Details stehen in `DEPLOYMENT.md`.

## 4. Hintergrundaufgaben und Kiosk

Nach erfolgreicher Einrichtung von Datenbank und Konfiguration:

```sh
sudo install -m 644 deploy/pi/privatebar-boot.service /etc/systemd/system/
sudo install -m 644 deploy/pi/privatebar-tick.service /etc/systemd/system/
sudo install -m 644 deploy/pi/privatebar-tick.timer /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now privatebar-boot.service privatebar-tick.timer
```

Im grafischen Kioskkonto automatisches Desktop-Login einrichten und
`/srv/privatebar/current/deploy/pi/kiosk.sh` im Autostart ausführen.
Das Skript öffnet https://privatebar.local; bei anderem Hostnamen anpassen.

Für SMB-Fotorahmen und Monitorsteuerung sind alle Vorlagen unter `deploy/pi/`
enthalten. Root-Helfer, Freigabe-Mount, User-Service, Eingabegerät und
Monitor-Umgebung gemäss den Abschnitten „SMB-Fotorahmen“ und „Monitor“ in
`DEPLOYMENT.md` einrichten. Diese Angaben hängen von der Hardware ab.
Die Update-Dienste erst mit einer tatsächlich freigegebenen, signierten
Releasequelle konfigurieren; dieses Erstinstallationsarchiv ist kein Pi-Update.

## 5. Abnahme

PIN-Anmeldung, Touchbedienung und Kamera prüfen. In den lokalen Einstellungen
„Jetzt synchronisieren“ starten und mit dem zuvor eingerichteten Cyon abgleichen.
Offlinebetrieb, Wiederverbindung, SMB-Ausfall, Fotorahmen-Dauertest und Monitor-
Ruhezeit am echten Gerät prüfen. Vollständige Abnahmepunkte: IMPLEMENTATION.md.
