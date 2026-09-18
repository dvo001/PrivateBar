# Rezeptmengen korrigieren

Stand: 18. September 2026.

Der bisherige Import deutete Zahlen ohne Einheit als Stück und erkannte unter
anderem `1-1/2 oz`, `.5 oz`, `dl` und ausgeschriebene metrische Einheiten nicht
korrekt. Der Parser verarbeitet diese Schreibweisen jetzt vollständig. Flüssige
Volumen werden in cl, Kilogramm in g normalisiert. TL und EL verwenden weiterhin
5 beziehungsweise 15 ml; oz bezeichnet wie bisher die US-Flüssigunze.

Einheitenlose Mengen bleiben als Originalangabe erhalten. Brüche wie `1/2` und
`1/3` erscheinen als `½` und `⅓`; explizite Teile als beispielsweise `⅓ Anteil`.
Flüssigkeitsmengen erscheinen mit höchstens einer Nachkommastelle, zum Beispiel
`2.2 cl` statt `2.218 cl`. Werte unter `0.1 cl` behalten bis zu drei Nachkommastellen,
damit sie nicht als null erscheinen. Intern bleibt die Präzision erhalten.
Eindeutige Volumenbereiche wie `2 -3 oz` erscheinen als `5.9–8.9 cl`; die
Datenbank erhält dafür keine erfundene einzelne Menge.

Eine ausdrücklich gelieferte Angabe `33%` bleibt `33 %`: Sie lässt sich nicht
verlustfrei als Drittel interpretieren. Andere Bereiche, ungültige Brüche, Zusätze und
uneindeutige Einheiten wie cup oder shot bleiben vollständig im Original erhalten.
Für diese Angaben wird kein Volumen für die Alkoholschätzung erfunden.

Beim [Fuzzy Asshole](https://www.thecocktaildb.com/drink/15743-fuzzy-asshole-cocktail)
enthält die am 18. September geprüfte Originalquelle zweimal `1/2` ohne Einheit.
Die Zubereitung verlangt je eine halbe Tasse Kaffee und Pfirsichlikör. Deshalb
erscheinen nach Korrektur `½ Kaffee` und `½ Pfirsichlikör`, nicht `0.5 Stück`.
Eine feste Menge in cl ist ohne Tassengrösse nicht ableitbar.

## Bestehende Importrezepte

Ein unverändertes Quellrezept wird vom normalen Import übersprungen. Deshalb
müssen bestehende Mengen einmal aus `original_measure` neu normalisiert werden.
Eigene Rezepte und Haushaltskopien werden dabei nicht verändert. Rezept-IDs,
Originalangaben und manuelle Übersetzungen bleiben erhalten. Jede Änderung erhält
eine neue Rezeptversion und ein Sync-Ereignis; Wiederholung ohne weitere
Änderungen erzeugt keine Ereignisse. Die Verarbeitung erfolgt pro Rezept
transaktional in Batches von 100 Rezepten. Wartungsmodus und Pi-Modus sperren sie.

Nach Übertragung der geänderten Anwendungsdateien zuerst auf Cyon ausführen:

```sh
/usr/bin/php83 /absoluter/pfad/zu/privatebar/artisan privatebar:normalize-measures
```

Dieser Aufruf zeigt nur die Anzahl betroffener Rezepte. Zum Anwenden:

```sh
/usr/bin/php83 /absoluter/pfad/zu/privatebar/artisan privatebar:normalize-measures --apply
```

Ohne SSH lassen sich die Befehle als temporäre my.cyon-Cronjobs ausführen;
Ausgabe prüfen und Cronjob anschliessend entfernen. Danach auch die geänderten
Anwendungsdateien auf den Pi übertragen und den normalen Abgleich ausführen.
Bestehende Produktions-View-Caches nach dem Codeupdate mit `artisan view:clear`
und `artisan view:cache` erneuern. Die Anleitung beschreibt die Bereitstellung;
in dieser Sitzung wurde keine Live-Datenbank verändert.
