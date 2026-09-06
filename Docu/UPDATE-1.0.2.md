# PrivateBar 1.0.2 – Zutaten und Flaschenzuordnung

Version 1.0.2 ergänzt alle im Zutatencheck benannten Kataloglücken. Eine neue
Installation hat 134 konkrete Zutaten und 14 allgemeine Bereichseinträge mit
insgesamt 355 Namen/Synonymen. Nur Wasser, Eis, Zucker und Salz sind automatisch
vorhanden. Bestehende Installationen können zusätzliche importierte oder private
Zutaten enthalten.

Beim Scannen und manuellen Erfassen gibt es eine Suche, Bereichsfilter und
gruppierte Auswahl. «Noch nicht zugeordnet» erlaubt die vorläufige Speicherung
einer Flasche, erfüllt aber keine bestimmte Cocktailzutat. Unter «Meine Bar» →
«Flasche bearbeiten» lässt sich die Zuordnung später ändern. Unter Einstellungen
→ Cocktailzutaten & Synonyme können Mitglieder Zutaten hinzufügen, umbenennen,
Bereiche ändern sowie Synonyme ergänzen und entfernen.

## Pakete

Die lokalen Dateien liegen unter `artifacts/1.0.2/`.

- `privatebar-1.0.2-cyon-update.zip`: Programmänderungen für Cyon ab 1.0.1,
  ohne .env, private Daten und vendor. Auf Cyon 1.0.0 zuerst das dokumentierte
  Mailupdate 1.0.1 durchführen.
- `privatebar-1.0.2-cyon-installation.zip`: vollständige Erstinstallation mit
  Produktionsabhängigkeiten und Cyon-Umgebungsvorlage.
- `privatebar-1.0.2-pi-installation.tar.gz`: vollständige Anwendung für die
  Pi-Erstinstallation oder einen manuellen Wechsel des Releaseverzeichnisses.

Zu jedem Paket liegt eine SHA-256-Prüfsumme bei. Die Pakete sind nicht für den
signaturgeprüften Updateknopf bestimmt. Der Git-Tag kennzeichnet den Quellstand;
die Prüfung auf echter Cyon-/Pi-Hardware bleibt getrennt davon erforderlich.

## Cyon ohne Shell aktualisieren

Die bestehende .env, APP_KEY, Mail-, Datenbank- und Gerätezugänge erhalten.
Eine wiederherstellbare Kopie der bisherigen Programmdateien und den verfügbaren
Hosting-Sicherungsstand gemäss DEPLOYMENT.md prüfen. Den normalen
`schedule:run`-Cronjob während des Dateiupdates pausieren.

Die folgenden temporären Cronjobs **nacheinander** ausführen, jeweils mit `*`
in allen fünf Zeitfeldern. Nach Erfolg den jeweiligen temporären Job entfernen.
Den nächsten Schritt erst durchführen, wenn das vorherige Log keinen Fehler zeigt.

1. Laravel für den Dateiwechsel in Wartung setzen:

   ```sh
   /usr/bin/php83 /home/silberf1/public_html/pbar/artisan down > /home/silberf1/public_html/pbar/storage/logs/update-1.0.2-down.log 2>&1
   ```

2. `privatebar-1.0.2-cyon-update.zip` nach
   `/home/silberf1/public_html/pbar` entpacken. Die enthaltenen Ordner app,
   config, database, public, resources und routes gehören direkt in diesen
   Projektordner. Es wird kein zusätzlicher Unterordner angelegt.

3. Den Zutatenkatalog gezielt ergänzen:

   ```sh
   /usr/bin/php83 /home/silberf1/public_html/pbar/artisan db:seed --class=IngredientCatalogSeeder --force > /home/silberf1/public_html/pbar/storage/logs/update-1.0.2-zutaten.log 2>&1
   ```

   Das Log nennt die Anzahl ergänzter/geänderter Zutaten. Ein erneuter Lauf ist
   erlaubt und meldet ohne zwischenzeitliche Änderungen 0. Private Konflikte
   werden im Log genannt und nicht still überschrieben. Der alte Amaretto-Patch
   ist nicht zusätzlich nötig. Nicht den gesamten DatabaseSeeder erneut ausführen.

