# PrivateBar 1.1.0 – Cyon installieren oder aktualisieren

Die Pakete enthalten Produktionsbibliotheken und fertig gebaute Frontend-Dateien.
Composer, Node und SSH sind für die Installation nicht erforderlich.
Die tatsächliche Abnahme auf Cyon steht noch aus. Webroot ist ausschliesslich
`public/`. Die `.env`, Bibliotheken und private Dateien bleiben ausserhalb davon.

## Erstinstallation über my.cyon

1. `privatebar-1.1.0-cyon-installation.zip` verwenden. Vor dem Upload die
   zugehörige SHA-256-Prüfsumme prüfen (Windows: `Get-FileHash` in PowerShell;
   Linux: `sha256sum -c SHA256SUMS`). Das ZIP enthält die Dateien direkt,
   ohne zusätzliches Versions-Unterverzeichnis.
2. In my.cyon die Domain, eine **leere** MariaDB-Datenbank mit eigenem Konto
   und Let's Encrypt einrichten. HTTPS erzwingen. Unter Erweitert →
   PHP-Versionsmanager PHP 8.3 wählen. Paket im Dateimanager in einen eigenen
   Anwendungsordner hochladen und entpacken; Domain-Ziel auf dessen `public/`
   setzen. `storage/` und `bootstrap/cache/` müssen für PHP beschreibbar sein.
3. `.env.example` zu `.env` kopieren; Rechte `0600`. Die Vorlage ist bereits
   für `APP_ENV=production`, `PRIVATEBAR_MODE=cloud`, HTTPS und SMTP vorbereitet.
   `APP_URL`, `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` und die
   SMTP-Zugangsdaten des eigenen Cyon-Mailkontos ergänzen. `APP_KEY` beim
   Erstaufbau leer lassen; das Installationsskript erzeugt ihn. `APP_DEBUG=false`
   und `SESSION_SECURE_COOKIE=true` beibehalten. Keine Pi-PIN oder SMB-Zugänge
   hochladen. Externe Dienste zunächst ausgeschaltet lassen.
4. Im Dateimanager `storage/app/private/cyon-install/` mit Rechten `0700`
   erstellen und darin `input.json` mit Rechten `0600` anlegen:

   ```json
   {
     "email": "person@example.ch",
     "name": "Vorname",
     "password": "EIGENES PASSWORT MIT MINDESTENS ZWOELF ZEICHEN",
     "device_name": "Hausbar Pi"
   }
   ```

   Eigene Werte einsetzen; Anführungszeichen und Backslashes in JSON maskieren.
   Der absolute Projektpfad darf hier nicht über Symlinks erreichbar sein.
5. Unter Erweitert → Cronjobs vorübergehend minütlich starten:

   ```text
   /usr/bin/php83 /absoluter/pfad/zu/privatebar/tools/cyon-install.php
   ```

   Den tatsächlichen Hostingpfad einsetzen und die PHP-CLI-Version prüfen.
   Regulären Scheduler noch nicht starten. Die Installation erzeugt Grunddaten,
   Mitglied und Gerätezugang, baut Caches und prüft die Anwendung.
   Fehler nennen den betroffenen Schritt; nach Korrektur denselben Lauf erneut
   starten, vorhandenen APP_KEY und Geräte-Token erhalten.
6. Nach Erfolg ist `storage/app/private/cyon-install/complete` vorhanden.
   Installations-Cronjob entfernen. `device-token.txt` geschützt auf den Pi
   übertragen und dort unter Lokale Einstellungen → Cyon-Verbindung speichern.
   Danach `device-token.txt`, eine verbliebene `input.json` und
   `tools/cyon-install.php` **auf Cyon** löschen; `complete` und `lock` behalten.
7. Den regulären Cronjob jede Minute einrichten:

   ```text
   /usr/bin/php83 /absoluter/pfad/zu/privatebar/artisan schedule:run
   ```

8. HTTPS, Anmeldung, Bestätigungs-E-Mail und bestätigten Kontozugang prüfen.
   Azure-Zugangsdaten bei Bedarf nur in der Cyon-`.env` ergänzen; danach über
   einen temporären Cronjob `artisan optimize` ausführen und diesen entfernen.
   Rezeptimport und Übersetzung getrennt unter Einstellungen aktivieren.
   Auf dem Pi Verbindungstest und Synchronisation durchführen.

