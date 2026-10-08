# Umsetzungsstand

## Pi-Paket 1.1.3: 8. Oktober 2026

Eigenes manuelles Updatepaket unter `artifacts/1.1.3/` mit Scrollbalken-Korrektur
für Uhr/Fotorahmen und den Direktbuttons. Anleitung: [Release 1.1.3](RELEASE-1.1.3-PI.md).
Installer verwendet den neuen Pfad `releases/1.1.3`; 1.1.2 bleibt erhalten.
Version und User-Agent angepasst; unveränderte Migrationen/API/Sync.
Shellsyntax, JavaScript-Syntax, 18 Python-Tests, Paketinhalt und Prüfsummen
geprüft. PHP-/Browser-/MariaDB-Prüfungen nicht wiederholt, Zielsystemabnahme offen.

## Pi-Paket 1.1.2: 8. Oktober 2026

Manuelles Updatepaket mit Direktbuttons für Fotorahmen/Uhr unter
`artifacts/1.1.2/`. Anleitung: [Release 1.1.2](RELEASE-1.1.2-PI.md).
Version und User-Agent auf 1.1.2 angehoben; keine neue Migration oder API-Änderung.
Produktionsbibliotheken aus dem bestehenden 1.1.1-Paket bei unverändertem Lockfile.
JavaScript-Syntax, isolierte Anzeigeabläufe, 18 Python-Tests, Shellsyntax und
Paketintegrität geprüft. PHP-/MariaDB-/Browser-/axe-Prüfungen nicht erneut
ausgeführt; keine Zielsystemabnahme und keine signierte Produktionsfreigabe.

## Pi-Paket 1.1.1: 7. Oktober 2026

Manuelles Pi-Installations-/Updatepaket unter artifacts/1.1.1/ mit
Produktionsbibliotheken, aktuellem Frontend, Releaseinstaller und SHA-256.
Update von 1.1.0 ohne Datenbankmigration; Cyon 1.1.0 und API-/Sync-Schema 1
bleiben kompatibel. Änderungen/Anleitung: RELEASE-1.1.1-PI.md.
Der Kiosk wartet auf die HTTPS-Anmeldung und verwendet Wayland-Eingabeoptionen.
Der Installer aktualisiert zusätzlich den root-eigenen SMB-Helfer am Standardpfad.

134 PHP-Tests / 1002 Assertions auf SQLite bestanden, zwei MariaDB-spezifische
Tests mangels Server übersprungen. PHPStan Level 5 und Pint bestanden; optionale
Turbo-Erweiterung im statischen PHP nicht ladbar, Analyse selbst erfolgreich.
18 Python-Tests bestanden. Produktionsbibliotheken aus dem 1.1.0-Paket:
76 Pakete ohne Entwicklungsabhängigkeiten; composer.lock bytegleich.
Isoliertes Produktionsstaging: Migration/Seed, optimize und Healthcheck bestanden.
21 reguläre Browser-/axe-Ansichten und Einstellungsabläufe inklusive PIN-Popup,
Abbrechen und tatsächlich serverseitig gespeichertem Wert bei 1920, 390 und
320 Pixeln bestanden; keine Überläufe, JavaScriptfehler oder erkannten axe-Verstösse.
Die HTTP-Browserinstanz verwendete Produktionsbibliotheken und isolierte lokale
Testkonfiguration; produktives HTTPS wird weiterhin erzwungen.
Releaseinstaller mit echten Produktionsdateien/PHP in isolierter Testwurzel:
Cachepfade/Healthcheck, Symlinkwechsel und Erhalt von .env/storage geprüft;
Rootprüfung im Harness ausgelassen, Dienstbefehle simuliert und root-eigene
Helferinstallation am Standardpfad dort nicht ausgeführt. Shellsyntax bestanden.
Paketinhalt und SHA-256 geprüft; keine echte .env, Datenbank, privaten Bilder,
Laufzeitcaches, Tests oder Entwicklungsbibliotheken enthalten.

Die SMB-Korrektur ist laut Nutzer nach Pi-Neustart erfolgreich bestätigt.
Keine Installation oder vollständige Pi-Abnahme des gebündelten 1.1.1-Pakets,
keine neue MariaDB-/Cyon-Abnahme, kein Git-Tag und keine Signatur. Die
Produktionsfreigabe bleibt gesperrt; manuelles Anwendungspaket vorbereitet.


## SMB automatisch nach Neustart einbinden: 7. Oktober 2026