4. Konfigurations-, Routen- und View-Caches erneuern:

   ```sh
   /usr/bin/php83 /home/silberf1/public_html/pbar/artisan optimize > /home/silberf1/public_html/pbar/storage/logs/update-1.0.2-cache.log 2>&1
   ```

5. Anwendung wieder öffnen:

   ```sh
   /usr/bin/php83 /home/silberf1/public_html/pbar/artisan up > /home/silberf1/public_html/pbar/storage/logs/update-1.0.2-up.log 2>&1
   ```

Den normalen Scheduler wieder minütlich aktivieren. Die temporären Updatejobs
entfernen. Im Browser neu laden und unter Einstellungen nach Amaretto, Aperol
und Ginger Beer suchen. Auf dem Smartphone eine Flasche zunächst allgemein,
danach konkret zuordnen. Eine Zutat samt Synonym bearbeiten und den Abgleich
zum Pi prüfen. Für diesen Versionswechsel gibt es keine neue Schemamigration
und keine geänderten Composer-Abhängigkeiten.

## Pi nach Cyon aktualisieren

Die Ersteinrichtung steht in INSTALLATION-PI.md. Bei einem bestehenden Pi das
vollständige Paket in ein neues Verzeichnis `/srv/privatebar/releases/1.0.2`
entpacken. Die bestehende `/srv/privatebar/shared/.env` und shared/storage
weiterverwenden; niemals die Umgebungsvorlage über die eingerichtete .env kopieren.
Die mitgelieferte leere storage-Struktur im neuen Release zuerst an einen
anderen Ort verschieben, dann die vorhandene shared/storage dort verlinken.

Den Timer `privatebar-tick.timer` vor dem Wechsel stoppen und einen bereits
laufenden `privatebar-tick.service` beenden lassen. Prüfen, dass keine
anderen Hintergrundhelfer gerade schreiben. Mit PHP 8.3 im neuen Verzeichnis
als Benutzer privatebar `artisan optimize` und `artisan privatebar:health`
ausführen. Danach den Symlink `/srv/privatebar/current` über einen temporären
Symlink atomar auf das geprüfte neue Verzeichnis umstellen und PHP-FPM neu laden.
Den Timer wieder starten. Benutzer, Pfade und Service-Namen müssen zur
bestehenden Installation passen; PHP-FPM-Pool und Nginx-Konfiguration bleiben
auf den eingerichteten Socket abgestimmt.

Bei eingerichtetem Geräteabgleich kommen Katalog und Synonyme von Cyon.
Den Seeder auf dem Pi daher nicht zusätzlich starten. Neue Synonymlöschungen
und die Zusammenfassung alter Importnamen vollständig erst prüfen, wenn beide
Instanzen 1.0.2 verwenden. Die API bleibt unter `/api/v1`; ältere Clients
können weiterhin synchronisieren, wenden Synonymlöschungen aber noch nicht an.

## Bestehende Daten und Rückkehr zur vorherigen Version

Historische Zutaten-IDs und ihre Rezept-, Produkt- und Einkaufsbezüge werden
nicht gelöscht. Ein eindeutiger alter Importname wird über den gemeinsamen
Synonymkatalog auf die Hauptzutat abgebildet: gleiche Machbarkeit und
Alkoholberechnung, keine zweite Auswahl im Flaschenformular. Nicht eindeutige
oder privat bearbeitete Zuordnungen bleiben erhalten.

Bourbon und Scotch sind nun getrennte Zutaten. Alte Flaschen oder Rezepte,
die bereits ohne weitere Unterscheidung als «Whisky» gespeichert wurden,
bleiben so erhalten; eine genauere Flaschenzuordnung erfolgt über Bearbeiten.
Eine pauschale Reklassifizierung würde bestehende Angaben verfälschen.

Bei einem Programmfehler kann das vorherige Programmverzeichnis wieder benutzt
werden. Ergänzte Zutaten bleiben gültige Datensätze im bisherigen Schema.
Die vorige Version stellt allerdings die neue Auswahl und Aliasauflösung
nicht bereit. Kein Zurückspielen einer Datenbank ohne den in DEPLOYMENT.md
beschriebenen Wiederherstellungsablauf.
