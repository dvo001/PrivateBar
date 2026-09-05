# Architektur und technische Entscheidungen

## Aufbau

`app/Domain` enthält die Fachbereiche Bar, Rezepte, Zugang, Einstellungen,
Synchronisation, Fotos und Updates. Controller validieren Eingaben und rufen die
Fachdienste auf. Anbieteradapter in `app/Infrastructure/Providers` liefern interne
Arrays mit ausdrücklich gewählten Feldern. Blade rendert die Oberfläche auf dem
Server; JavaScript ergänzt Kamera, Zutatenzeilen und Fotorahmen.

Die Oberfläche, Symbole, Illustrationen, Schriftdateien und der bei Bedarf geladene
ZXing-Fallback werden lokal ausgeliefert. `tools/build.php` erzeugt die statischen
Assets und einen SHA-256-Manifestnachweis. Es gibt keinen Frontend-Build auf Cyon
oder auf dem Pi und keinen laufenden Node-Prozess.

Die beiden Betriebsarten heissen `pi` und `cloud`. Produktionsdaten liegen in
MariaDB; SQLite dient zusätzlich den isolierten Entwicklungstests. Datenbankzeiten
werden als UTC geschrieben, die Verbindung verwendet `+00:00`. Anzeigen und
Monitorzeiten benutzen `Europe/Zurich`. Eigene Erstaufbau-Migrationen können nach
abgebrochenen CREATE-TABLE-Schritten wieder anlaufen.

## Fachliche Entscheidungen

- Bestand ist die Existenz eines Produkts in `bar_inventory`. Das Entfernen löscht
  keine Barcodekorrekturen. Ein Barcode hat eine deterministische UUID und einen
  eindeutigen Datenbankindex, auch bei unabhängiger Erfassung auf beiden Instanzen.
- Eine Ersatzregel bedeutet **benötigte Zutat ← vorhandene Ersatzzutat**. Es werden
  mehrere direkte Regeln, aber keine transitiven Ersatzketten angewendet. Mehrfach
  vorkommende fehlende Zutaten zählen einmal, nicht einmal pro Rezeptzeile.
- Ein generischer Einkaufseintrag wird beim Erfassen eines konkreten Barcodes für
  dieselbe Zutat aus dem Bestand entfernt. Produktmetadaten bleiben erhalten.
- Bei mehreren vorhandenen Flaschen derselben Zutat verwendet die ABV-Schätzung
  deterministisch die nach UUID erste Flasche mit bekanntem Alkoholwert. Sonst
  gilt der feste Kategorienwert. Bei einer noch unklassifizierten Flüssigkeit
  ohne Alkoholwert erscheint keine erfundene Null-Prozent-Angabe.
- Mengen aus oz werden mit 2.957352956 cl umgerechnet; Cocktail-Teelöffel mit 0.5 cl,
  Esslöffel mit 1.5 cl. Nicht eindeutig numerische Angaben bleiben im Original
  erhalten. Die Oberfläche skaliert keine Portionen.
- Der Quellkatalog wird in Batches eingelesen. Name, kanonische Zutaten und Mengen
  bestimmen den kontrollierten Dublettenfingerabdruck. Zweitquellen ergänzen die
  Herkunft, ohne die Hauptquelle oder eine manuelle Übersetzung zu überschreiben.
- Standardbilder sind eigene SVG-Illustrationen. Fremde TheCocktailDB-Fotos werden
  nicht ohne einen nachvollziehbaren Lizenznachweis übernommen.
- Alphabetische Gruppensortierung normalisiert diakritische Zeichen; Bewertungen
  sortieren nach Durchschnitt. Favoriten verändern diese Reihenfolge nicht.

## Synchronisation /api/v1

Der Pi startet ausschliesslich ausgehende HTTPS-Anfragen. Ein eigener Bearer-Token
wird auf Cyon nur als SHA-256-Hash in `devices` gespeichert und kann per SSH
widerrufen werden. Benutzerpasswörter sind keine Gerätezugänge.

`POST /api/v1/sync` nimmt `schema_version: 1`, `epoch`, einen numerischen Cursor und
bis zu 50 Ereignisse entgegen. Jedes Ereignis besitzt eine UUID als Idempotenzschlüssel,
einen Entitätstyp, eine stabile Entitätskennung, Nutzdaten und ein Löschflag.
Antworten enthalten bestätigte IDs, höchstens 100 geordnete Ereignisse, den nächsten
Cursor, die Serverepoche und `has_more`.

Die Serverbestätigung vergibt eine monotone Sequenz. **Zuletzt bestätigt** bedeutet
hier ausdrücklich die Reihenfolge der Annahme auf Cyon; manipulierbare Geräteuhren
entscheiden keinen Konflikt. Ein später angenommener Offline-Schreibvorgang gewinnt.
Eine erneute Übermittlung einer bereits angenommenen Ereignis-ID erzeugt weder eine
zweite Änderung noch eine zweite Version. Geräteakteure werden serverseitig bestimmt.