Der SMB-Helfer bindet eine konfigurierte, nicht eingehängte Fotoquelle jetzt
auch ohne neuen smb_mount_requested-Auftrag ein. Damit übernimmt der bestehende
Minutentimer die Wiederherstellung nach einem Neustart und weitere Versuche nach
einem fehlgeschlagenen Mount. Bereits eingebundene Quellen werden ohne
ausdrücklichen Auftrag nicht ausgehängt. Unkonfigurierte Quellen bleiben inaktiv.
Read-only-Optionen und Entfernen der temporären Zugangsdaten bleiben erhalten.
Fünf isolierte Python-Tests bestehen: fehlender Mount nach Boot, vorhandener
Mount, unkonfigurierte Quelle, expliziter Remount und erneuter Versuch nach Fehler.
git diff --check bestanden; Installation des geänderten Helfers und
Neustartprüfung auf dem echten Pi stehen noch aus.

## PIN-Popup bei lokalen Aktionen: 7. Oktober 2026

Auf der lokalen Einstellungsseite öffnet Speichern beziehungsweise Ausführen
ein modales PIN-Popup mit eigenem Ziffernblock. Dies umfasst lokale Einstellungen,
Pi–Cyon-Verbindung, Verbindungstests, Updateprüfung/-installation, Wartungsmodus
und Onlinezugang-Wiederherstellung. Die aktuelle PIN wird erst im Popup abgefragt;
das optionale Feld für eine neue PIN bleibt im Einstellungsformular.
Jede Aktion erfordert eine frische, verdeckte sechsstellige Eingabe. Abbrechen
und Escape lösen keinen Vorgang aus und erhalten die Einstellungen. Das Popup
zeigt den gewählten Vorgang, setzt Tastaturfokus und löscht seine PIN beim
Schliessen. Die ursprünglichen POST-Ziele, CSRF- und serverseitigen PIN-Prüfungen
bleiben bestehen. Ohne JavaScript bleiben die bisherigen PIN-Felder verfügbar.

Isolierte Chromium-Prüfung mit tatsächlichem Dialogmarkup, JavaScript und CSS
bei 1920 × 1200, 390 und 320 Pixeln bestanden: kein Ausführen vor Bestätigung,
unvollständige PIN abgewiesen, Abbrechen/Escape, Einstellungen erhalten, korrekte
PIN/Formularwerte am passenden Ziel, PIN nicht wiederverwendet, Dialog im
Viewport und kein horizontaler Überlauf oder JavaScriptfehler. Testskript:
tests/Browser/pin-confirm.cjs. Vorhandene Browserabläufe verwenden nun physische
Zifferntasten statt fill() an schreibgeschützten PIN-Feldern. Ressourcen und
SHA-256-Manifest aktualisiert; JavaScript-Syntax und git diff --check bestanden.
Keine PHP-Laufzeit, kein vollständiger Laravel-/axe-Lauf oder echte Pi-Abnahme.
Noch nicht bereitgestellt.

## Breiter Seiten-Scrollbalken: 7. Oktober 2026

Der rechte Seiten-Scrollbalken ist in Chromium ab 761 Pixel Bildschirmbreite
32 Pixel breit. Olivgrüner Griff auf dunkler Spur, gelber Hover- und Terrakotta-
Aktivzustand passen zur Oberfläche. Eine stabile Scrollleiste verhindert
Layoutsprünge beim Wechsel zwischen kurzen und langen Seiten.
Isolierte Chromium-Prüfung mit dem tatsächlichen CSS bei 1920 × 1200, 390 und
320 Pixeln: Desktopbreite 32 Pixel, schmale Ansichten ohne diese Verbreiterung,
Scrollen möglich und kein horizontaler Überlauf. Ressourcen und Manifest
aktualisiert; git diff --check bestanden. Keine Bereitstellung oder Pi-Abnahme.

## Numerische PIN-Eingabe am Pi: 7. Oktober 2026

Am direkt lokal erkannten Pi-Bildschirm erhalten alle PIN-Felder einen eigenen
Ziffernblock mit 0–9, Leeren und Löschen der letzten Ziffer. Die PIN bleibt ein
verdecktes Passwortfeld. Schreibschutz unterdrückt die zusätzliche Systemtastatur;
vor dem Absenden wird die HTML-Validierung ausdrücklich geprüft. Eingaben bleiben
auf sechs Ziffern begrenzt, führende Nullen erhalten. Physische Zifferntasten und
Backspace/Delete funktionieren ebenfalls. Ohne JavaScript oder bei Zugriff aus
dem Heimnetz bleibt das bisherige Passwortfeld mit inputmode=numeric bestehen.
Der Ziffernblock ist auch auf der lokalen Wartungsseite verfügbar.

