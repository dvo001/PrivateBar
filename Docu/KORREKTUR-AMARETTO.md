# Amaretto auf einer bestehenden Cyon-Installation ergänzen

Das Paket `artifacts/privatebar-amaretto-korrektur.zip` enthält die beiden
Seeder-Dateien und diese Anleitung. Es enthält keine .env, Datenbank oder
Bibliotheken und ändert keine Versionsnummer. Es ist kein neues Release.

1. ZIP lokal entpacken. Die zwei PHP-Dateien aus `database/seeders/` nach
   `/home/silberf1/public_html/pbar/database/seeders/` hochladen.
   Die bestehende `DatabaseSeeder.php` vorher lokal sichern.
2. Auf Cyon einen vorübergehenden Cronjob anlegen; in allen fünf Zeitfeldern `*`:

   ```sh
   /usr/bin/php83 /home/silberf1/public_html/pbar/artisan db:seed --class=AmarettoSeeder --force > /home/silberf1/public_html/pbar/storage/logs/amaretto-setup.log 2>&1
   ```

3. Nach ein bis zwei Minuten `storage/logs/amaretto-setup.log` prüfen.
   Erwartet wird `INFO Seeding database.` ohne Fehlermeldung. Unter Einstellungen
   → Cocktailzutaten & Synonyme muss «Amaretto» auswählbar sein, sofern eine
   schon vorhandene Zutat nicht zuvor privat umbenannt wurde.
4. Den temporären Cronjob entfernen. Den regulären `schedule:run`-Cronjob behalten.
5. Barcode `8001110016303` erneut scannen. Amaretto wird vorgeschlagen;
   Flaschenangaben inklusive 28 % vol vor dem Speichern prüfen.

Nur diesen gezielten Seeder ausführen. Der wiederholte Aufruf ist idempotent.
Bestehende Produkte und Rezepte werden nicht geändert. Die Ergänzung wird über
die eingerichtete Synchronisation an den Pi übertragen; auf dem Pi ist dafür
kein eigener Seeder-Aufruf nötig. Konfigurationscache, Migrationen oder ein
Neustart sind für diese Datenkorrektur nicht erforderlich.

Voraussetzung ist die vorhandene PrivateBar-1.0.x-Installation mit ihrem normalen
Composer-PSR-4-Autoloader. Die Datei `AmarettoSeeder.php` muss vollständig
hochgeladen sein, bevor der Cronjob startet.
