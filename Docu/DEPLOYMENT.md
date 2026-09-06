# Installation und Betrieb

## Freigabestand

Die Codebasis ist zur lokalen Prüfung vorbereitet. Es wurden weder Cyon noch ein
Raspberry Pi verändert. Vor einem Release müssen die Zielumgebungen in
`deploy/release-approval.json` tatsächlich geprüft sein. Die Datei steht bewusst
auf `false`. Das Signierwerkzeug bricht bei fehlenden Freigaben, Testfehlern,
Formatfehlern oder Fehlern der statischen Analyse ab.

## Gemeinsame Voraussetzungen

PHP 8.3, Composer 2 und MariaDB. PHP benötigt zusätzlich zu Laravels Anforderungen
GD und PDO MySQL. Empfohlenes PHP-Speicherlimit: 256 MB, `upload_max_filesize=20M`,
`post_max_size=21M`. Auf dem 2-GB-Pi PHP-FPM auf wenige Prozesse begrenzen und
konkret vermessen. Keine Redis-Pflicht, kein Horizon und kein Queue-Worker.

Die PHP-8.3-Pakete müssen für das eingesetzte OS aus einer gepflegten Quelle
bereitgestellt werden; nicht blind das unversionierte `php`-Paket installieren.
Mit `php -v`, `php -m` und `composer check-platform-reqs --no-dev` kontrollieren.

`.env` und Schreibverzeichnisse nur für die nötigen Dienstkonten lesbar machen.
`APP_KEY` nach Einrichtung erhalten. Alle Zeiten intern UTC; die DB-Verbindung
setzt `+00:00`. `SESSION_SECURE_COOKIE=true` und `APP_DEBUG=false` in Produktion.
Nur `public/` ist Webroot; weder Repository noch `storage/app/private` freigeben.

## Cyon

### Mit SSH

1. Private Subdomain `privatebar.vonrufs.ch` einrichten, PHP 8.3 wählen und Webroot
   auf das `public/`-Verzeichnis zeigen lassen. Im Hostingpanel ein gültiges
   Let's-Encrypt-Zertifikat aktivieren; HTTP auf HTTPS umleiten.
2. Datenbank und eingeschränktes Datenbankkonto anlegen. Code per SSH aus einem
   geprüften Tag bereitstellen. Frontend vorher mit `php tools/build.php` bauen.
3. `.env` mit `APP_ENV=production`, `APP_URL=https://privatebar.vonrufs.ch`,
   `PRIVATEBAR_MODE=cloud`, `PRIVATEBAR_INSTANCE_ID=cyon-housebar` und den
   Datenbankzugängen einrichten. PIN und SMB-Zugangsdaten gehören nicht auf Cyon.
4. Im Releaseverzeichnis ausführen:

   ```sh
   composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
   php artisan key:generate
   php artisan migrate --seed --force
   php artisan privatebar:publish-state
   php artisan privatebar:member person@example.ch "Vorname"
   php artisan privatebar:device "Hausbar Pi"
   php artisan optimize
   php artisan privatebar:health
   ```

   `key:generate` ausschliesslich beim Erstaufbau. Der Geräte-Token wird einmal
   angezeigt und anschliessend nur in der geschützten Pi-Konfiguration gespeichert.
   Die Ausgabe nicht in geteilte Protokolle kopieren.
5. Einen Cronjob pro Minute mit dem im Hosting verfügbaren PHP-8.3-CLI-Pfad
   einrichten: `php /absoluter/pfad/artisan schedule:run`. Es läuft kein dauerhafter
   PHP-, Node- oder Python-Dienst auf Cyon.
6. `PRIVATEBAR_PROVIDERS_ENABLED=true`, `COCKTAILDB_KEY`,
   `AZURE_TRANSLATOR_KEY` und `AZURE_TRANSLATOR_REGION` erst nach Einrichtung der
   jeweiligen Dienste setzen. Danach `php artisan config:cache` erneuern.

Der konkrete SSH-Pfad, PHP-Binary-Pfad und die Hostingkonfiguration müssen im
bestehenden Cyon-Konto festgestellt werden; es wurden keine Zugangsdaten vorgegeben.
Die Anwendung verschickt Einladungen und Bestätigungs-E-Mails per SMTP.
Passwort-Resetlinks bleiben manuell teilbar. SMTP vor dem ersten Online-Login
gemäss dem folgenden Abschnitt einrichten.

