# Lokale Prüfung am 5. September 2026

| Prüfung | Ergebnis |
| --- | --- |
| PHP 8.3.30 / PHPUnit 12.5.34, SQLite | 34 Tests, 154 Assertions erfolgreich |
| Dieselbe Suite auf MariaDB 10.6.22 | 34 Tests, 154 Assertions erfolgreich |
| PHPStan, Level 5 | Keine Fehler |
| Laravel Pint | Erfolgreich |
| Composer-Konfiguration und Lockdatei | Valide |
| Python-Monitorlogik | 3 Tests erfolgreich |
| Lokaler Ressourcenbuild | Erfolgreich |
| Chromium / Playwright / axe, 1920, 390 und 320 Pixel | 21 Ansichten ohne Überlauf, JavaScriptfehler oder erkannte WCAG-Verstösse |
| Schreibender Browserablauf | Bestand → Einkauf → machbares Rezept → Favorit → eigenes Rezept erfolgreich |
| `git diff --check` | Erfolgreich |

Browserprüfungen liefen gegen eine lokale Entwicklungsinstanz mit SQLite.
Die MariaDB-Prüfung verwendet eine separate, disposable Testdatenbank.
Beim erneuten Start des MariaDB-Prüflaufs war zunächst der temporäre Server nicht
aktiv; nach dessen Start bestand die vollständige Suite. PHPStan lief mit dem
statischen PHP-Binary ohne optionale Turbo-Erweiterung erfolgreich.

Nicht nachgewiesen sind Zielgeräte-Laufzeiten, Dauerbetrieb des Fotorahmens,
physische Kamera-/Touch-/Monitorfunktionen, Live-SMB, Live-Anbieterzugänge sowie
Deployment und Wiederherstellung auf Pi/Cyon. Details: [Umsetzungsstand](../IMPLEMENTATION.md).

Reproduzierbare Befehle und Browserparameter stehen in [README](../../README.md).

## Ergänzung am 6. September 2026: Cyon-Installation ohne SSH

- PHP 8.3.30 / PHPUnit: 43 Tests, 177 Assertions erfolgreich (SQLite).
- Neun neue Tests prüfen Wiederanlauf ohne doppelte Datensätze oder Sync-Ereignisse,
  Rollback bei Fehlern, bestehende Instanzen, Cloud-Modus, Passwortvalidierung,
  parallele Skriptaufrufe, Abschlussmarkierung und geheimnisfreie Fehlerausgaben.
- PHPStan Level 5 erfolgreich (ohne optionale Turbo-Erweiterung).
- Die neuen PHP-Dateien bestehen Laravel Pint; die projektweite Formatprüfung
  meldet bestehende Abweichungen in der unveränderten `tests/Feature/UpdateTest.php`.
- `git diff --check` erfolgreich. Noch keine Ausführung auf Cyon und keine neue
  MariaDB-Abnahme des Installationsablaufs.

## Version 1.0.0: Installationspaket am 6. September 2026

- Nach dem Versionswechsel: 43 Tests, 177 Assertions erfolgreich (SQLite).
- PHPStan Level 5 und projektweite Pint-Prüfung erfolgreich. Der zuvor genannte
  Formatfehler in UpdateTest.php ist behoben; der Test simuliert ausdrücklich
  die Ausgangsversion 0.1.0 für sein Update auf 1.0.0.
- Separates Paket mit `composer install --no-dev --prefer-dist
  --optimize-autoloader --no-interaction` aus der Lockdatei vorbereitet.
- Plattformanforderungen des Pakets mit PHP 8.3.30 geprüft; Laravel startet.
- Cyon-/Pi-Abnahme und Produktionsfreigabe bleiben ausstehend.

## Pi-Grundkomponenten-Skript

- Sieben isolierte Python-/Bash-Tests erfolgreich; keine Host-Pakete installiert.
- Drei Monitorlogiktests erfolgreich; Bash-Syntax und git diff --check erfolgreich.
- Sury-Release-Metadaten für Bookworm und Trixie listen arm64; offizielle
  Keyring-Anleitung als Grundlage verwendet.
- Installation und Paketdienststart auf echtem Raspberry Pi OS noch ausstehend.

## Version 1.0.1: Einladungsmails und E-Mail-Verifizierung

- Vollständiger PHP-Testlauf: 55 Tests, 248 Assertions erfolgreich (PHP 8.3.30, SQLite).

