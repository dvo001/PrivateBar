# PrivateBar 1.2.0 – API Ninjas

Stand: 9. Oktober 2026. Neues manuelles Cyon-/Pi-Update, ohne neue Migration
oder Composer-Abhängigkeit. TheCocktailDB und OpenDrinks bleiben erhalten.
API Ninjas ergänzt gezielte Suchtreffer, keinen vollständigen Katalog.

## Quelle und Kontingent

API-Key ausschliesslich in der geschützten Cyon-`.env` eintragen:

```dotenv
API_NINJAS_KEY=EIGENER_KEY
API_NINJAS_MONTHLY_LIMIT=2800
API_NINJAS_COCKTAIL_QUERIES="margarita,martini,mojito,negroni,daiquiri,bloody mary"
```

Den echten Schlüssel nicht in Git, Chat, Pi-Konfiguration oder Browser eintragen.
Ohne Schlüssel wird die optionale Quelle übersprungen. Der bestehende Schalter
«Rezeptimport» steuert alle Quellen. Nach TheCocktailDB und OpenDrinks folgt
pro Hintergrundlauf ein Suchbegriff; jeder Suchbegriff liefert höchstens zehn
Treffer. Sechs Begriffe benötigen bei täglichem Import maximal 186 Calls pro
Monat, ohne Wiederholungen. Mehr Begriffe und kürzere Importintervalle erhöhen
den Verbrauch. Fehler behalten den Cursor; der nächste Lauf versucht erneut.

Das vom Benutzer genannte Kontingent beträgt 3000 Calls pro Monat. PrivateBar
begrenzt seine eigenen Anfragen standardmässig auf 2800 je Kalendermonat in
Europe/Zurich, inklusive fehlgeschlagener Versuche. Das lässt 200 Calls Reserve.
Auf anderen Anwendungen verbrauchte Calls kennt PrivateBar nicht; die Grenze
bei gemeinsam genutztem Key entsprechend senken. Der Anbieter-Abrechnungszeitraum
kann abweichen. Beim Erreichen der Grenze bleiben bisherige Rezepte nutzbar;
der Import setzt im nächsten Monat fort. Zähler liegen nur auf Cyon.

Dokumentation: https://api-ninjas.com/api/cocktail
Nutzungsbedingungen: https://api-ninjas.com/tos
Ein API-Key bestätigt keine Rechte zur dauerhaften Speicherung, Übersetzung oder
Weitergabe an Haushaltsmitglieder. Diese Bedingungen vor Aktivierung klären.
Der Quellhinweis wird mit Originalantwort und Importzeitpunkt gespeichert.
API Ninjas liefert keine dokumentierte stabile Rezept-ID: PrivateBar bildet
sie aus normalisiertem Namen und Zutatenbezeichnungen. Mengen-/Textänderungen
aktualisieren dieselbe Quelle; geänderte Zutatenzusammensetzung ist eine Variante.
Unklare Mengen bleiben erhalten, optionale Zutaten werden nicht erraten.
Die API liefert keine Alkoholklassifikation; bestehender Importstandard ist
alkoholisch. Bilder, Gläser und Methoden werden nicht erfunden.

## Update ohne Cyon-Shellzugriff

Die folgenden Befehle verwenden die Vorgaben aus [cyon-settings.md](cyon-settings.md):
Projektordner `/home/silberf1/public_html/pbar/` und ein Kontrolllog für jeden
Cronjob. Das Domain-Ziel muss `/home/silberf1/public_html/pbar/public/` sein.
Logs liegen unter `storage/logs/`, ausserhalb des Webroots. PHP muss dort schreiben
können. `>>` hängt die Ausgabe an; `2>&1` erfasst auch Fehler. Leere Logs allein
belegen keinen Erfolg: zusätzlich den Ausführungsstatus in my.cyon kontrollieren.

1. SHA-256-Prüfsummen in `artifacts/1.2.0/` prüfen. Datenbank exportieren,
   `.env`, `storage/` und bisherigen Programmordner separat sichern.
2. Auf Cyon den Scheduler-Cronjob pausieren und laufenden Import abwarten.
   Noch keinen API-Ninjas-Key eintragen. Diesen temporären my.cyon-Cronjob
   ausführen, Log und Ausführungsstatus kontrollieren und Cronjob entfernen:

   ```text
   /usr/bin/php83 /home/silberf1/public_html/pbar/artisan privatebar:maintenance on >> /home/silberf1/public_html/pbar/storage/logs/release-1.2.0-maintenance-on.log 2>&1
   ```

