# Umsetzungsstand

Stand: 5. September 2026. Die Anwendung ist implementiert und lokal geprüft.
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
auf `false`. Es wurden keine Produktionszugänge eingerichtet, keine Änderungen
veröffentlicht und keine Instanzen deployt.
