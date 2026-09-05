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