Lokale Änderungen und die Outbox werden in derselben Transaktion geschrieben. Der
Pi bestätigt ausgehende Ereignisse dauerhaft. Eingehende Projektionen, Inbox und
Cursor werden gemeinsam committed. Solange noch lokale Ereignisse unbestätigt sind,
werden ältere eingehende Zustände nicht über diese Änderungen geschrieben. Abbrüche
lassen sich durch Wiederholung und gespeicherte Cursor fortsetzen.

Synchronisierte Aggregate: Produkte einschliesslich Bestand und Zuordnung,
Zutaten/Synonyme, Ersatzregeln, eigene Rezepte, importierter Cyon-Katalog,
Sichtbarkeit, manuelle Übersetzungen, Favoriten, Einkauf, persönliche Bewertungen
von Cyon sowie ausdrücklich erlaubte gemeinsame Einstellungen. Einstellungen haben
namensbasierte UUIDs. Fremdschlüsselbeziehungen bleiben in den Aggregaten erhalten.

`Projector` prüft Feldlisten, Werte und Zuständigkeiten. Kioskgeräte dürfen weder
persönliche Bewertungen setzen noch importierte Originalrezepte verändern. Lokale
Einstellungen, PIN, SMB-Geheimnisse, Fotopfade und Fotorahmenbilder sind keine
zulässigen Synchronisationsentitäten.

`/api/v1/media` akzeptiert ausschliesslich inhaltlich adressierte WebP-Dateien unter
`recipes/` oder `products/`, mit Grössen-, Format- und Hashprüfung. Fotos werden vor
dem Cursorabschluss übertragen. Private `frame/`-Dateien sind ausgeschlossen.

Die API startet mit v1; es existiert noch keine Vorgängerclientversion. Zusätzliche
Envelope-Felder werden toleriert. Künftige inkompatible Änderungen müssen v1
mindestens während einer weiteren Clientgeneration weiter bedienen. Der bisherige
Vertrag darf bei einem Release nicht still geändert werden.

## Sicherheitsgrenzen

- Session-Cookies laufen beim Schliessen des Browsers aus. Das Kioskprofil startet
  zusätzlich inkognito; die Kernel-Boot-ID invalidiert Freigaben nach Pi-Neustart.
  Wiederhergestellte Sitzungen beliebiger Fremdbrowser hängen auch von deren
  Browser-Einstellungen ab; eine verlässliche Browser-Neustarterkennung bietet
  gewöhnliches HTTP für solche Browser nicht.
- Fehlversuche liegen in einer eigenen DB-Tabelle. Eine frische Sitzung, ein neuer
  Prozess oder Neustart heben eine Sperre nicht auf.
- Die lokale Verwaltungsgrenze verwendet die tatsächliche `REMOTE_ADDR`, keine
  vom Client gelieferten Proxy-Header. Der Pi-Kiosk muss daher direkt auf dem
  lokalen Webserver landen. Ein Reverse Proxy auf Loopback ist hierfür ungeeignet.
- PIN, neue PIN, SMB-Passwort und Kontopasswörter werden bei Validierungsfehlern
  nicht als frühere Formularwerte in die Session geschrieben.
- Ein lokaler Recovery-Aufruf verlangt erneut die PIN und einen Gerätezugang;
  Cyon lehnt ihn ab, solange noch eine nicht abgelaufene Mitgliedssitzung existiert.
- Wartung blockiert HTTP und Fachmutationen. Transaktionale Schreibpfade prüfen
  zusätzlich den gesperrten Wartungsdatensatz. Laufende Netzwerkaufrufe können
  auslaufen; ihr lokaler Cursorcommit darf danach nicht mehr erfolgen.
- Logdateien rotieren täglich, sieben Tage Aufbewahrung. Technische Statusmeldungen
  sind in den Einstellungen, nicht als globale Startseitenwarnung sichtbar.

## Bekannte Grenzen der ersten Implementierung

Die automatisierten Prüfungen ersetzen keine Tests mit echten Smartphonekameras,
einer realen SMB-Freigabe, Wayland/Touchhardware oder Cyon. Der Release-Nachweis
bleibt deshalb gesperrt. Die durchgehende Synchronisation über zwei getrennte
Produktionsinstanzen und der Restore-Ablauf sind vor Freigabe zusätzlich zu erproben.
Die Rezeptsuche arbeitet in begrenzten DB-Batches und sortiert kompakte Treffer im
Speicher; die Zielzeiten müssen mit dem vollständigen Katalog auf dem 2-GB-Pi
vermessen werden. Quellmetadaten ohne eindeutige Geschmacks-/Methodenangabe werden
nicht als gesicherte zusätzliche Rezeptmerkmale erfunden.