JavaScript-Syntax und isolierte Chromium-Browserprüfung mit den tatsächlichen
Frontend-Dateien bei 1920 × 1200, 390 und 320 Pixeln bestanden: führende Null,
sechsstellige Begrenzung, Löschen, physische Tastatur, Pflicht-PIN, unvollständige
optionale neue PIN, gültige Formularwerte und kein horizontaler Überlauf.
Testskript: tests/Browser/pin.cjs (node tests/Browser/pin.cjs; optional
PRIVATEBAR_BROWSER_EXECUTABLE für eine vorhandene Chromium-Installation).
Ressourcenbuild und SHA-256-Manifest aktualisiert; git diff --check bestanden.
Kein vollständiger Laravel-/axe-Prüflauf, keine PHP-Laufzeit verfügbar und keine
Prüfung mit Squeekboard auf dem echten Pi. Noch nicht bereitgestellt.

## Grössere Pi-Oberfläche: 7. Oktober 2026

Für Bildschirmbreiten ab 761 CSS-Pixeln sind Grundschrift (16 → rund 21.33 Pixel),
Button-/Eingabefeldabstände und Mindesthöhen um ein Drittel vergrössert.
Buttons und Eingabefelder sind mindestens 64 statt 48 Pixel hoch; Checkboxen
32 statt 24 Pixel gross. Überschriften, Beschriftungen und Navigationsschrift
skalieren mit. Die Seitennavigation ist entsprechend breiter; bei 1920 × 1200
stehen drei statt vier Rezeptkarten nebeneinander. Zwischen 761 und 1150 Pixeln
werden enge Inhaltsraster einspaltig. Smartphoneansichten bis 760 Pixel behalten
ihre bisherigen Grössen.

Quell-CSS und öffentliche Ressourcen inklusive SHA-256-Manifest sind aktualisiert.
Ressourcenintegrität und `git diff --check` sind geprüft. PHP-/Node-/Browserlaufzeit
stehen in dieser Sitzung nicht bereit; die Browser-/axe-Prüfung bei 1920 × 1200,
390 und 320 Pixeln sowie die tatsächliche Lesbarkeit/Touchbedienung auf dem Pi
bleiben offen. Keine Bereitstellung oder Aktualisierung der Releasepakete.

## Version 1.1.0: 4. Oktober 2026

Manuelle Cyon-Installations-/Updatepakete und Pi-Installations-/Updatepaket
unter `artifacts/1.1.0/`, inklusive Produktionsbibliotheken, Frontend und
Prüfsummen. Separate Anleitungen: INSTALLATION-1.1-CYON.md und
INSTALLATION-1.1-PI.md. Änderungen und Herkunft: RELEASE-1.1.md.
Keine zusätzliche Datenbankmigration gegenüber 1.0.4; API-/Sync-Schema bleibt 1.
Pi-Update erfordert auch den Austausch und Neustart des Monitor-User-Service.
Die tatsächliche Cyon-/Pi-Abnahme steht aus; keine Produktionsfreigabe oder
Bereitstellung und keine Signatur für die Updatefunktion im Menü.

## Produkthinweise bei Drink-Zutaten: 21. September 2026

Im Rezeptdetail steht unter jeder zugeordneten Zutat «Aus deiner Bar» mit Marke
und Produktname der vorhandenen Produkte. Mehrere passende Produkte erscheinen
als Alternativen mit «oder». Bei Ersatz wird der Bestand der verwendeten
Ersatzzutat angezeigt; historische Zutaten-Synonyme werden berücksichtigt.
Generische Bestandseinträge sind ausdrücklich gekennzeichnet. Für automatische
Grundzutaten ohne Produkt und fehlende Zutaten erscheint kein Produkthinweis.
Die bestehende Alkoholschätzung und Machbarkeitslogik bleiben unverändert.

Regressionstests für vorhandene/entfernte Produkte, mehrere Alternativen,
HTML-Escaping, Ersatz und historische Zuordnungen sind ergänzt.
`git diff --check` ist erfolgreich. PHP-Tests, PHPStan und Pint konnten in dieser
Sitzung mangels auffindbarer PHP-Laufzeit nicht ausgeführt werden.
Browserprüfung und Abnahme auf Cyon/Pi stehen aus; keine Bereitstellung erfolgt.

