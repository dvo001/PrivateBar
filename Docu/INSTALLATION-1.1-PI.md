# PrivateBar 1.1.0 – Raspberry-Pi-Erstinstallation

Das Paket `privatebar-1.1.0-pi-installation.tar.gz` enthält die Anwendung,
Produktionsbibliotheken, gebaute Frontend-Dateien und die Vorlagen für Pi-Dienste.
Es ist ein Anwendungspaket für die Erstinstallation, kein SD-Karten-Abbild und
kein signiertes Update für „Freigegebene Version installieren“.
Die Abnahme auf dem echten Raspberry Pi steht noch aus. Prüfsummen und Pakete stehen
unter `artifacts/1.1.0/`; vor dem Entpacken `sha256sum -c SHA256SUMS` ausführen.
Für eine bestehende Installation den Abschnitt „Update“ am Ende verwenden.

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
`artifacts/1.1.0/install-prerequisites.sh` verfügbar. Auf den Pi kopieren und dort:

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
sudo mkdir -p /srv/privatebar/releases/1.1.0 /srv/privatebar/shared
sudo tar --no-same-owner -xzf privatebar-1.1.0-pi-installation.tar.gz -C /srv/privatebar/releases/1.1.0
sudo mv /srv/privatebar/releases/1.1.0/storage /srv/privatebar/shared/storage
sudo cp /srv/privatebar/releases/1.1.0/.env.example /srv/privatebar/shared/.env
sudo ln -s /srv/privatebar/shared/storage /srv/privatebar/releases/1.1.0/storage
sudo ln -s /srv/privatebar/shared/.env /srv/privatebar/releases/1.1.0/.env
sudo ln -s /srv/privatebar/releases/1.1.0 /srv/privatebar/current
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
Die Cyon-Adresse und den auf Cyon erzeugten Geräte-Token nach der Installation
im Menü Einstellungen → Lokale Einstellungen → Cyon-Verbindung speichern
und dort „Verbindung testen“ ausführen. Alternativ sind die .env-Startwerte möglich.
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
enthalten. Root-Helfer und Freigabe-Mount gemäss „SMB-Fotorahmen“ in `DEPLOYMENT.md`
einrichten. Die Monitor-Einrichtung folgt unten. Diese Angaben hängen von der Hardware ab.
Die Update-Dienste erst mit einer tatsächlich freigegebenen, signierten
Releasequelle konfigurieren; dieses Erstinstallationsarchiv ist kein Pi-Update.

## 5. Abnahme

PIN-Anmeldung, Touchbedienung und Kamera prüfen. In den lokalen Einstellungen
„Jetzt synchronisieren“ starten und mit dem zuvor eingerichteten Cyon abgleichen.
Offlinebetrieb, Wiederverbindung, SMB-Ausfall, Fotorahmen-Dauertest und Monitor-
Ruhezeit am echten Gerät prüfen. Vollständige Abnahmepunkte: IMPLEMENTATION.md.

## OFF-Uhr und Monitor-Dienst

Der Bildschirm bleibt eingeschaltet und zeigt während der Ruhezeit eine Uhr.
Die Leuchtkraft dimmt die Uhr, nicht die Hintergrundbeleuchtung des Bildschirms.
Nach PIN-Anmeldung am lokalen Kiosk lassen sich unter Einstellungen → Lokale
Einstellungen der Zeitplan, analoge/digitale Darstellung, Farbe, Leuchtkraft und
Weckdauer nach Berührung einstellen. Erste Berührung öffnet die Bar;
weitere Bedienung verlängert die Weckdauer. Währenddessen pausiert der Fotorahmen.

Als root das aktuelle Monitorprogramm installieren:

```sh
sudo install -d /usr/local/lib/privatebar
sudo install -m 755 /srv/privatebar/current/deploy/pi/monitor.py /usr/local/lib/privatebar/monitor.py
```

Die folgenden Befehle **im Terminal des angemeldeten grafischen Kioskkontos**
ausführen, nicht als root und nicht in einer unabhängigen SSH-Sitzung:

