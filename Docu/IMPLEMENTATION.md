# Umsetzungsstand

Stand: 6. September 2026, Version 1.0.1. Die Anwendung ist implementiert und lokal geprüft.
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
3. Pi-Touchdisplay und Kamera, SMB-Freigabe, Netzverlust, Fotocache sowie Monitor-Aus-/Einschaltung und 29-Minuten-Weckzeit prüfen. Fotorahmen-Dauertest durchführen.
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