Stand: 4. Oktober 2026, Version 1.1.0. Die Anwendung ist implementiert und lokal geprüft.
Eine Produktionsfreigabe gemäss AGENTS.md ist damit noch nicht erteilt.

## Implementiert

- Laravel 13 / PHP 8.3, Blade, MariaDB-Schema, lokale Frontend-Ressourcen und deutsche Touchoberfläche.
- Bestand ohne Mengenverwaltung, Barcode-Erfassung mit manueller Korrektur, kanonische Zutaten, Synonyme und gerichtete Ersatzregeln.
- Vier Machbarkeitsstufen, Suche und Filter, Einkaufsliste, Favoriten, persönliche Bewertungen, eigene Rezepte, Kopien und komprimierte Fotos.
- Zufallsauswahl mit Verlauf, Tagesempfehlung und Alkoholschätzung aus bekannten Flüssigkeitsmengen.
- Cloud-Importadapter für TheCocktailDB/OpenDrinks und Azure-Übersetzung mit Schutz manueller Bearbeitungen.
- Kiosk-PIN, persistente Anmeldesperren, Haushaltskonten, einmalige Einladungs-/Resetlinks und Gerätewiderruf.
- Transaktionales Änderungsjournal, quittierter Geräteabgleich, Wiederholungsbehandlung, Tombstones, Epochen und kontrollierter Neuaufbau nach Wiederherstellung.
- Lokaler SMB-Fotocache, Fotorahmen, Monitorsteuerung, Wartungsmodus und signaturgeprüfter manueller Releasewechsel.
- Pi-Systemdienste, Cyon-/Pi-Installationsanleitung, CI-Prüfung ohne Deployment, Logo, Icon und A3-Poster.

## Nachweise und Grenzen

Die PHP-Tests prüfen insbesondere Sperren, Kernabläufe, Import-/Übersetzungsschutz,
Synchronisationswiederholungen, Bildverarbeitung und fehlgeschlagene/erfolgreiche
Releasewechsel. Externe HTTP-Dienste werden dabei simuliert. Browserprüfungen
prüfen sieben Ansichten bei drei Bildschirmbreiten auf Überlauf, JavaScriptfehler
und automatisiert erkennbare WCAG-Verstösse. Das ersetzt keine vollständige
manuelle Barrierefreiheitsprüfung.

Die konkreten Prüfergebnisse stehen in [Checks/CHECKS.md](Checks/CHECKS.md).

## Vor einer Freigabe noch erforderlich

1. Pi und Cyon tatsächlich einrichten: Datenbanken, HTTPS, PIN/Konten, Gerätezugang, Cron/systemd und Anbieterzugänge.
2. Beide echten Instanzen zusammen abnehmen: Offline-Schreiben, Verbindungsabbruch, konkurrierende Änderungen, Wiederanlauf und Wiederherstellung.
3. Pi-Touchdisplay und Kamera, SMB-Freigabe, Netzverlust, Fotocache sowie OFF-Uhr und konfigurierbare Weckdauer prüfen. Fotorahmen-Dauertest durchführen.
4. Antwortzeiten und Speicherbedarf mit vollem importiertem Katalog auf dem Ziel-Pi messen. Lokale Desktopmessungen belegen keine Pi-Leistungsgrenze.
5. Live-Anbieterzugriffe, Übersetzungsqualität und Quellmetadaten prüfen. Unbekannte Mengen/Metadaten werden nicht erfunden; dadurch bleiben einzelne Alkoholschätzungen offen.
6. Signiertes Release mit echtem Schlüssel, privaten Artefakten und Produktionsverzeichnisrechten testen. Wiederherstellungsbefehle im Betrieb proben; es gibt keine automatische Sicherung.
7. Poster im endgültigen Druck und Oberfläche am echten Touchgerät visuell abnehmen.

Die Freigabefelder in `deploy/release-approval.json` bleiben bis zu diesen Nachweisen
auf `false`. Zugangsdaten werden nicht versioniert. Die vom Benutzer begonnene
Einrichtung auf Cyon und Pi ersetzt noch keine vollständige Zielsystemabnahme.

## Ergänzung: Cyon-Erstinstallation ohne SSH

`tools/cyon-install.php` ermöglicht die einmalige Cloud-Erstinstallation per
my.cyon-Cronjob mit geschützter JSON-Konfiguration, Dateisperre, fortsetzbarem
Datenaufbau und Abschlussmarkierung. Die Anleitung steht in [DEPLOYMENT.md](DEPLOYMENT.md).
Lokal bestehen 43 Tests mit 177 Assertions, einschliesslich neun neuer Tests
für Wiederanlauf, Rollback, Instanzschutz, Dateisperre und geheime Fehlerdaten.
Die Prüfung auf echtem Cyon-Hosting sowie Update- und Wiederherstellungsabläufe
ohne SSH stehen weiterhin aus.

