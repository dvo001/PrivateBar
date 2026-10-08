# PrivateBar 1.1.2 für den Raspberry Pi

Stand: 8. Oktober 2026. Manuelles Pi-Update von 1.1.1. Cyon 1.1.0 bleibt
kompatibel. Keine neue Datenbankmigration, API und Sync-Schema bleiben Version 1.

## Änderungen

- Fotorahmen und Uhr ohne sichtbaren Scrollbalken oder reservierten Rand.

- Zwei quadratische Touch-Buttons rechts neben «Zu Hause» am entsperrten lokalen
  Pi: Fotorahmen sofort starten oder konfigurierte analoge/digitale Uhr öffnen.
- Die Uhr lässt sich unabhängig vom Ruhezeitplan öffnen. Eine erste Berührung
  führt ohne darunterliegende Aktion zur bisherigen Ansicht zurück.
- Kritische Dialoge verhindern den manuellen Start weiterhin.
- Alle bisherigen Änderungen aus 1.1.1 inklusive numerischer PIN-Tastatur bleiben
  enthalten. Der Zeitplan und die Einstellungen werden nicht geändert.

## Bestehenden Pi aktualisieren

Tarball, SHA-256-Datei und den Installer aus `artifacts/1.1.2/` gemeinsam nach
`/home/pbar/Downloads` kopieren. Danach auf dem Pi:

```bash
cd /home/pbar/Downloads
sha256sum -c privatebar-1.1.2-pi-installation.tar.gz.sha256
sudo bash install-pi-release.sh /home/pbar/Downloads/privatebar-1.1.2-pi-installation.tar.gz
```

Nur bei erfolgreicher Prüfsumme installieren. Bei einem Installerfehler stoppen.
Der Installer erhält die gemeinsame `.env`, `APP_KEY`, Datenbank, Einstellungen,
Zugangsdaten, Fotos und `storage`. Er prüft den neuen Release unter seinem
endgültigen Pfad und wechselt erst danach den `current`-Symlink. Das bisherige
Release-Verzeichnis bleibt erhalten. Composer und Node sind am Pi nicht nötig.

Nach erfolgreicher Installation:

```bash
sudo reboot
```

Version 1.1.2, PIN-Ziffernblock und beide neuen Buttons am Bildschirm prüfen.
Uhr auch ausserhalb der Ruhezeit öffnen, Rückkehr durch Berührung und weiter
laufenden Fotorahmen prüfen. Danach automatische Ruhezeit/Fotorahmen prüfen.

## Lokale Namensauflösung

`privatebar.local` muss am Pi auf `127.0.0.1` auflösen. Bei Cloud-init mit
`manage_etc_hosts: true` gehört `127.0.0.1 privatebar.local` zusätzlich in
`/etc/cloud/templates/hosts.debian.tmpl`, damit der Eintrag den Neustart übersteht.
Diese Betriebssystemkonfiguration wird vom Anwendungspaket nicht verändert.

## Prüfstand

JavaScript-Syntax, isolierte manuelle Anzeigeabläufe, 18 Python-Tests,
Shellsyntax, Paketinhalt, Asset-Manifest und SHA-256-Prüfsummen geprüft.
Produktionsbibliotheken werden bei bytegleichem `composer.lock` aus dem
vorhandenen 1.1.1-Paket übernommen. Keine Entwicklungsabhängigkeiten,
Zugangsdaten, Datenbank oder privaten Laufzeitdateien enthalten.
PHP-/MariaDB-/Browser-/axe-Prüfungen wurden für 1.1.2 nicht ausgeführt: PHP und
Playwright sind in dieser Umgebung nicht installiert. Die historischen Prüfungen
von 1.1.1 sind keine Abnahme von 1.1.2. Keine Pi-/Cyon-Abnahme, Signatur oder
Produktionsfreigabe; `deploy/release-approval.json` bleibt gesperrt.