### SMTP für Einladungen und E-Mail-Verifizierung

Ab 1.0.1 gehört der E-Mail-Versand zur Version 1. Auf Cyon ein eigenes Mailkonto
anlegen und dessen Daten in der dortigen `.env` eintragen:

```dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=smtps
MAIL_HOST=mail.cyon.ch
MAIL_PORT=465
MAIL_USERNAME=privatebar@vonrufs.ch
MAIL_PASSWORD="DAS_PASSWORT_DES_MAILKONTOS"
MAIL_FROM_ADDRESS=privatebar@vonrufs.ch
MAIL_FROM_NAME="PrivateBar"
MAIL_TIMEOUT=15
```

Die Beispieladresse muss tatsächlich als zulässiges Mailkonto/Absenderadresse
im Hosting eingerichtet sein. SMTP-Zugangsdaten sind nicht die Datenbankzugänge.
`APP_URL=https://privatebar.vonrufs.ch` muss auf die tatsächliche öffentliche
Adresse zeigen; daraus werden die E-Mail-Links gebildet. Ein vorhandenes
`MAIL_URL` entfernen, damit es die einzelnen SMTP-Einstellungen nicht übersteuert.
Nach Änderungen `php artisan config:cache` ausführen; ohne SSH einmalig per
my.cyon-Cronjob mit dem tatsächlichen PHP-/Projektpfad. Cronjob danach entfernen.