## Version 1.0.0 als Cyon-Installationspaket

Die Anwendungsversion ist auf 1.0.0 gesetzt. Das lokal vorbereitete ZIP unter
`artifacts/privatebar-1.0.0-cyon-installation.zip` enthält Produktionsabhängigkeiten,
gebaute Assets, eine Cloud-Umgebungsvorlage und das einmalige Installationsskript.
Es dient der Ersteinrichtung und Zielsystemabnahme; es ist kein freigegebenes,
signiertes Release und kein Pi-Update. Die Freigabefelder bleiben auf `false`.
Die Ausgangsversion der Update-Tests ist unabhängig von der Anwendungsversion
festgelegt. 43 Tests mit 177 Assertions, PHPStan und Pint bestehen.

## Version 1.0.0 als Pi-Installationspaket

`artifacts/privatebar-1.0.0-pi-installation.tar.gz` enthält dieselben
Produktionsabhängigkeiten und Assets wie das Cyon-Paket, dazu eine Pi-Vorlage
für .env, Pi-Dienste und eine Anleitung unter [INSTALLATION-PI.md](INSTALLATION-PI.md).
Es enthält keine Betriebssystempakete und ist kein signiertes Pi-Update.
PHP-Start und Plattformanforderungen sind lokal geprüft; die ARM-/Hardwareabnahme
bleibt ausstehend. Drei Monitorlogiktests bestehen.

## Grundkomponenten für den Pi

`deploy/pi/install-prerequisites.sh` prüft Raspberry-Pi-Hardware, arm64 und
Bookworm/Trixie und installiert bei ausdrücklichem Aufruf mit --install die
Grundpakete samt PHP 8.3. Bei Bedarf wird die signierte Sury-Paketquelle ergänzt.
Die Pi-Dienste verwenden ausdrücklich /usr/bin/php8.3. Sieben isolierte Tests
prüfen Plattformgrenzen, Paketauswahl, PHP-Kandidaten und sichere Optionsbehandlung;
drei Monitorlogiktests bestehen weiterhin. Eine echte Paketinstallation auf
Raspberry Pi OS ist noch nicht abgenommen. Anleitung: INSTALLATION-PI.md.

## Version 1.0.1: Einladungsmails und E-Mail-Verifizierung

Die aktualisierte V1-Vorgabe umfasst SMTP-Einladungen und eine separate
E-Mail-Verifizierung vor dem Onlinezugriff. Bestehende unbestätigte Konten
erhalten nach dem Login eine Bestätigungsmöglichkeit. SMTP-Ausfälle werden
am Vorgang angezeigt; Einladungslinks bei Versandfehler widerrufen.
Signierte Bestätigungslinks sind 30 Minuten gültig und an Konto sowie aktuelle
E-Mail-Adresse gebunden. Der erneute Versand ist auf einmal pro Minute begrenzt.
Das vorhandene Feld email_verified_at wird verwendet, ohne Schemaänderung.
SMTP-Konfiguration und Umstellung stehen in DEPLOYMENT.md. Live-Mailversand
und Zustellung auf Cyon müssen mit dem echten Mailkonto noch geprüft werden.

Das Cyon-Updatepaket `artifacts/privatebar-1.0.1-cyon-update.zip` enthält nur
die für den Mailfluss geänderten/neuen Dateien und keine Zugangsdaten.
Die Umstellung einer bestehenden 1.0.0-Installation ist in
[UPDATE-1.0.1.md](UPDATE-1.0.1.md) beschrieben.

Für 1.0.1 bestehen 55 Tests mit 248 Assertions sowie PHPStan und Pint.

Der Git-Tag `v1.0.1` kennzeichnet diesen Quellstand. Er ist keine Bestätigung
der ausstehenden Produktionsfreigabe und löst keine Bereitstellung aus.
Die CI prüft zusätzlich die Pi-Helfer; lokale Installationsarchive unter
`artifacts/` bleiben ausserhalb von Git.

## Zutatenprüfung und Amaretto-Korrektur

