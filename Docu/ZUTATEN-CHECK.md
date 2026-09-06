# Prüfung der Cocktail-Grundliste

Stand: 6. September 2026. Geprüft wurde der mitgelieferte Quellstand v1.0.1
mit 27 Zutaten und 56 eindeutigen Namen/Synonymen. Die echte Cyon-Datenbank
wurde nicht ausgelesen; durch Rezeptimporte können dort zusätzliche Einträge stehen.
Die separat vorbereitete Amaretto-Korrektur erweitert neue Installationen auf 28 Zutaten.

## Ergebnis

Die Grundliste deckt die acht Startrezepte ab, reicht aber für einen allgemeinen
Flaschenbestand nicht aus. Der Import legt unbekannte Zutaten unter dem
Originalnamen in «Weitere Zutaten» an. Dadurch kann eine Zutat erst nach einem
Rezeptimport auswählbar sein oder mehrfach unter verschiedenen Namen erscheinen.
Die Zutatenverwaltung konnte in 1.0.1 nur bestehende Einträge bearbeiten;
1.0.2 erlaubt auch neue Zutaten und das Entfernen von Synonymen.

Die nachfolgenden Kataloglücken sind mit Version 1.0.2 umgesetzt. Die neue
Grundliste umfasst 134 konkrete Zutaten und 14 allgemeine Bereichseinträge.
Die unten dokumentierten Zahlen beschreiben die ursprüngliche Prüfung von 1.0.1.
Installation und Umgang mit bestehenden Daten: [UPDATE-1.0.2.md](UPDATE-1.0.2.md).

## Fehlende eigenständige Zutaten

| Bereich | In der Grundliste fehlende Beispiele |
| --- | --- |
| Liköre und Aperitifs | Amaretto, Aperol, Kaffeelikör, Irish Cream, Blue Curaçao, Pfirsichlikör, Kokoslikör, Crème de Cassis, Holunderblütenlikör, Haselnusslikör, Maraschinolikör |
| Weitere Spirituosen | Brandy/Cognac, Cachaça, Pisco, Mezcal, Absinth |
| Wein und Wermut | Trockener Wermut, Prosecco/Schaumwein; süsser weisser Wermut muss davon unterschieden werden |
| Bitter | Orangenbitter zusätzlich zu Angostura |
| Säfte | Cranberrysaft, Grapefruitsaft, Apfelsaft, Tomatensaft, Maracujasaft |
| Sirupe | Mandelsirup/Orgeat, Agavensirup, Honigsirup, Holunderblütensirup |
| Mixer | Ginger Beer, Cola, Zitronenlimonade, Kokoswasser |
| Küche und frische Zutaten | Milch, Rahm, Kokosmilch, Cream of Coconut, Eiweiss, Honig, brauner Zucker, Zimt, Ingwer, Erdbeeren, Gurke |

Das ist eine priorisierte Auswahl für eine Hausbar, kein vollständiger Katalog
aller Getränke und Lebensmittel. Neue Zutaten sollen nicht automatisch als
vorhanden gelten. Aktuell sind nur Wasser, Eis, Zucker und Salz so vorbelegt.

## Fehlende Synonyme vorhandener Zutaten

| Vorhandene Zutat | Bisher nicht erkannte Schreibweisen aus OpenDrinks |
| --- | --- |
| Minze | mint leaves |
| Limettensaft | fresh lime juice |
| Zitronensaft | fresh lemon juice, freshly squeezed lemon juice |
| Eis | crushed ice, ice cube |
| Wasser | cold water, boiling water |
| Zucker | white sugar, granulated sugar |
| Grenadine | grenadine syrup |

Diese Namen müssen vor dem Erzeugen neuer Importzutaten normalisiert werden.
Bereits entstandene Dubletten brauchen eine kontrollierte Zusammenführung mit
Erhalt der Rezept-, Produkt-, Einkaufs- und Synchronisationsbezüge. Nur zusätzliche
Synonyme einzutragen bereinigt bestehende doppelte Datensätze nicht.

Nicht blind gleichsetzen: Ginger Ale/Ginger Beer, Kokosmilch/Cream of Coconut,
Amaretto/Mandelsirup, Holunderblütenlikör/-sirup, trockener/süsser Wermut sowie
Orangenbitter/Orangenlikör erfüllen unterschiedliche Rezeptanforderungen.
Auch das bestehende gemeinsame Mapping von Bourbon, Scotch und Whiskey auf
«Whisky» ist für präzise Rezeptzuordnungen zu grob; eine Aufteilung wäre eine
eigene Datenbereinigung. Ein unspezifisches «rum» legt keinen weissen Rum fest.

## Nachweise und Grenzen

- Grundliste: `database/seeders/DatabaseSeeder.php` im Git-Stand v1.0.1.
- Exakte Namensauflösung: `app/Domain/Recipes/Importer.php::ingredient()`.
- Automatischer Scanvorschlag: `app/Http/Controllers/BarController.php::suggest()`.
  Dieser prüfte in 1.0.1 Produktname und Kategorie-Tags. In 1.0.2 werden
  zuerst der Produktname und anschliessend kuratierte Markensynonyme geprüft. Ein Eintrag im Zutatenkatalog allein garantiert deshalb noch
  keinen automatischen Vorschlag für jede Marke.
- [TheCocktailDB API](https://www.thecocktaildb.com/api.php): Der dokumentierte
  Endpunkt `list.php?i=list` lieferte beim Test 100 Zutaten. Das ist keine
  nachgewiesen vollständige Liste; ergänzend wurden die Rezeptdaten unten geprüft.
- [OpenDrinks-Rezeptdaten](https://github.com/alfg/opendrinks/tree/f446f0e9356b9b43155d207b4f7c5214d9da91ab/src/recipes),
  Commit `f446f0e9356b9b43155d207b4f7c5214d9da91ab`: 756 JSON-Rezepte,
  1618 verschiedene kleingeschriebene, getrimmte Zutatenbezeichnungen einschliesslich
  eines leeren Namens. 1581 nicht leere Bezeichnungen treffen kein vorhandenes
  Synonym. Das sind ausdrücklich nicht 1581 fehlende eigenständige Zutaten:
  Die Daten enthalten Varianten, ausgeschriebene Mengen und Zubereitungsangaben.
  Häufige Treffer ohne Zuordnung: Milch (38 Nennungen), Honig (33), mint leaves
  (27), fresh lime juice (21), Ginger Beer und trockener Wermut (je 13).

## Korrektur für Disaronno

Der [OFF-Eintrag 8001110016303](https://world.openfoodfacts.org/api/v2/product/8001110016303.json)
liefert Amaretto, die Marke Disaronno, den deutschen Produktnamen «Disarono»
und 28 % vol. `AmarettoSeeder` ergänzt Amaretto und die Synonyme amaretto,
disaronno und disarono. Vorhandene Zutaten-IDs, Kategorien, Synonymzuordnungen
und private Korrekturen werden bewahrt. Geänderte Daten werden beim gezielten
Aufruf für die Synchronisation protokolliert. Wiederholungen erzeugen keine
weiteren Einträge oder Ereignisse.

Die 28 % vol gehören zur konkreten Flasche. Sie ersetzen nicht den Kategorienwert
aller Liköre. Installationsanleitung: [KORREKTUR-AMARETTO.md](KORREKTUR-AMARETTO.md).
