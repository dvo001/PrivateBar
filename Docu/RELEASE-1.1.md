# PrivateBar 1.1.0

Stand: 4. Oktober 2026. Manuell bereitgestellte Installations- und Updatepakete
auf Basis des aktuellen Arbeitsstands, keine signierte Produktionsfreigabe.
API und Sync-Schema bleiben Version 1. Gegenüber 1.0.4 keine neue Migration.

## Änderungen

- OFF-Modus mit analoger oder digitaler Uhr; Zeitplan, Weckdauer nach Berührung,
  Farbe und Leuchtkraft im lokalen Einstellungsmenü.
- Getrennte Schalter für Rezeptimport, Übersetzung und Produktsuche.
- Pi-Cyon-Verbindung im lokalen Einstellungsmenü mit geschütztem Geräte-Token
  und lesendem Verbindungstest.
- Manueller SQL-Datenbankexport auf Cyon nach erneuter Passwortprüfung;
  dokumentierte Wiederherstellung inklusive Bilder, APP_KEY und Sync-Epoche.
- Produkthinweise unter den Zutaten im Rezeptdetail.

## Pakete

Alle Dateien liegen unter `artifacts/1.1.0/`:

| Datei | Verwendung |
| --- | --- |
| privatebar-1.1.0-cyon-installation.zip | Neue Cyon-Installation oder kontrollierte Wiederherstellung |
| privatebar-1.1.0-cyon-update.zip | Programm-Overlay für vorhandenes Cyon 1.0.4, ohne .env/storage |
| privatebar-1.1.0-pi-installation.tar.gz | Pi-Erstinstallation und manueller Wechsel von 1.0.4 |
| install-pi-release.sh | Wechsel einer bestehenden Pi-Installation |
| install-prerequisites.sh | Prüfung/Installation der Pi-Betriebssystempakete |
| SHA256SUMS und *.sha256 | Integritätsprüfung der bereitgestellten Dateien |
| package-info.json | Herkunft, Versionsnummer und Paketprüfsummen |

Die Pakete enthalten PHP-Produktionsbibliotheken und gebaute Frontend-Dateien,
keine Zugangsdaten, Datenbanken, privaten Bilder oder Entwicklungsbibliotheken.
Das Pi-Paket ist kein Betriebssystemabbild. Composer und Node sind auf dem
Zielsystem für diese Pakete nicht erforderlich.

## Anleitungen und Prüfstand

- [Cyon installieren/aktualisieren](INSTALLATION-1.1-CYON.md)
- [Pi installieren/aktualisieren](INSTALLATION-1.1-PI.md)
- [Datenbank wiederherstellen](DATENBANK-EXPORT.md)
- [Prüfnachweise](Checks/CHECKS.md)

Die lokale Prüfung ersetzt die Abnahme auf echten Zielsystemen nicht.
`deploy/release-approval.json` bleibt gesperrt. Die manuelle Installation ist
von der signierten Updatefunktion im Menü getrennt.