Die Grundliste enthält im Stand v1.0.1 nur 27 Zutaten. Weitere Lücken und
fehlende Synonyme sind in [ZUTATEN-CHECK.md](ZUTATEN-CHECK.md) dokumentiert;
die Prüfung umfasst den Quellstand und externe Rezeptdaten, nicht die Cyon-DB.
Amaretto ist als gezielte Korrektur vorbereitet: neue Installationen erhalten
die Zutat, bestehende Installationen verwenden den idempotenten AmarettoSeeder.
Dieser bewahrt vorhandene Zuordnungen und protokolliert Ergänzungen für die
Synchronisation. Die Anleitung steht in [KORREKTUR-AMARETTO.md](KORREKTUR-AMARETTO.md).
Die übrigen im Bericht genannten Ergänzungen sind mit Version 1.0.2 umgesetzt.

## Version 1.0.2: Vollständiger Grundkatalog und bearbeitbare Zuordnungen

- 134 konkrete Zutaten, 14 allgemeine Bereichseinträge und 355 Namen/Synonyme
  bei einer Neuinstallation; Ergänzung bestehender Instanzen über IngredientCatalogSeeder.
- Bereichsfilter und Suche beim Flaschenscan, gruppierte Auswahl auch ohne
  JavaScript und in der Rezepterfassung. Eine vorläufige Bereichszuordnung
  erfüllt keine spezifische Rezeptzutat.
- Vorhandene Flaschen samt Zutatenzuordnung bearbeitbar; keine neue Flasche
  bei einem Zuordnungswechsel. Zutaten können neu erfasst, umbenannt und einem
  Bereich zugeordnet werden. Vorhandene Synonyme sind sichtbar und entfernbar.
- Synonymlöschungen werden mit vollständigen Synonymlisten synchronisiert;
  ältere Payloads ohne dieses Feld erhalten bestehende Synonyme.
- Eindeutige alte Importnamen werden bei Bestand, Rezeptmachbarkeit,
  Alkoholschätzung und Einkauf auf die Hauptzutat aufgelöst. Historische IDs
  und Verweise bleiben erhalten; private Konflikte werden nicht überschrieben.
- Bourbon und Scotch getrennt für neue Zuordnungen; bestehende generische
  Whisky-Angaben bleiben bis zur manuellen Präzisierung erhalten.
- Keine neue Schemamigration oder Composer-Abhängigkeit. Updateanleitung:
  [UPDATE-1.0.2.md](UPDATE-1.0.2.md). Zielsystemabnahme weiterhin ausstehend.

## Zutatenkorrektur für den Export vom 8. September 2026

Ein separat ausführbares CLI-/Cron-Skript mit Vorschau ist unter
`tools/correct-ingredients.php` vorbereitet. Es korrigiert gezielt Kategorien,
119 Zutaten und acht Produktzuordnungen; fünf Zutaten werden ergänzt. Historische
IDs und der Barbestand bleiben erhalten. Automatische alkoholische Zutaten können
optional deaktiviert werden. Kategorien müssen wegen fehlender Kategorien-Sync
vorher separat auf dem Pi angelegt werden. Anleitung und Grenzen stehen in
[KORREKTUR-ZUTATEN-2026-09-08.md](KORREKTUR-ZUTATEN-2026-09-08.md), alle
Einzelkorrekturen in [KORREKTUR-ZUTATEN-DETAILS.md](KORREKTUR-ZUTATEN-DETAILS.md).
Die Live-Daten wurden nicht verändert; keine Änderung am allgemeinen Importer.

## Mengen und Einheiten: 18. September 2026

Der Mengenparser verarbeitet gemischte Brüche mit Bindestrich, Unicode-Brüche,
führende Dezimalpunkte, Dezimalkommas sowie weitere metrische und Löffeleinheiten.
Fehlende Einheiten werden nicht mehr als Stück interpretiert. Brüche bleiben
in der Anzeige erhalten; cl-Mengen werden normalerweise auf eine Nachkommastelle
gerundet, eindeutige Volumenbereiche metrisch dargestellt. Die interne
Umrechnungspräzision bleibt erhalten.
Der Vorschau-/Korrekturbefehl `privatebar:normalize-measures` repariert vorhandene
Importmengen anhand ihrer Originalangaben; `--apply` veröffentlicht geänderte
Rezepte für die Synchronisation. Eigene Rezepte und Haushaltskopien bleiben erhalten.
Anleitung und fachliche Grenzen: [KORREKTUR-MENGEN.md](KORREKTUR-MENGEN.md).
Keine Ausführung auf Cyon/Pi und keine Produktionsfreigabe.

## Vereinfachte Suche unter Machbar

