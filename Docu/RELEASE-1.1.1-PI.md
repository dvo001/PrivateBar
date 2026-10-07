# PrivateBar 1.1.1 für den Raspberry Pi

Manuelles Pi-Update von 1.1.0. Cyon 1.1.0 bleibt kompatibel; keine neue
Datenbankmigration und keine Änderung an API-/Sync-Schema 1.

## Änderungen

- Schriften und Bedienflächen in der grossen Ansicht um ungefähr ein Drittel
  vergrössert; breitere Seitennavigation und drei Rezeptkarten nebeneinander.
- Rechter Seiten-Scrollbalken in Chromium 32 Pixel breit.
- Verdeckte PIN-Eingabe mit eigenem Ziffernblock am lokal erkannten Pi-Bildschirm.
- Lokale Einstellungen: PIN-Popup vor Speichern, Tests und weiteren geschützten
  Aktionen; Abbrechen erhält die Einstellungen, jede Aktion verlangt eine neue PIN.
- SMB-Fotoquelle wird nach Neustart automatisch wieder eingebunden. Der
  Releaseinstaller aktualisiert den root-eigenen SMB-Helfer mit.
- Kiosk verwendet Wayland-Eingabeoptionen und wartet vor Chromium-Start auf die
  erreichbare HTTPS-PIN-Seite. So wird ein zu früher Browserstart vermieden.

## Bestehenden Pi aktualisieren

Alle Paketdateien liegen unter `artifacts/1.1.1/`. Kopiere das Tarball, seine
`.sha256`-Datei und `install-pi-release.sh` zusammen nach `/home/pbar/Downloads`.

```bash
cd /home/pbar/Downloads
sha256sum -c privatebar-1.1.1-pi-installation.tar.gz.sha256
sudo bash install-pi-release.sh /home/pbar/Downloads/privatebar-1.1.1-pi-installation.tar.gz
```

Der Installer erhält die gemeinsame `.env`, `APP_KEY`, Datenbank, Einstellungen,
Zugangsdaten, Foto-Cache und `storage`. Er erstellt einen neuen Releasepfad,
baut Caches am endgültigen Pfad und prüft die Anwendung vor dem Symlinkwechsel.
Der vorhandene Tick-Timer behält seinen Aktivierungszustand. Unter der Standard-
Installationswurzel wird der SMB-Helfer root-eigen nach `/usr/local/lib/privatebar`
kopiert. Der bestehende SMB-Timer und das grafische Kiosk-Autostartkonto müssen
bereits eingerichtet sein. Composer und Node werden auf dem Pi nicht benötigt.

Bei Fehlern stoppen und die Installerausgabe prüfen. Nach erfolgreichem Update:

```bash
sudo reboot
```

Nach mindestens einer Minute prüfen:

```bash
findmnt -t cifs -o TARGET,SOURCE,FSTYPE
```

PrivateBar muss Version 1.1.1 anzeigen. PIN-Ziffernblock, Speichern-/Test-Popup,
Abbrechen, lokale Einstellungen und Fotorahmen prüfen.

## Betriebssystem-Einstellungen bleiben erhalten

`privatebar.local` muss im Kiosk direkt auf `127.0.0.1` auflösen. Ein dauerhaft
beschreibbares Root-Dateisystem ist erforderlich; `overlayroot=tmpfs` würde
Änderungen nach einem Neustart verwerfen. Die bereits angepasste Squeekboard-
Installation wird durch dieses Anwendungspaket nicht ersetzt. Für andere
Text-/Passwortfelder bleibt diese Systemtastatur relevant.

## Prüfstand

Details und Grenzen stehen in IMPLEMENTATION.md und Checks/CHECKS.md.
Die Pakete sind manuell einspielbar und enthalten Produktionsbibliotheken aus
dem geprüften 1.1.0-Paket bei unverändertem composer.lock. Keine .env, Datenbank,
privaten Bilder oder Laufzeitcaches sind enthalten. Keine Signatur, kein Git-Tag
und keine automatische Updatefreigabe. Die Pi-Gesamtabnahme von 1.1.1 bleibt
bis zur Installation und Prüfung des Pakets offen.
