# PrivateBar 1.0.3 – Kategorien im Cyon-Pi-Abgleich

Version 1.0.3 enthält den Kategorienabgleich von Cyon zum Pi. Kategorien werden
auf Cyon verwaltet, im Sync als Stammdaten mitgeliefert und vor den Zutaten
angewendet. Damit scheitert ein bestehender Zutaten-Event nicht mehr an einer
auf dem Pi fehlenden Kategorie. Der Pi kann keine Kategorieänderungen zu Cyon
hochladen. Zusätzlich ist die fehlerhafte Regex-Regel im Medienabgleich behoben.

Die Paketdateien liegen unter `artifacts/1.0.3/` und zusätzlich unter
`/mnt/d/Download/`.

## Bestehende Cyon-Installation aktualisieren

Während des Dateiwechsels den normalen `schedule:run`-Cronjob pausieren. Eine
wiederherstellbare Kopie der bisherigen Dateien und der Cyon-Backupstand müssen
vorhanden sein. Die bestehende `.env`, der `APP_KEY`, Datenbankzugänge und
Gerätezugänge bleiben unverändert.

1. `privatebar-1.0.3-cyon-update.zip` nach
   `/home/silberf1/public_html/pbar` hochladen und direkt dort entpacken. Die
   enthaltenen Verzeichnisse gehören direkt in den Projektordner.
2. Caches neu bauen:

   ```sh
   /usr/bin/php83 /home/silberf1/public_html/pbar/artisan optimize > /home/silberf1/public_html/pbar/storage/logs/update-1.0.3-cache.log 2>&1
   ```

3. Den Scheduler wieder aktivieren. Danach einmal auf dem Pi ausführen:

   ```sh
   sudo -u privatebar /usr/bin/php8.3 /srv/privatebar/current/artisan privatebar:sync -v
   ```

4. Im Pi-Log muss der Lauf mit dem Zustand `Aktuell` enden. Die Kategorie
   `category_id` der bisher blockierenden Zutat muss auf dem Pi vorhanden sein.

Das Update benötigt keine Migration und keinen neuen Composer-Lauf. Es ist ein
manuell einzuspielendes Paket, kein signaturgeprüftes Update für den lokalen
Updateknopf. Die Freigabefelder für die echte Cyon-/Pi-Abnahme bleiben `false`.

## Raspberry Pi

Das Archiv `privatebar-1.0.3-pi-installation.tar.gz` und das Skript
`deploy/pi/install-release.sh` auf den Pi kopieren. In der Downloadmappe liegt
die separate Kopie als `install-pi-release.sh`. Das Skript kann zum Beispiel
direkt aus dem Verzeichnis mit den beiden Dateien gestartet werden:

```sh
sudo bash install-release.sh ./privatebar-1.0.3-pi-installation.tar.gz
```

Es prüft den Archivnamen, optional die benachbarte `.sha256`-Datei und die
Archivpfade. Danach wird der neue Release unter
`/srv/privatebar/releases/1.0.3` vorbereitet. Die bestehende
`/srv/privatebar/shared/.env` und `shared/storage` bleiben erhalten. Während
`artisan optimize` und `artisan privatebar:health` läuft der Tick-Timer nicht.
Erst wenn beide Befehle erfolgreich sind, wird `/srv/privatebar/current`
atomar umgeschaltet und der vorher aktive Timer wieder gestartet. Jeder Lauf
schreibt zusätzlich nach `shared/storage/logs/pi-release-1.0.3-*.log`.

Das Skript bricht ab, wenn der Ziel-Release bereits existiert oder die
gemeinsame Konfiguration fehlt. Ein fehlgeschlagener Lauf lässt den bisherigen
`current`-Release aktiv und entfernt nur sein eigenes temporäres Verzeichnis.

## Prüfsummen

`SHA256SUMS` enthält die Prüfsummen der drei Pakete. Archive enthalten keine
`.env`, Datenbanken, Logs oder privaten Schlüssel.