Unter «Machbar» enthält das Suchformular nur «Menü suchen», «Alkohol» und
«Sortierung». Die übrigen Suchansichten verwenden weiterhin ihre bisherigen
Filter. Die Machbarkeitsbeschränkung wird weiterhin serverseitig gesetzt.

## Kategorien im Abgleich: Cyon → Pi

Zutatenkategorien werden jetzt als Stammdaten vom Cyon an den Pi gespiegelt.
Cyon bleibt führend; der Pi weist Kategorieänderungen zurück. Jede Sync-Antwort
enthält zusätzlich den vollständigen aktuellen Kategorienbestand, sodass auch
ältere Zutatenereignisse nachträglich validiert werden können. Kategorien werden
vor Zutaten angewendet. Die API-Version bleibt 1.

## Bildabgleich: Regex-Korrektur am 18. September 2026

Die Pfadvalidierung von `/api/v1/media` verwendet eine Array-Regel, damit
Laravel die Verzeichnisalternative `recipes|products` nicht als Regeltrenner
behandelt. Der zuvor reproduzierte HTTP-500-Fehler wird damit behoben.
Anleitung: [KORREKTUR-BILDSYNC.md](KORREKTUR-BILDSYNC.md).
Cyon-/Pi-Ausführung und tatsächlicher Bildabgleich bleiben ausstehend.

## OFF-Uhr: 4. Oktober 2026

Die lokale Ruhezeit zeigt im entsperrten Pi-Kioskbrowser eine analoge oder digitale
Uhr. Zeitplan, Weckdauer, Farbe und Darstellungshelligkeit sind PIN-geschützt unter
lokalen Einstellungen konfigurierbar. Erste Berührung wird abgefangen; Interaktion
verlängert die Weckdauer auch über Seitennavigation hinweg. Der Fotorahmen pausiert
während der Ruhezeit. Änderungen werden alle 30 Sekunden lokal abgefragt.
Der aktualisierte Monitor-Dienst hält den Bildschirm eingeschaltet und muss auf
bestehenden Pi-Installationen separat ersetzt und neu gestartet werden.
Keine Datenbankmigration, keine Synchronisation dieser lokalen Einstellungen.
Python-Prüfungen und Diff-Prüfung erfolgreich; PHP-/Browserprüfung und reale
Pi-Anzeige bleiben mangels Laufzeiten beziehungsweise Zielzugriff offen.

## Externe Dienste und Verbindung: 4. Oktober 2026

Das Einstellungsmenü bietet getrennte Schalter für automatischen Rezeptimport,
automatische Übersetzung (beide Cyon) und Online-Produktsuche (je Instanz).
Der bisherige .env-Schalter liefert nur den Startwert, solange der betreffende
Menüwert nicht gespeichert ist. Import und Übersetzung laufen unabhängig;
pausierter Import behält seinen Cursor. Produktcache bleibt bei deaktivierter
Online-Suche nutzbar; auch Produktbilder werden dann nicht nachgeladen.

Die lokalen Einstellungen bieten PIN-geschützte HTTPS-Adresse, verschlüsselt
lokal gespeicherten Gerätezugang und einen lesenden Verbindungstest.
Ein leeres Zugangsfeld behält den bisherigen Zugang; Serverwechsel erfordert
erneute Eingabe. Laufender Abgleich sperrt Verbindungsänderungen. Synchronisation,
Medienabgleich und Kontowiederherstellung verwenden dieselbe konfigurierte
Verbindung. Geheimnisse werden weder angezeigt noch in Formularfehlern geflasht
oder synchronisiert. Keine Migration und keine Änderung des Sync-Schemas nötig.
Cyon erhält den zusätzlichen authentifizierten GET-Endpunkt /api/v1/device-check.

Die Browserprüfung entdeckte eine durch die Uhr-Hintergrundabfrage veränderte
Rücksprungadresse nach Formularaktionen. Die Hintergrundabfragen sind jetzt als
AJAX markiert; das Speichern der Dienstschalter führt explizit zu Einstellungen.

129 Tests mit 984 Assertions bestehen auf SQLite. 128 Tests mit 982 Assertions
bestehen auf MariaDB 10.6.23; die anschliessend ergänzte Regression für die
Rücksprungadresse besteht zusätzlich mit MonitorTest auf MariaDB (2 Tests, 9 Assertions).
PHPStan Level 5, projektweite Pint-Prüfung, Ressourcenbuild und JavaScript-Syntax
bestehen. 21 allgemeine Ansichtsprüfungen und die neuen Einstellungsabläufe bei
1920, 390 und 320 Pixeln bestehen inklusive axe und Überlauf-/JavaScriptprüfung.
Alle Datenbanken und Anbieterantworten waren isoliert beziehungsweise simuliert.
Keine Bereitstellung oder reale Verbindung zu Cyon/Pi; Zielabnahme bleibt offen.
Bedienung und Update-Reihenfolge: DEPLOYMENT.md.

