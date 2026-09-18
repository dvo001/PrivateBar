# Zutatenkorrektur für die Exporte vom 8. September 2026

Das Paket enthält ein CLI-Skript, seine Korrekturklasse und ein lesbares
JSON-Manifest. Es ist auf die geprüften IDs und Ausgangszuordnungen zugeschnitten.
Es führt keine Live-Änderung durch, solange `--apply` fehlt.

## Umfang

- 19 Kategorien: fünf Ergänzungen, vier Umbenennungen; vorhandene Kategorie-IDs bleiben erhalten.
- 119 Zutaten: Kategorie korrigieren, darunter drei Umbenennungen (Grapa → Grappa,
  Yuzu → Yuzusirup, Wild Strawberry → Erdbeer-Gin).
- Fünf neue Zutaten: Limoncello, Passionsfruchtlikör, Trojka Red, Trojka Green,
  Trojka Sun. Die drei unterschiedlichen Trojka-Liköre werden nicht als normaler
  Wodka oder als untereinander gleichwertig behandelt.
- Acht Produktzuordnungen: Studer Kirsch → Kirsch; Malibu → Kokoslikör;
  beide Limoncellos → Limoncello; Passoa → Passionsfruchtlikör;
  drei Trojka-Produkte → jeweilige Likörzutat.
- Optional: bei den 37 im Manifest genannten alkoholischen Zutaten
  `automatic` abschalten. Andere automatische Grundzutaten bleiben erhalten.

Produktnamen, Alkoholwerte, Bilder, Barcodes, bestehende Zutaten-IDs,
Rezeptverknüpfungen und tatsächlicher Barbestand bleiben erhalten. Keine Produkte
werden gelöscht oder als vorhanden hinzugefügt. Historische Namen der drei
umbenannten Zutaten werden als Synonyme erhalten; Konflikte führen zum Abbruch.
Weitere Synonymzusammenführungen und Ersatzregeln sind nicht enthalten, da die
entsprechenden Tabellen nicht exportiert wurden. Das Skript ändert nicht die
allgemeine Importlogik. Es ist eine gezielte Datenbereinigung.

Weitere offene Prüffälle werden bei jedem Lauf ausgegeben. Fehlende Alkoholwerte
werden nicht geschätzt. Für «Weitere Spirituosen» bleibt der Kategorienwert leer;
Brandy und Obstbrände erhalten 40 %, Bier und Cider 5 %, Milch/Alternativen sowie
alkoholfreie Alternativen 0 % als grobe Fallbackwerte, nicht als Flaschenwerte.

## Installation und Reihenfolge

Voraussetzung: bestehende PrivateBar-1.0.2-Installation mit `vendor/` und `.env`.
Die drei Dateien aus `tools/` mit unveränderter Ordnerstruktur in das jeweilige
Anwendungsverzeichnis hochladen. Sie gehören nicht nach `public/`.

**Kategorien werden in der aktuellen Anwendung nicht synchronisiert.** Deshalb:

1. Synchronisations-/Importzeitplan auf beiden Instanzen für den Vorgang pausieren
   (Cyon-`schedule:run`-Cronjob vorübergehend deaktivieren; Pi-Tick-Timer stoppen).
   Währenddessen keine Zutaten/Produkte bearbeiten oder manuell synchronisieren.
   Der Anwendungs-Wartungsmodus darf nicht aktiv sein: er sperrt auch das Journal.
2. Zuerst auf dem Pi ausschliesslich die Kategorien vorbereiten:

   ```sh
   /usr/bin/php8.3 /srv/privatebar/current/tools/correct-ingredients.php --categories-only
   /usr/bin/php8.3 /srv/privatebar/current/tools/correct-ingredients.php --categories-only --apply
   ```

3. Auf Cyon Vorschau ausführen und Log lesen. Bei den unveränderten Exportdaten
   sind 141 Änderungen zu erwarten (9 Kategorien, 5 neue Zutaten,
   119 Zutatenkorrekturen, 8 Produktzuordnungen).
4. Auf Cyon mit `--apply` anwenden. Zutaten werden vor den Produkten ins
   normale Sync-Journal geschrieben; Kategorieänderungen erhalten lokale Audit-Einträge.
5. Temporären Korrektur-Cronjob entfernen, normale Zeitpläne wieder aktivieren
   und Cyon → Pi synchronisieren. Ohne vorhandenen Pi entfällt dessen Vorbereitung;
   vor späterer Erstsynchronisation dort den Kategorienlauf nachholen.

## Cyon ohne SSH

Im my.cyon-Dateimanager nach `/home/silberf1/public_html/pbar/` hochladen.
Falls die Installation inzwischen anders liegt, alle Pfade entsprechend anpassen.
In my.cyon einen temporären Cronjob mit allen fünf Zeitfeldern `*` anlegen:

```sh
/usr/bin/php83 /home/silberf1/public_html/pbar/tools/correct-ingredients.php > /home/silberf1/public_html/pbar/storage/logs/ingredient-correction-preview.log 2>&1
```

Nach dem ersten Lauf den temporären Cronjob deaktivieren und das Log im
Dateimanager öffnen. Erwartet: `VORSCHAU – keine Daten gespeichert` und die
Liste der Änderungen. Anschliessend zum Anwenden den Befehl ersetzen:

```sh
/usr/bin/php83 /home/silberf1/public_html/pbar/tools/correct-ingredients.php --apply > /home/silberf1/public_html/pbar/storage/logs/ingredient-correction-apply.log 2>&1
```

Nach dem ersten Lauf Log prüfen und Cronjob entfernen. Erwartet:
`ANGEWENDET: 141 Änderungen`; bei Wiederholung `ANGEWENDET: 0 Änderungen`.
Da `>` das Log überschreibt, den ersten Bericht lokal speichern.

Bei `ABBRUCH` nicht mit SQL nachhelfen: Der komplette Lauf wurde zurückgerollt.
Der benannte Datensatz muss mit dem aktuellen Stand abgeglichen werden.
Ein unverändertes Manifest ist wiederholbar; keine doppelten Journalereignisse.
Die Dateisperre verhindert parallele Aufrufe dieses Skripts, ersetzt aber nicht
das Pausieren anderer Schreibvorgänge. Eine Vorschau führt ihre Probeänderungen
innerhalb einer zurückgerollten Transaktion aus und kann kurz Zeilensperren halten.

## Automatisch vorhandene Spirituosen – optional

Nur wenn die 37 im Manifest aufgeführten Zutaten künftig ausschliesslich über
vorhandene Flaschen verfügbar sein sollen, auf Cyon zusätzlich
`--disable-automatic-alcohol` verwenden, zuerst ohne, dann mit `--apply`.
Nach der regulären Korrektur sind das 37 zusätzliche Änderungen.
Es werden keine Flaschen automatisch angelegt. Dadurch können bisher als machbar
angezeigte Rezepte Zutaten vermissen; das ist die beabsichtigte Bestandskorrektur.

## Grenzen

Das Skript wird vorbereitet und lokal getestet, nicht auf Cyon/Pi ausgeführt.
Die Exportdateien enthalten keinen Barbestand, keine Synonyme, keine Ersatzregeln
und keine Rezepte. Zusätzliche Konflikte in diesen Live-Daten können einen
kontrollierten Abbruch verursachen. Eine Wiederherstellung nach einem bereits
bestätigten Lauf erfolgt über die vorhandenen Hosting-Backups; das Skript erstellt
kein Datenbankbackup und hat keinen pauschalen Rückwärtslauf.