```sh
mkdir -p ~/.config/privatebar ~/.config/systemd/user
cp /srv/privatebar/current/deploy/pi/privatebar-monitor.service ~/.config/systemd/user/
wlr-randr
nano ~/.config/privatebar/monitor.env
```

In der Datei den tatsächlich angezeigten Ausgang eintragen, beispielsweise:

```dotenv
PRIVATEBAR_MONITOR_OUTPUT=HDMI-A-1
```

Danach in derselben Wayland-Sitzung:

```sh
systemctl --user import-environment WAYLAND_DISPLAY XDG_RUNTIME_DIR
systemctl --user daemon-reload
systemctl --user enable --now privatebar-monitor.service
systemctl --user restart privatebar-monitor.service
systemctl --user status privatebar-monitor.service
```

Den Umgebungsimport auch im Autostart der grafischen Sitzung vor dem Start
von Monitor-Dienst und Kiosk ausführen. Der Browser muss privatebar.local direkt
über 127.0.0.1 erreichen; ein Zugriff über LAN-IP aktiviert die lokale OFF-Uhr nicht.

## Update einer bestehenden Pi-Installation

1. Datenbank, `/srv/privatebar/shared/.env` und `shared/storage` sichern.
   Den bestehenden APP_KEY erhalten. Den bisherigen `current`-Zielpfad notieren.
2. Archiv, zugehörige `.sha256`, `SHA256SUMS` und `install-pi-release.sh`
   aus dem Versionsordner auf den Pi kopieren; Prüfsummen dort prüfen.
   Das Skript erwartet die vorhandene Struktur `/srv/privatebar/releases`,
   `/srv/privatebar/shared` und den Symlink `/srv/privatebar/current` sowie
   das Konto `privatebar` und den installierten `privatebar-tick.timer`.
   Fehlende Dienste zuerst wie oben installieren; bei abweichenden Pfaden die
   dokumentierten Skriptvariablen verwenden.
3. Im Verzeichnis der kopierten Dateien ausführen:

   ```sh
   sha256sum -c SHA256SUMS
   sudo bash install-pi-release.sh ./privatebar-1.1.0-pi-installation.tar.gz
   ```

   Der Timer wird während des Wechsels angehalten. `.env` und `storage` bleiben
   gemeinsam erhalten. Cache und Gesundheitsprüfung erfolgen vor dem atomaren
   Umschalten von `current`; ein vorher aktiver Timer wird wieder gestartet.
   Version 1.1.0 ergänzt gegenüber 1.0.4 keine Migration. Das Skript ist für
   bestehende 1.0.4-Installationen vorgesehen; ältere Stände zuerst gemäss deren
   Updateanleitung migrieren. Nicht erneut `key:generate` oder `migrate --seed` ausführen.
4. **Monitorprogramm und User-Service gemäss dem Abschnitt oben ersetzen und
   neu starten.** Ein alter Monitor-Dienst würde den Bildschirm weiterhin
   ausschalten. Kiosk neu starten oder die Seite vollständig neu laden.
5. PIN, Bestand, Produkthinweise, Synchronisation, Offlinebetrieb und die
   analoge/digitale OFF-Uhr samt Berührung, Sommerzeit und Zeitplan prüfen.
   Der Fotorahmen muss nach der Weckdauer wieder korrekt pausieren/fortfahren.

Bei Fehlern vor dem Wechsel bleibt `current` auf dem bisherigen Stand.
Protokolle stehen in `shared/storage/logs/pi-release-1.1.0-*.log`.
Für eine Rückkehr nach erfolgreichem Wechsel den Tick-Timer stoppen, `current`
atomar auf den notierten alten Release setzen und den zuvor aktiven Timer wieder
starten. Auch Monitorprogramm/User-Service aus der gesicherten Version zurücksetzen.
Nicht blind alte Dateien über `shared/storage` oder `.env` kopieren.

Dieses manuelle Paket ist kein signiertes Update für die Versionsverwaltung
im Menü. Eine Produktionsfreigabe ist erst nach der echten Pi-/Cyon-Abnahme möglich.
