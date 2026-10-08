# PrivateBar

Pi-Version 1.1.3: [Pi-Paket und Update](Docu/RELEASE-1.1.3-PI.md). Cyon-Version 1.1.0: [Cyon installieren/aktualisieren](Docu/INSTALLATION-1.1-CYON.md) · [Pi installieren/aktualisieren](Docu/INSTALLATION-1.1-PI.md) · [Paketübersicht](Docu/RELEASE-1.1.md).

Private Hausbar für Raspberry Pi und Cyon: PHP 8.3, Laravel 13, Blade und MariaDB.
Dunkle deutsche Touchoberfläche, lokale Kernfunktionen ohne Internet, keine SPA,
kein Redis und keine dauerhaften Laravel-Worker.

Die verbindliche Spezifikation steht in [Docu/AGENTS.md](Docu/AGENTS.md).
[Umsetzungsstand und Prüfgrenzen](Docu/IMPLEMENTATION.md) unterscheiden lokale
Nachweise von der noch ausstehenden Abnahme auf den Zielgeräten.

## Lokal starten

Voraussetzungen: PHP 8.3 mit PDO MySQL, GD, DOM/XML, Mbstring, cURL, OpenSSL,
Fileinfo und ZIP; Composer 2; eine leere MariaDB-Datenbank und ein eigener DB-Benutzer.

```sh
composer install
cp .env.example .env
```

In `.env` die Datenbankzugänge setzen. Für den Entwicklungsserver auf dem eigenen
Rechner `APP_URL=http://127.0.0.1:8000` und `SESSION_SECURE_COOKIE=false` setzen.
Auf Pi und Cyon sind HTTPS und sichere Cookies verpflichtend.

```sh
php artisan key:generate
php artisan migrate --seed
php artisan privatebar:pin
php tools/build.php
php artisan serve --host=127.0.0.1
```

Es gibt keine Standard-PIN, kein Standardkonto und keine öffentliche Registrierung.
Der Seeder legt Zutaten, gerichtete Ersatzregeln und acht eigene Startrezepte an,
aber keine Flaschen. Der Anwendungsschlüssel darf nach der Einrichtung nicht
ungeplant ersetzt werden, da er unter anderem lokale SMB-Zugangsdaten schützt.

Für Cyon `PRIVATEBAR_MODE=cloud` setzen und das erste Konto per SSH erstellen:

```sh
php artisan privatebar:member person@example.ch "Vorname"
```

Externe Quellen bleiben bis zur Einrichtung von `PRIVATEBAR_PROVIDERS_ENABLED`
und den nötigen Anbieterzugängen deaktiviert. Offline-Rezepte und manuelle
Produkterfassung benötigen diese Dienste nicht.

## Prüfen

```sh
php tools/build.php
composer check
python3 tests/Unit/test_monitor.py
```

PHPUnit verwendet standardmässig eine isolierte SQLite-Datenbank im Arbeitsspeicher.
Die CI führt dieselbe Suite zusätzlich auf MariaDB aus. Für eigene MariaDB-Tests
nur eine separate Testdatenbank verwenden; `RefreshDatabase` löscht deren Tabellen.

Browserprüfungen sind optional und benötigen Node nur in der Entwicklungsumgebung:

```sh
npm ci
npx playwright install chromium
PRIVATEBAR_TEST_URL=http://127.0.0.1:8000 PRIVATEBAR_TEST_PIN=DEINE_TEST_PIN npm run test:browser
```

`tests/Browser/flows.cjs` prüft zusätzlich schreibende Abläufe und erzeugt Testdaten.
Es darf nur gegen eine dafür angelegte Testinstanz laufen.

## Betrieb und Gestaltung

- [Installation, Kiosk, SMB, Updates und Wiederherstellung](Docu/DEPLOYMENT.md)
- [Architektur und Synchronisationsprotokoll](Docu/ARCHITECTURE.md)
- [Funktionsmanual](Docu/PRIVATEBAR-MANUAL.md)
- [A3-Poster als PDF](Docu/Brand/PrivateBar-A3.pdf)
- [A3-Poster, 300 dpi](Docu/Brand/PrivateBar-A3-300dpi.png)
- [Skalierbare Postermasterdatei](Docu/Brand/PrivateBar-A3-master.svg)

Die Anwendung wird ausschliesslich über `public/index.php` bereitgestellt.
Ein Push oder Merge löst keine Bereitstellung aus.