- Zwölf neue Mail-/Verifizierungstests prüfen Versand an den richtigen Empfänger,
  separate Bestätigung, Zugriffssperre für unbestätigte Konten, bestehende Konten,
  Wiederholungsbegrenzung, Ablauf/Signatur, Konto-/Adressbindung, Login auf einem
  zweiten Gerät, Versandfehler und Widerruf, Pi-/Wartungssperren sowie HTML und
  Klartext einschliesslich unverändertem signiertem Link. Keine echten E-Mails gesendet.
- PHPStan Level 5 und projektweite Pint-Prüfung erfolgreich.
- Aktualisierte Dateien auf eine isolierte Kopie des bisherigen Cyon-1.0.0-Pakets
  angewendet: vorhandene Produktionsbibliotheken laden die neuen Klassen; Migration
  einer isolierten SQLite-Testdatenbank, optimize, health und die neuen gecachten
  Verifizierungsrouten erfolgreich. Keine neue Schemamigration erforderlich.
- SMTP-Zustellung auf Cyon und Zielgeräteabnahme bleiben ausstehend.

## Amaretto-Korrektur und Zutatenprüfung

- 58 PHP-Tests mit 267 Assertions erfolgreich auf SQLite.
- Drei neue Tests prüfen den gezielten Seeder-Aufruf samt Wiederholung und
  Sync-Ereignis, den Erhalt importierter IDs und privater Korrekturen sowie
  den Disaronno-Scan bis zur bestätigten Speicherung mit 28 % vol.
- PHPStan Level 5 erfolgreich; keine Frontend- oder Schemaänderung.
- Zutatenabgleich mit OpenDrinks und der verfügbaren TheCocktailDB-Liste:
  Vorgehen, Quellen und Grenzen in [ZUTATEN-CHECK.md](../ZUTATEN-CHECK.md).
- Die Datenkorrektur wurde nicht auf Cyon ausgeführt. Ein neuer MariaDB- oder
  Hardwareprüflauf ist für diese Ergänzung noch nicht dokumentiert.

## Version 1.0.2: Zutatenkatalog, Bereiche und Bearbeitung

- 65 PHP-Tests mit 743 Assertions erfolgreich auf SQLite und auf einer isolierten
  MariaDB 10.6.23, PHP 8.3.30. Der MariaDB-Testserver lief nur über einen lokalen
  Unix-Socket und verwendete eine eigene Testdatenbank.
- PHPStan Level 5 und Laravel Pint erfolgreich.
- Chromium/Playwright/axe: bisherige 21 Ansichten sowie sechs zusätzliche
  Ansichten für Flaschenformular und Zutatenverwaltung bei 1920, 390 und 320
  Pixeln ohne Überlauf, JavaScriptfehler oder erkannte WCAG-Verstösse.
- Schreibender Browserablauf: Bereich eingrenzen, Vorschlag ausfiltern,
  allgemeine Flasche speichern, konkret als Amaretto bearbeiten, eigene Zutat
  anlegen, umbenennen und ein Synonym entfernen. Gruppierte Auswahl auch ohne
  JavaScript geprüft. Testskript: `tests/Browser/ingredients.cjs`.
- Alte Import-IDs, Bestandszuordnungen und Rezeptbezüge bleiben erhalten.
  Tests prüfen Aliasauflösung für Machbarkeit, Alkohol und Einkauf,
  idempotente Katalogergänzung, private Korrekturen, getrennte Sirup-/Spirituosen-
  Vorschläge sowie vollständige und ältere partielle Sync-Payloads.
- Keine echte Cyon-Bereitstellung, Smartphonekamera oder Pi-Hardwareabnahme
  durchgeführt; die Freigabefelder bleiben auf false.
- Isoliertes Update vom bisherigen Produktionspaket samt 1.0.1-Mailpatch:
  Katalog von 27 auf 148 Einträge ergänzt, Wiederholung mit 0 Änderungen,
  Produktionscaches, neue Routen und Healthcheck erfolgreich. Bestehende
  Produktionsbibliotheken laden alle neuen Klassen ohne Composer-Neuinstallation.

## Zutatenkorrektur am 8. September 2026

- Vollständige SQLite-Suite: 69 Tests, 767 Assertions erfolgreich (PHP 8.3.30).
- Vier neue Tests: Vorschau ohne bleibende Änderungen, Wiederholbarkeit,
  unveränderter Bestand, gültige Sync-Payloads, optionale Automatik-Korrektur,
  vollständiges Rollback bei spätem Zuordnungskonflikt, Pi-Schutz und Synonymkonflikt.