3. `privatebar-1.2.0-cyon-update.zip` in
   `/home/silberf1/public_html/pbar/` mit Überschreiben entpacken.
   Es enthält weder `.env` noch `storage/`. `vendor/` vollständig ersetzen.
   Alte PHP-Cachedateien in `bootstrap/cache/` entfernen, Verzeichnis erhalten.
4. Die folgenden vier Befehle **einzeln und in dieser Reihenfolge** als
   temporäre Cronjobs ausführen. Jeweils das zugehörige Log im Dateimanager
   und den Ausführungsstatus prüfen, dann den Cronjob entfernen, bevor der
   nächste angelegt wird. Bei Fehler stoppen; Wartung und pausierten Scheduler
   beibehalten. APP_KEY bleibt erhalten; Installationsskript nicht erneut ausführen.

   ```text
   /usr/bin/php83 /home/silberf1/public_html/pbar/artisan optimize:clear >> /home/silberf1/public_html/pbar/storage/logs/release-1.2.0-clear.log 2>&1
   ```

   ```text
   /usr/bin/php83 /home/silberf1/public_html/pbar/artisan migrate --force >> /home/silberf1/public_html/pbar/storage/logs/release-1.2.0-migrate.log 2>&1
   ```

   ```text
   /usr/bin/php83 /home/silberf1/public_html/pbar/artisan optimize >> /home/silberf1/public_html/pbar/storage/logs/release-1.2.0-optimize.log 2>&1
   ```

   ```text
   /usr/bin/php83 /home/silberf1/public_html/pbar/artisan privatebar:health >> /home/silberf1/public_html/pbar/storage/logs/release-1.2.0-health.log 2>&1
   ```

5. Wartung mit folgendem temporärem Cronjob beenden. Log und Ausführungsstatus
   prüfen, Cronjob entfernen, Anmeldung und vorhandene Rezepte prüfen.
   Scheduler zunächst pausiert lassen.

   ```text
   /usr/bin/php83 /home/silberf1/public_html/pbar/artisan privatebar:maintenance off >> /home/silberf1/public_html/pbar/storage/logs/release-1.2.0-maintenance-off.log 2>&1
   ```

6. Pi mit Tarball und Installer aus `artifacts/1.2.0/` aktualisieren:

   ```bash
   cd /home/pbar/Downloads
   sha256sum -c privatebar-1.2.0-pi-installation.tar.gz.sha256
   sudo bash install-pi-release.sh /home/pbar/Downloads/privatebar-1.2.0-pi-installation.tar.gz
   ```

   Bei Fehler stoppen. Version 1.2.0, Verbindungstest, Synchronisation und
   bestehende Kioskfunktionen prüfen.
7. Erst jetzt `/home/silberf1/public_html/pbar/.env` um die obigen
   API-Ninjas-Werte ergänzen. Die folgenden Befehle wieder einzeln als
   temporäre Cronjobs ausführen, jeweils Log und Ausführungsstatus prüfen
   und Cronjob entfernen:

   ```text
   /usr/bin/php83 /home/silberf1/public_html/pbar/artisan optimize:clear >> /home/silberf1/public_html/pbar/storage/logs/release-1.2.0-api-clear.log 2>&1
   ```

   ```text
   /usr/bin/php83 /home/silberf1/public_html/pbar/artisan optimize >> /home/silberf1/public_html/pbar/storage/logs/release-1.2.0-api-optimize.log 2>&1
   ```

8. Scheduler wieder jede Minute aktivieren, ebenfalls mit Kontrolllog:

   ```text
   /usr/bin/php83 /home/silberf1/public_html/pbar/artisan schedule:run >> /home/silberf1/public_html/pbar/storage/logs/cron-scheduler.log 2>&1
   ```

   Im Menü Rezeptimport einschalten. Zum nächsten konfigurierten Importzeitpunkt
   Quellhinweise, Mengen, Übersetzung, wiederholten Import und Offline-Rezepte
   am Pi prüfen. Das Schedulerlog regelmässig im Dateimanager kontrollieren und
   nach Sicherung leeren; die Laravel-Logrotation rotiert dieses Umleitungslog
   nicht automatisch.

Das Sync-Schema bleibt 1. Neue Pi-Clients melden `api_ninjas_sources=true`.
Für ältere Clients entfernt Cyon nur die neue Quellenmetadatenkennung aus der
Antwort; Rezepte und Cursor bleiben erhalten. Alte Quellen bleiben unverändert.
Die Reihenfolge oben stellt vollständige neue Quellmetadaten am Pi sicher.

## Freigabestand

Die Pakete sind manuell installierbare Prüfkandidaten, keine signierte
Produktionsfreigabe. Echte Cyon-/Pi-/API-Key-Abnahme bleibt erforderlich.
`deploy/release-approval.json` bleibt gesperrt. Keine Bereitstellung erfolgt.
