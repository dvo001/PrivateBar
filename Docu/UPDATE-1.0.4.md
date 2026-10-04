# PrivateBar 1.0.4 – Produkthinweise bei Rezeptzutaten

Version 1.0.4 zeigt im Rezeptdetail unter vorhandenen Zutaten die konkreten
Produkte aus der eigenen Bar. Mehrere passende Flaschen werden als Alternativen
angezeigt. Bei einer Ersatzzutat wird das Produkt der tatsächlich verwendeten
Ersatzzutat ausgegeben. Entfernte Flaschen und fehlende Produkte erscheinen
nicht. Marken- und Produktnamen werden weiterhin escaped ausgegeben.

Die Paketdateien liegen unter `artifacts/1.0.4/` und zusätzlich unter
`/mnt/d/Download/PrivateBar-1.0.4/`. Es gibt keine neue Datenbankmigration.

## Bestehende Cyon-Installation aktualisieren

Den Scheduler während des Dateiwechsels pausieren und eine wiederherstellbare
Kopie der bisherigen Dateien bereithalten.

1. `privatebar-1.0.4-cyon-update.zip` nach
   `/home/silberf1/public_html/pbar` hochladen und direkt dort entpacken.
2. Im Dateimanager unter `bootstrap/cache/` die generierten Dateien
   `config.php`, `packages.php` und `services.php` löschen. `.gitignore` bleibt
   bestehen. Damit werden keine Entwicklungs-Provider wie Collision aus einem
   alten Cache weiterverwendet.
3. Den Cyon-Cronjob einmal ausführen:

   ```sh
   /usr/bin/php83 /home/silberf1/public_html/pbar/artisan optimize > /home/silberf1/public_html/pbar/storage/logs/update-1.0.4-cache.log 2>&1
   ```

4. Den Scheduler wieder aktivieren und danach auf dem Pi synchronisieren.

Die bestehende `.env`, der `APP_KEY`, Datenbankzugänge und Gerätezugänge bleiben
unverändert. Ein APP_KEY-Wechsel ist kein Bestandteil dieses Updates.

## Raspberry Pi

Das Archiv `privatebar-1.0.4-pi-installation.tar.gz` und
`install-pi-release.sh` auf den Pi kopieren. Der Releasewechsel erfolgt mit:

```sh
sudo bash install-pi-release.sh ./privatebar-1.0.4-pi-installation.tar.gz
```

Das Skript legt den Release unter `/srv/privatebar/releases/1.0.4` an, verwendet
die bestehende gemeinsame `.env` und `storage`, entfernt alte generierte
Laravel-Caches vor `optimize`, führt `privatebar:health` aus und schaltet erst
danach `/srv/privatebar/current` atomar um. Der vorher aktive Tick-Timer wird
wieder gestartet. Jeder Lauf wird unter
`/srv/privatebar/shared/storage/logs/pi-release-1.0.4-*.log` protokolliert.

Ein bereits vorhandener Ziel-Release wird absichtlich nicht überschrieben.
Fehlgeschlagene temporäre Installationen dürfen erst entfernt werden, wenn
`current` auf einen anderen Release zeigt.

## Prüfsummen und Freigabe

`SHA256SUMS` enthält die Prüfsummen der drei Pakete. Die Archive enthalten keine
`.env`, Datenbanken, Logs, Tests oder privaten Schlüssel. Die echte Cyon-/Pi-
Abnahme bleibt offen; `deploy/release-approval.json` bleibt deshalb gesperrt.
