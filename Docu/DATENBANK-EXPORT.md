# Manueller Cyon-Datenbankexport

## Export herunterladen

1. Auf Cyon als bestätigtes Mitglied anmelden.
2. **Einstellungen → Datenbank exportieren** öffnen.
3. Das aktuelle Kontopasswort eingeben und **Datenbankexport herunterladen** wählen.
4. Die Datei `privatebar-datenbank-DATUM-ZEIT.sql` geschützt aufbewahren.

Der Export enthält Tabellenstruktur und dauerhafte Daten der PrivateBar-Datenbank,
auch Konten samt Passwort-Hashes, Gerätezugangshashes, Katalog, Zuordnungen,
Barbestand, Favoriten, Bewertungen, Einkaufsliste, Übersetzungen, Einstellungen,
Änderungsverlauf, Sync-Ereignisse und Migrationsstand. Die Quelle bleibt unverändert.
Alle Daten werden aus einem gemeinsamen InnoDB-Snapshot gelesen, in begrenzten
Batches geschrieben und erst nach erfolgreichem Abschluss zum Download angeboten.
Keine Shell oder `mysqldump` erforderlich. Während des Exports keine Migrationen
oder Änderungen der Tabellenstruktur ausführen.

Sitzungen, Browser-Daueranmeldungen, Cache und Warteschlangen werden nicht
wiederhergestellt. Nach dem Import ist eine neue Anmeldung erforderlich.
Der wiederhergestellte Stand startet automatisch im Wartungsmodus.
Es entstehen keine automatischen Sicherungen; eine temporäre Datei ausserhalb des
Webroots wird nach dem Download gelöscht. Bei einem abgebrochenen PHP-Prozess
kann eine Restdatei unter `storage/app/private/database-exports/` zurückbleiben;
nach Kontrolle im my.cyon-Dateimanager entfernen.

## Zusätzlich sichern

Die SQL-Datei enthält keine Bilddateien oder Umgebungskonfiguration:

- `storage/app/private/recipes/` und `storage/app/private/products/` separat sichern.
- Die `.env` separat geschützt sichern, insbesondere den ursprünglichen `APP_KEY`.
  Beim Wechsel des Hostings die Datenbankzugänge anpassen und diesen APP_KEY behalten,
  damit eventuell verschlüsselte Einstellungen wieder lesbar sind.
- Die zum Export passende Programmversion beziehungsweise das Installationspaket
  aufbewahren. Die Version steht im Kommentar am Anfang der SQL-Datei.

Die SQL-Datei enthält persönliche Daten und Authentifizierungs-Hashes. Sie gehört
weder nach `public/` noch in Git oder an eine öffentlich erreichbare Downloadadresse.

## Neuinstallation ohne SSH

1. Scheduler/Cronjobs pausieren; einen vorhandenen Pi lokal in Wartung versetzen.
   Die neue Installation noch nicht für regulären Zugriff freigeben.
2. Das passende vollständige Cyon-Programmpaket per my.cyon-Dateimanager installieren.
   Webroot ist ausschliesslich `public/`. Die bisherige `.env` und die Bildverzeichnisse
   geschützt wiederherstellen; Datenbankzugänge auf das neue Ziel anpassen.
3. In my.cyon eine **neue, leere Datenbank** anlegen und deren phpMyAdmin öffnen.
   Die Ziel-Datenbank auswählen, **Importieren** öffnen, SQL-Datei auswählen und
   mit Format **SQL** importieren. Anleitung: [phpMyAdmin-Import](https://docs.phpmyadmin.net/de/latest/import_export.html).
   Der Export enthält kein `CREATE DATABASE`, keine Datenbanknamenbindung und kein
   `DROP TABLE`; vorhandene Tabellen werden nicht überschrieben. Die in phpMyAdmin
   angezeigten Upload-/Zeitlimits beachten. Bei fehlgeschlagenem Import das neue,
   unvollständige Ziel gezielt leeren oder ein weiteres leeres Ziel verwenden.
4. **Nicht** `tools/cyon-install.php` ausführen: Der Import enthält bereits Konten,
   Gerätezugänge und Grunddaten. Keine neuen Grunddaten darüber säen und keinen
   neuen APP_KEY erzeugen.
5. Folgende Befehle als einzelne temporäre my.cyon-Cronjobs ausführen. Ersetze
   `/ABSOLUTER/PFAD/privatebar` durch das tatsächliche Anwendungsverzeichnis;
   Ausgaben und Exitstatus nach jedem Schritt prüfen und bei Fehler stoppen:

   ```text
   /usr/bin/php83 /ABSOLUTER/PFAD/privatebar/artisan config:clear
   /usr/bin/php83 /ABSOLUTER/PFAD/privatebar/artisan migrate --force
   /usr/bin/php83 /ABSOLUTER/PFAD/privatebar/artisan privatebar:maintenance on
   /usr/bin/php83 /ABSOLUTER/PFAD/privatebar/artisan privatebar:new-epoch
   /usr/bin/php83 /ABSOLUTER/PFAD/privatebar/artisan optimize
   /usr/bin/php83 /ABSOLUTER/PFAD/privatebar/artisan privatebar:health
   ```

   Der Import setzt bereits Wartung; die erneute Anweisung bestätigt diesen Zustand.
   Die neue Epoche schützt vor ungeprüftem Abgleich neuerer Pi-Daten über den Restore.
   Die temporären Cronjobs nach erfolgreichem Lauf jeweils entfernen.
6. Danach den kontrollierten Wiederherstellungsablauf unter
   [Deployment → Wartung und Wiederherstellung](DEPLOYMENT.md#wartung-und-wiederherstellung)
   ab Schritt 4 fortsetzen: offene Pi-Änderungen prüfen, Cyon-Wartung beenden,
   `privatebar:publish-state` ausführen und Epoche/Startcursor für den lokalen
   Neuaufbau verwenden. Nach Anmeldung auf Cyon Konten, Rezepte, Bestand, Bilder,
   Favoriten, Bewertungen und Einkaufsliste kontrollieren.
7. Scheduler erst nach erfolgreicher Kontrolle aktivieren. Ohne vorhandenen Pi
   entfällt dessen Projektionsreset; den neuen Pi mit dem wiederhergestellten
   beziehungsweise einem neu erzeugten Gerätezugang initial synchronisieren.

Der SQL-Roundtrip wird lokal mit einer separaten MariaDB geprüft. Import in der
realen Cyon-phpMyAdmin-Oberfläche, grosse reale Datenbestände und physischer
Pi-Wiederanlauf sind weiterhin separat abzunehmen.