- Zusätzlicher isolierter Test mit den vier tatsächlichen JSON-Exporten:
  141 Änderungen, zweiter Lauf 0; alle erzeugten Sync-Payloads validiert.
  Optionaler Automatiklauf: 37 Änderungen. Keine Verbindung zur Live-Datenbank.
- PHPStan Level 5 inklusive beider neuer Skriptdateien: keine Fehler.
  Das statische PHP-Binary meldete die nicht ladbare optionale Turbo-Erweiterung;
  die Analyse lief erfolgreich zu Ende.
- Projektweite Pint-Prüfung, PHP-Syntax und `git diff --check` erfolgreich.
- Kein MariaDB-Server in dieser Umgebung vorhanden; MariaDB-/Cyon-/Pi-Ausführung
  und echter Instanzabgleich bleiben ungeprüft. Keine Frontendänderung.

## Mengen-/Einheitenkorrektur am 18. September 2026

- Vollständige SQLite-Suite unter PHP 8.3.30: 109 Tests, 846 Assertions erfolgreich.
- 36 neue Unit-Testfälle für metrische Umrechnung, Bruchschreibweisen, ungültige
  und mehrdeutige Mengen, genaue Bruchanzeige, praktische cl-Rundung und Bereiche.
- Vier neue Integrationstests prüfen Vorschau, Wiederholbarkeit, Sync-Payload,
  Erhalt privater Rezepte und manueller Übersetzungen, Quell-Deduplizierung,
  Pi-/Wartungssperren sowie die gerenderte Rezeptansicht für Fuzzy Asshole und
  Irish Curdling Cow.
- PHPStan Level 5, projektweite Pint-Prüfung und `git diff --check` erfolgreich.
  Das statische PHP-Binary lädt die optionale PHPStan-Turbo-Erweiterung nicht;
  die Analyse selbst besteht.
- Keine MariaDB-, Browser-/Touch-, Cyon- oder Pi-Abnahme durchgeführt. Die
  Blade-Ausgabe wurde serverseitig geprüft; Layout/CSS bleiben unverändert.
  Keine Live-Daten geändert. Anwendung: [KORREKTUR-MENGEN.md](../KORREKTUR-MENGEN.md).

## Kategorien im Abgleich am 18. September 2026

- Kategorien werden auf Cyon als eigene Stammdatenereignisse akzeptiert und auf
  dem Pi vor Zutaten angewendet; Kategorieereignisse vom Pi werden abgewiesen.
- Jede Cyon-Sync-Antwort liefert zusätzlich den vollständigen Kategorienbestand.
  Damit kann ein alter Zutaten-Event auch dann verarbeitet werden, wenn die
  Kategorie erst später veröffentlicht wurde.
- SyncTest und SyncClientTest: 13 Tests, 63 Assertions erfolgreich; PHPStan und
  Pint erfolgreich. Keine echte Cyon-/Pi-Ausführung durchgeführt.

## Bildabgleich: Regex-Korrektur am 18. September 2026

- Zwei neue Medien-Tests reproduzierten vor der Korrektur denselben
  `preg_match`-Fehler mit HTTP 500 wie auf Cyon.
- Nach Korrektur: SyncTest und SyncClientTest unter PHP 8.3.30/SQLite erfolgreich,
  10 Tests und 55 Assertions. Temporärer APP_KEY nur für den lokalen Testprozess.
- Upload und Download echter WebP-Bilder für beide erlaubten Verzeichnisse
  sowie HTTP 422 für ungültige Pfade geprüft.
- PHPStan Level 5, Pint für die geänderten PHP-Dateien und `git diff --check`
  erfolgreich. Keine erneute vollständige Suite oder MariaDB-/Zielsystemabnahme.

## Version 1.0.3-Paket am 18. September 2026

- Vollständige Suite: 114 Tests, 883 Assertions erfolgreich; PHPStan Level 5,
  Pint und `git diff --check` erfolgreich.
- `artifacts/1.0.3/` enthält Cyon-Update, vollständige Cyon-Installation und
  Pi-Installation mit SHA-256-Prüfsummen. Archive enthalten keine `.env`,
  Datenbanken, Tests oder privaten Schlüssel.
- Die Pakete sind manuell einspielbar, nicht signiert und nicht als reale
  Cyon-/Pi-Produktionsabnahme freigegeben. `deploy/release-approval.json`
  bleibt deshalb auf `false`.
- `deploy/pi/install-release.sh` mit `bash -n` geprüft. Das Pi-Tarball enthält
  das Skript, `artisan`, Produktions-Vendor und keine Tests, Datenbanken oder
  `.env`; Archiv und `.sha256`-Datei wurden mit `sha256sum -c` geprüft.