## Manueller Cyon-Datenbankexport: 4. Oktober 2026

Unter Einstellungen bietet Cyon einen passwortgeschützten SQL-Datenbankdownload
für eine Neuinstallation. Der Export umfasst Struktur und dauerhafte Daten aus
einem konsistenten InnoDB-Snapshot, inklusive Konten, Gerätehashes und Sync-Verlauf.
Batches begrenzen den Speicherbedarf; Binärwerte werden als Hexliterale geschrieben.
Die Datei entsteht ausserhalb des Webroots, wird erst vollständig zum Download
angeboten und danach gelöscht. Bei Lesefehlern werden Transaktion und Datei aufgeräumt;
Fehlermeldungen enthalten keine SQL-Werte oder Zugangsdaten. Der Pi bietet diese
Funktion nicht, unbestätigte Konten erhalten keinen Zugriff.

SQL-Import erfolgt in eine neue, leere MariaDB über phpMyAdmin; kein destruktives
DROP und keine Bindung an den alten Datenbanknamen. Sitzungen, Cache und Jobs
bleiben leer, Remember-me-Zugänge werden entfernt und Wartung ist nach Import aktiv.
Bilder und .env/APP_KEY müssen separat gesichert werden. Die Anleitung
[DATENBANK-EXPORT.md](DATENBANK-EXPORT.md) beschreibt Neuinstallation ohne SSH,
Cron-Prüfschritte, neue Sync-Epoche und anschliessenden kontrollierten Pi-Neuaufbau.
Es handelt sich um einen manuellen Export, nicht um automatische Sicherungen.

Vollständige Suite: 134 Tests auf SQLite (zwei MariaDB-spezifische Tests übersprungen)
und 134 Tests auf MariaDB erfolgreich. Zusätzlich geprüfter SQL-Roundtrip erhält
Sonderzeichen, Binärwerte, Dezimalzahlen, generierte Spalten und Fremdschlüssel und
schliesst Änderungen nach Snapshotbeginn aus. Download, Dateilöschung und Fehler-
Rollback sind geprüft. PHPStan/Pint und Browser/axe bei 1920, 390 und 320 Pixeln
bestehen. Keine Ausführung oder Installation auf echtem Cyon; dessen Importoberfläche,
reale Datenmenge und Pi-Wiederanlauf bleiben abzuklären.

## Direkter Aufruf von Fotorahmen und Uhr: 8. Oktober 2026

Rechts neben «Zu Hause» stehen am entsperrten, direkt lokal erkannten Pi zwei
quadratische 44-Pixel-Schaltflächen mit beschrifteten Bild-/Uhrsymbolen. Sie öffnen
den vorhandenen Fotorahmen beziehungsweise die konfigurierte analoge/digitale Uhr
sofort, auch ausserhalb des Ruhezeitplans. Die erste Berührung schliesst die Anzeige
ohne darunterliegende Aktionen; der Fokus kehrt zum Auslöser zurück. Kritische
Dialoge verhindern den manuellen Start weiterhin. Der Zeitplan bleibt unverändert.

JavaScript-Syntax mit Node 24.16.0 und `git diff --check` erfolgreich.
Isolierter JavaScript-Ablauftest bestätigt manuellen Uhrstart ohne aktiven
Zeitplan, Fortbestand der Anzeige, Aufwecken und manuellen Fotorahmenstart.
Assets und SHA-256-Manifest mangels PHP-Laufzeit nach dem Verfahren von
`tools/build.php` mit Python erzeugt. Browser-/Touchprüfung sowie Pi-Abnahme
stehen noch aus; Playwright und PHP sind in dieser Umgebung nicht installiert.

## Scrollbalken in Fotorahmen/Uhr: 8. Oktober 2026

Solange die gemeinsame Vollbildanzeige sichtbar ist, blendet CSS den
Dokument-Scrollbalken und dessen reservierten Platz aus und sperrt das Scrollen
der darunterliegenden Seite. Das gilt für manuellen und automatischen Start.
Beim Schliessen gelten automatisch wieder die normalen Scrollregeln.
Ressourcen und manuelles Pi-Paket 1.1.2 aktualisiert; physische Pi-Prüfung offen.