Cyon dokumentiert [mail.cyon.ch mit SSL/TLS auf Port 465](https://www.cyon.ch/support/a/e-mail-konto-einrichten-imap-pop3-und-smtp-einstellungen).
Der Versand erfolgt direkt während des Vorgangs mit 15 Sekunden SMTP-Timeout;
es ist kein Queue-Worker erforderlich. `MAIL_MAILER=log` und `array` werden für
Zugangs-E-Mails absichtlich abgelehnt, damit keine Zugangstoken in Logs landen.
Auf dem Pi `MAIL_MAILER=log` und leere SMTP-Zugangsdaten belassen: Er versendet
keine Einladungs- oder Bestätigungs-E-Mails.

Bestehende Konten mit leerem `email_verified_at` müssen nach dem Update ihre
Adresse bestätigen. Es wird kein bestehendes Konto automatisch als bestätigt
markiert. Die erste Anmeldung fordert eine Bestätigungs-E-Mail an. Bei einem
SMTP-Fehler bleibt das Konto erhalten und die Bestätigungsseite bietet einen
neuen Versuch an. Einladungsfehler widerrufen dagegen den neu erzeugten Link.
Annahme der Einladung allein bestätigt die E-Mail-Adresse nicht, da der Link
auch kopiert oder als QR-Code geteilt werden kann.

### Ohne SSH über my.cyon

Alternativ erfolgt die Bereitstellung über den my.cyon-Dateimanager und Cronjobs. Dafür ist keine interaktive Shell auf Cyon nötig; PHP-Befehle laufen
weiterhin serverseitig über Cron. Die Vorbereitung des Pakets erfolgt lokal mit
PHP 8.3 und Composer.

**Stand: Das einmalige Installationsskript `tools/cyon-install.php` ist
implementiert; die Abnahme auf echtem Cyon-Hosting steht noch aus.** Es ist nur
per CLI ausführbar, nicht über den Browser. Es ersetzt für diesen Ablauf die
interaktive Passwortabfrage von `privatebar:member`.

1. In my.cyon die Subdomain `privatebar.vonrufs.ch`, eine Datenbank mit eigenem
   Datenbankkonto und ein Let's-Encrypt-Zertifikat einrichten. HTTPS erzwingen
   und unter **Erweitert → PHP-Versionsmanager** PHP 8.3 wählen.
2. Lokal eine saubere Kopie des geprüften Tags vorbereiten:

   ```sh
   composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
   php tools/build.php
   ```

   Das Uploadpaket muss `vendor/`, die gebauten Frontend-Dateien und
   `public/.htaccess` enthalten. Keine lokale `.env`, Datenbanken, privaten
   Fotos oder lokal erzeugten Laravel-Konfigurationscaches übernehmen.
   Das Paket über den my.cyon-Dateimanager hochladen und entpacken.
3. Das Zielverzeichnis der Subdomain auf das Unterverzeichnis `public/` setzen.
   `.env`, `vendor/` und `storage/` dürfen nicht öffentlich erreichbar sein.
   Die Laravel-Schreibverzeichnisse `storage/` und `bootstrap/cache/` müssen
   vorhanden und für PHP beschreibbar sein.
4. Auf Cyon eine eigene `.env` anhand von `.env.example` erstellen:

   ```dotenv
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://privatebar.vonrufs.ch
   PRIVATEBAR_MODE=cloud
   PRIVATEBAR_INSTANCE_ID=cyon-housebar
   SESSION_SECURE_COOKIE=true
   ```

   Datenbankhost, Datenbankname, Benutzer und Passwort gemäss Hostingkonto
   ergänzen. Einen eigenen `APP_KEY` ausschliesslich beim Erstaufbau erzeugen
   und dauerhaft erhalten. PIN und SMB-Zugangsdaten nicht hochladen.
5. Im Dateimanager den Ordner `storage/app/private/cyon-install/` erstellen
   (Rechte `0700`) und darin die Datei `input.json` (Rechte `0600`) anlegen:

   ```json
   {
     "email": "person@example.ch",
     "name": "Vorname",
     "password": "HIER EIN EIGENES PASSWORT EINTRAGEN",
     "device_name": "Hausbar Pi"
   }
   ```

   Ein eigenes Passwort mit mindestens zwölf Zeichen einsetzen. JSON erfordert
   bei Anführungszeichen und Backslashes im Passwort entsprechende Maskierung.
   Diese Datei nie in Git oder ein allgemeines Releasearchiv aufnehmen.
   `.env` ebenfalls auf `0600` setzen. Alle Projektdateien müssen unter einem
   echten absoluten Pfad liegen; der Installationsordner darf keine Symlinks
   enthalten oder über solche erreichbar sein.

   Unter **Erweitert → Cronjobs** vorübergehend minütlich ausführen:

   ```text
   /usr/bin/php83 /absoluter/pfad/zu/privatebar/tools/cyon-install.php
   ```

   Beide Pfade an das Hosting anpassen. Den regulären Scheduler erst nach der
   Installation aktivieren. Die Domain bis zum erfolgreichen Abschluss noch
   nicht für den normalen Zugriff freigeben.

   Das Skript prüft PHP und Erweiterungen sowie die Cloud-Produktionskonfiguration,
   erzeugt einen fehlenden `APP_KEY`, führt Migrationen durch und legt Grunddaten,
   Mitglied, Gerätezugang und Erstabgleich in einer gemeinsamen Transaktion an.
   Danach erstellt es die Laravel-Caches und prüft Datenbank und Frontend.
   Ein vorhandener Schlüssel bleibt erhalten. Eine Instanz mit bestehenden
   Mitgliedern oder Geräten wird abgelehnt, sofern sie nicht zu diesem
   angefangenen Installationslauf gehört.

   Fehlerausgaben nennen nur den fehlgeschlagenen Schritt, keine Zugangsdaten.
   Nach einem Fehler die Ursache prüfen und denselben Lauf erneut starten:
   `.env` und `device-token.txt` unverändert behalten. Migrationen sind separat
   wiederanlaufbar; ein bereits bestätigter Datenaufbau wird nicht wiederholt.
   Eine Dateisperre verhindert parallele Installationsläufe.

   Nach Erfolg liegt im Installationsordner die Sperrdatei `complete`;
   weitere Aufrufe beenden sich ohne erneute Installation. `input.json` wird
   automatisch gelöscht. Den temporären Cronjob entfernen und den Inhalt von
   `device-token.txt` geschützt als `PRIVATEBAR_DEVICE_TOKEN` in die Pi-`.env`
   übernehmen. Danach `device-token.txt`, eine allenfalls verbliebene
   `input.json` und `tools/cyon-install.php` auf Cyon löschen. `complete` und
   `lock` behalten. Der Geräte-Token wird niemals in der Cron-Ausgabe angezeigt.
6. Nach erfolgreicher Erstinstallation unter **Erweitert → Cronjobs** jede
   Minute den Scheduler starten. Beispiel mit anzupassenden absoluten Pfaden:

   ```text
   /usr/bin/php83 /absoluter/pfad/zu/privatebar/artisan schedule:run
   ```

   PHP-8.3-CLI-Pfad und Projektpfad im konkreten Hostingkonto prüfen; die
   PHP-Version für Browseraufrufe legt nicht automatisch die Cron-Version fest.
7. Anmeldung, HTTPS, Schreibzugriffe und Hintergrundaufgaben auf Cyon prüfen.
   Anbieterzugänge wie im SSH-Ablauf erst nach deren Einrichtung aktivieren und
   anschliessend den Konfigurationscache serverseitig erneuern.

Auch spätere Updates benötigen serverseitige Migrationen, Cache-Erneuerung und
Gesundheitsprüfungen. Dafür muss ein separater, kontrollierter Cron-Ablauf
vorbereitet werden; das Erstinstallationsskript darf nicht erneut verwendet
werden. Dasselbe gilt für die Befehle unter „Wartung und Wiederherstellung“.
Diese Betriebsabläufe ohne SSH sind ebenfalls noch nicht implementiert oder
abgenommen.

Cyon-Anleitungen: [Dateimanager](https://www.cyon.ch/support/a/bedienung-vom-dateimanager-im-my-cyon),
[PHP-Versionsmanager](https://www.cyon.ch/support/a/php-versionsmanager) und
[Cronjobs](https://www.cyon.ch/support/a/cronjob-erstellen-und-bearbeiten).

## Raspberry Pi

Für das vorbereitete Paket `artifacts/privatebar-1.0.0-pi-installation.tar.gz`
steht der Erstaufbau in [INSTALLATION-PI.md](INSTALLATION-PI.md). Bibliotheken
und Frontend sind darin enthalten; Composer-Installation und Frontend-Build
entfallen bei Verwendung dieses Pakets. Es ist kein signiertes Pi-Update.

- Raspberry Pi 4, mindestens 2 GB RAM, 64-Bit-OS, 32 GB oder mehr Speicher;
  USB-SSD bevorzugt. Versorgung 5 V / 3 A, geeignete Kühlung und Touchmonitor
  1920 × 1200 im Querformat entsprechend der Spezifikation.
- Dediziertes Konto `privatebar`; MariaDB, PHP 8.3/FPM, Nginx oder Apache,
  Chromium, `cifs-utils`, Python 3 und `wlr-randr` für die Wayland-Integration.
- Verzeichnisstruktur:

  ```text
  /srv/privatebar/current -> releases/0.1.0
  /srv/privatebar/releases/0.1.0/
  /srv/privatebar/shared/.env
  /srv/privatebar/shared/storage/
  ```

  Jeder Release enthält Symlinks auf die gemeinsame `.env` und `storage`.
  Programmverzeichnisse und Symlink müssen für den lokalen Update-Dienst
  beschreibbar sein. Systemdienstdateien und Root-Helfer bleiben root-eigen.
- `.env`: `PRIVATEBAR_MODE=pi`, `PRIVATEBAR_INSTANCE_ID=pi-housebar`, lokale
  MariaDB-Datenbank, Cyon-HTTPS-Adresse und eigener Geräte-Token. Azure-Zugang
  ausschliesslich auf Cyon. Externe Produktsuche darf nach Wunsch auch lokal
  aktiviert sein; ohne Internet bleibt die manuelle Erfassung nutzbar.
- Erstinitialisierung: `composer install`, `php artisan key:generate`,
  `php artisan migrate --seed --force`, `php artisan privatebar:pin`,
  `php tools/build.php`, `php artisan optimize`.
- Die lokale HTTPS-Adresse benötigt ein von den verwendeten Geräten akzeptiertes
  Zertifikat. Für `privatebar.local` ist eine eigene lokale CA mit installierter
  Vertrauenskette möglich; Let's Encrypt stellt keine `.local`-Zertifikate aus.
  Alternativ eine eigene DNS-Adresse mit DNS-basierter Zertifikatsvalidierung
  verwenden. Kamera-Funktion nicht mit abgeschalteten TLS-Prüfungen betreiben.
- Der Kioskname muss **direkt zu 127.0.0.1** auflösen. Smartphones verwenden den
  lokalen Webserver über seine LAN-Adresse bzw. denselben Namen im lokalen DNS.
  Kein Loopback-Reverse-Proxy: Er würde die lokale Verwaltungsgrenze verfälschen.
  `deploy/pi/nginx.conf` ist eine anzupassende Vorlage.

### Kiosk und Hintergrundaufgaben

Die Desktop-Anmeldung des dedizierten Kioskkontos automatisch starten lassen und
`deploy/pi/kiosk.sh` im Autostart der eingesetzten Wayland-Sitzung eintragen.
Chromium startet im Inkognito-Kioskprofil; Browserneustart verlangt die PIN erneut.
Die genaue Autostartdatei hängt vom verwendeten Raspberry-Pi-OS-Desktop ab.

`privatebar-boot.service`, `privatebar-tick.service/.timer` nach
`/etc/systemd/system/` installieren. Bootservice und Minutentimer aktivieren.
Der Pi synchronisiert bei Neustart und alle zehn Minuten; nach einem fehlgeschlagenen
Versuch erfolgt minütlich ein erneuter Versuch, wodurch die Rückkehr der Verbindung
innerhalb einer Minute erkannt wird. Ein Klick auf „Jetzt synchronisieren“ fordert
nur einen kurzen Hintergrundlauf an und blockiert die Oberfläche nicht.

### SMB-Fotorahmen

`deploy/pi/smb-mount.py` root-eigen nach `/usr/local/lib/privatebar/` kopieren und
`privatebar-smb.service/.timer` installieren. Der Helfer läuft kurz als Root,
liest die verschlüsselte Konfiguration über das Anwendungskonto, schreibt
Zugangsdaten nur vorübergehend nach `/run/privatebar` mit Modus 0600 und entfernt
sie nach dem Mountversuch. Er verwendet weder eine Shell mit interpolierten
Zugangsdaten noch protokolliert er das Passwort.

Die Freigabe wird unter `/mnt/privatebar-photos` mit `ro,nosuid,nodev,noexec`
eingehängt. Das Konto auf dem SMB-Server sollte ebenfalls nur lesen dürfen.
Server, Freigabe und Unterpfad werden direkt am Pi nach erneuter PIN-Eingabe
konfiguriert. „Verbindung testen“ beauftragt den nächsten Helferlauf; das Ergebnis
steht in den Einstellungen. Originalbilder werden nicht verändert.

Der Index läuft in begrenzten Schritten, der Cache standardmässig mit 2048 MB.
Unlesbare Bilder werden übersprungen. Bei Netzunterbruch bleibt der Cache nutzbar.
Private Bilder besitzen keine erlaubte Synchronisationsroute.

### Monitor

`deploy/pi/monitor.py` nach `/usr/local/lib/privatebar/` kopieren und
`privatebar-monitor.service` als User-Service der grafischen Sitzung installieren.
Das Dienstkonto benötigt Leserecht auf genau das Touch-Eingabegerät sowie Zugriff
auf die bestehende Wayland-Sitzung. In `~/.config/privatebar/monitor.env` setzen:

```text
PRIVATEBAR_TOUCH_DEVICE=/dev/input/by-id/DER_TATSAECHLICHE_TOUCHSCREEN-event-if00
PRIVATEBAR_MONITOR_OUTPUT=HDMI-A-1
```

Eingabegerät und Ausgang zuerst mit der realen Hardware ermitteln. Wayland-Umgebung
in den User-Service importieren. Das kleine Python-Programm beobachtet das
Eingabegerät, liest alle 30 Sekunden die Steuerdaten und schaltet mit `wlr-randr`.
Jede physische Touchinteraktion verlängert die Aktivität um 29 Minuten. Für andere
Display-Server ist ein entsprechend geprüfter Adapter nötig.

## Updates und Release

Kein Merge deployt. `.github/workflows/check.yml` führt nur Prüfungen durch.
Releases erfolgen manuell aus einem privaten GitHub-Repository über geprüfte Tags.

1. `php tools/build.php`, `composer check`, MariaDB- und Browserprüfungen ausführen.
   Pi- und Cyon-Abnahme einschliesslich Migrationskompatibilität dokumentieren.
2. Versionsnummer in `config/privatebar.php` setzen und die tatsächlich geprüfte
   Version in `deploy/release-approval.json` bestätigen. Ein ungeprüftes Release
   bleibt gesperrt; `false` nicht nur zum Umgehen der Prüfung ändern.
3. Aus exakt dem geprüften Tag eine saubere Releasekopie erstellen, Produktions-
   Composer-Abhängigkeiten installieren und ein **unkomprimiertes TAR-Archiv** bauen.
   Archivwurzel ist die Anwendung, nicht ein zusätzliches Oberverzeichnis.
   Keine `.env`, `storage`, lokalen Daten, Symlinks, Tests oder privaten
   Signaturschlüssel ins Archiv aufnehmen. Composer-Vendor und gebaute Assets
   müssen enthalten sein.
4. Auf dem Freigaberechner einen privaten RSA-Signaturschlüssel sicher aufbewahren;
   ausschliesslich der öffentliche Schlüssel wird am Pi eingerichtet. Das Werkzeug
   signiert die exakten Manifestbytes und deren Archivprüfsumme:

   ```sh
   php tools/release-manifest.php VERSION release.tar HTTPS_ASSET_URL PRIVATE_KEY manifest.json
   ```

5. Zuerst Cyon per SSH aktualisieren: neue Programmversion, rückwärtskompatible
   Migrationen, optimierter Autoloader und `php artisan optimize`. Die vorherige
   Pi-Clientversion muss weiter bedient werden.
6. Manifest und Archiv über HTTPS bereitstellen. Bei privaten GitHub-Assets die
   authentifizierten Asset-API-Adressen verwenden und den nur lesenden
   `PRIVATEBAR_RELEASE_TOKEN` geschützt hinterlegen. Keine Tokens in URLs.
   `PRIVATEBAR_RELEASE_MANIFEST` und `PRIVATEBAR_RELEASE_PUBLIC_KEY` auf dem Pi
   setzen; danach Konfigurationscache aktualisieren.
7. `privatebar-update.service/.timer` installieren. Direkt am Pi nach PIN
   „Update prüfen“, dann „Freigegebene Version installieren“ verwenden.

Der Installer prüft Signatur, SHA-256, Archivgrösse, mindestens 512 MB freien Platz,
API-Kompatibilität, Archivpfade und vollständige Programmdateien. Er führt Migration,
Optimierung und Gesundheitsprüfung im neuen Verzeichnis aus und schaltet danach
atomar den `current`-Symlink um. Bei Fehler bleibt bzw. wird die vorige Programmversion
aktiv. Nach einem Migrationsfehler bleibt Wartung aktiv, weil eine automatische
Rückmigration Daten beschädigen könnte. Unfertige Releaseverzeichnisse vor einem
neuen Versuch direkt am Pi prüfen und gezielt entfernen.

## Wartung und Wiederherstellung

PrivateBar erstellt **keine automatischen Datenbanksicherungen**. Synchronisation
ist kein Backup. Cyon garantiert durch diese Anwendung keine bestimmten
Sicherungspunkte; Verfügbarkeit und Zustand eines Hosting-Backups vorab prüfen.

1. Am Pi Wartung aktivieren. Auch Cyon mit `php artisan privatebar:maintenance on`
   sperren und den Scheduler für die Wiederherstellung pausieren.
2. Cyon-Datenbank aus dem gewählten Hosting-Backup wiederherstellen. Den Wartungs-
   zustand unmittelbar danach erneut setzen, falls er durch den Restore ersetzt
   wurde. Datenbankinhalt und kompatible Programmversion direkt prüfen.
3. Auf Cyon `php artisan privatebar:new-epoch` ausführen. Dadurch verweigert der
   Server automatische Abgleiche mit der alten Epoche statt neuere Pi-Daten
   ungeprüft über den Restore zu schreiben.
4. Offene lokale Änderungen prüfen und über deren Übernahme oder bewusstes Verwerfen
   entscheiden. Bei Bedarf wichtige Inhalte kontrolliert manuell übernehmen.
5. Cyon-Wartung nach Prüfung beenden und `php artisan privatebar:publish-state`
   ausführen. Die Ausgabe enthält die neue Epoche und den Startcursor einer
   vollständigen Projektion des bestätigten Cyon-Zustands.
6. Direkt am noch gesperrten Pi
   `php artisan privatebar:reset-projection EPOCHE STARTCURSOR` ausführen. Der
   Befehl verlangt die PIN und eine bewusste lokale Bestätigung. Bei unbestätigten
   Änderungen bricht er ab; deren bewusstes Verwerfen benötigt zusätzlich
   `--discard-pending`. PIN, SMB-Konfiguration, Fotocache und lokales Audit bleiben
   erhalten; nur die synchronisierte Projektion wird für den Neuaufbau geleert.
7. Pi-Wartung lokal mit PIN beenden, synchronisieren und Bestand, eigene Rezepte,
   Bilder und Einkaufsliste vergleichen. Scheduler erst nach erfolgreicher
   Kontrolle wieder regulär betreiben.

Diesen Ablauf zunächst mit Testdaten erproben. Er wurde noch nicht gegen ein echtes
Cyon-Hosting-Backup und eine physische Pi-Instanz abgenommen.
