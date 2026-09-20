# Kategorien synchronisieren

Kategorien werden auf Cyon verwaltet. Der Pi empfängt sie beim normalen
Abgleich und speichert sie lokal. Der Pi sendet keine Kategorieänderungen zurück.
Die Sync-Antwort enthält den vollständigen Kategorienbestand zusätzlich zu den
inkrementellen Ereignissen. Dadurch werden auch bereits wartende Zutaten mit
einer neu angelegten Kategorie sicher übernommen.

## Bestehendes Cyon aktualisieren

Die Dateien `app/Domain/Sync/Projector.php`, `app/Domain/Sync/SyncServer.php`,
`app/Domain/Sync/SyncClient.php` und `routes/console.php` aus dem Releasepaket
über den my.cyon-Dateimanager in die entsprechenden Verzeichnisse übertragen.
Danach den normalen Cronjob laufen lassen:

```sh
/usr/bin/php83 /home/silberf1/public_html/pbar/artisan schedule:run
```

Falls Kategorien oder Zutaten ausserhalb der Anwendung direkt geändert wurden,
kann einmalig zusätzlich der bestehende Zustandsabgleich gestartet werden:

```sh
/usr/bin/php83 /home/silberf1/public_html/pbar/artisan privatebar:publish-state
```

Der Befehl veröffentlicht den vollständigen Cyon-Zustand in der bestehenden
Reihenfolge. Der Pi erhält die Kategorien auch ohne diesen Befehl über das
zusätzliche Kategorienfeld jeder Sync-Antwort.

Auf dem Pi genügt danach:

```sh
sudo -u privatebar /usr/bin/php8.3 /srv/privatebar/current/artisan privatebar:sync -v
```

Ein erneuter Lauf ist sicher und erzeugt keine doppelten Kategorieeinträge.