## Update von 1.0.4 auf 1.1.0 ohne SSH

Das Update-ZIP enthält **keine `.env` und kein `storage/`**. Es ist ein Overlay
für eine bereits eingerichtete 1.0.4-Instanz. Nicht das Installations-ZIP über
vorhandene private Daten entpacken. Version 1.1.0 ergänzt keine Migration;
der Migrationsschritt kontrolliert dennoch den vorhandenen Stand.

1. Vorhandene Datenbank über phpMyAdmin sichern; `.env` inklusive APP_KEY und
   `storage/` separat geschützt herunterladen. Vorherigen Programmordner
   sichern. Scheduler-Cronjob in my.cyon pausieren, einen laufenden Durchlauf
   abwarten. Noch nicht mit dem Upload beginnen.
2. Einen temporären Cronjob mit dem bisherigen Programm ausführen:

   ```text
   /usr/bin/php83 /absoluter/pfad/zu/privatebar/artisan privatebar:maintenance on
   ```

   Erfolgreiche Ausführung kontrollieren, Cronjob entfernen. Wartungsmodus
   sperrt Schreibzugriffe und die Pi-Synchronisation.
3. `privatebar-1.1.0-cyon-update.zip` samt Prüfsumme prüfen, hochladen und im
   bestehenden Projektordner mit Überschreiben entpacken. `.env` und `storage/`
   erhalten. Vorhandenen `vendor/` zuvor durch das neue vollständige `vendor/`
   ersetzen, damit alte Bibliotheken nicht zurückbleiben. In `bootstrap/cache/`
   alte PHP-Cachedateien entfernen; Verzeichnis beibehalten. Die Domain bleibt
   auf `public/`. Der Updateordner enthält kein Erstinstallationsskript.
4. Die folgenden Befehle **einzeln und in dieser Reihenfolge** als temporären
   Cronjob ausführen. Jeweils Erfolg kontrollieren und den betreffenden Cronjob
   entfernen, bevor der nächste angelegt wird. Bei Fehlern stoppen,
   Wartungsmodus und pausierten Scheduler beibehalten:

   ```text
   /usr/bin/php83 /absoluter/pfad/zu/privatebar/artisan optimize:clear
   /usr/bin/php83 /absoluter/pfad/zu/privatebar/artisan migrate --force
   /usr/bin/php83 /absoluter/pfad/zu/privatebar/artisan optimize
   /usr/bin/php83 /absoluter/pfad/zu/privatebar/artisan privatebar:health
   /usr/bin/php83 /absoluter/pfad/zu/privatebar/artisan privatebar:maintenance off
   ```

   Diese Darstellung ist eine Liste von fünf einzelnen Cron-Befehlen.
   Nicht `key:generate`, `migrate --seed` oder `tools/cyon-install.php` ausführen.
5. Regulären minütlichen Scheduler wieder aktivieren. Anmeldung,
   Produkthinweise, getrennte Dienstschalter und Datenbankexport unter
   Einstellungen prüfen. Pi ebenfalls aktualisieren und Synchronisation testen.

Bei einem Fehler die gesicherten Programmdateien wiederherstellen und die Caches
mit dem bisherigen Code neu aufbauen. `.env`/APP_KEY und private Daten erhalten.
Wartung erst nach erfolgreicher Gesundheitsprüfung aufheben. Eine Datenbank-
Rücksicherung braucht den kontrollierten Ablauf in DATENBANK-EXPORT.md.

## Datenbankexport und Neuinstallation mit bestehenden Daten

Unter Einstellungen → Datenbankexport das aktuelle Kontopasswort erneut eingeben
und die SQL-Datei geschützt herunterladen. Für eine Neuinstallation zusätzlich
`.env` mit unverändertem APP_KEY und die privaten Rezept-/Produktbilder sichern.
Der SQL-Export allein enthält keine Bilddateien oder Umgebungskonfiguration.

Bei Wiederherstellung **nicht das Erstinstallationsskript ausführen**.
SQL in eine leere Datenbank über phpMyAdmin importieren und die vollständige
[Wiederherstellungsanleitung](DATENBANK-EXPORT.md) befolgen: Wartung,
Migrationen, neue Sync-Epoche, Gesundheitsprüfung und kontrollierter Pi-Neuaufbau.
