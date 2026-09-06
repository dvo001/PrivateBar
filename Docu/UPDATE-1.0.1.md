# PrivateBar 1.0.1 – Mailversand auf Cyon aktivieren

Dieses Update ergänzt SMTP-Einladungen und die separate Bestätigung der
E-Mail-Adresse. Die Bar ist online erst nach der Bestätigung zugänglich.
Das gilt auch für bestehende Konten mit leerem `email_verified_at`.
Der Pi-Kiosk und die Geräte-API benötigen weiterhin keine E-Mail-Bestätigung.

Das Paket `privatebar-1.0.1-cyon-update.zip` ist für die bereits installierte
Version 1.0.0 bestimmt. Es enthält geänderte Programmdateien, neue Mailvorlagen
und Dokumentation. Es enthält keine `.env`, privaten Daten oder Zugangsdaten.
Es ist kein signiertes Pi-Update. Die Produktionsfreigabe und die Prüfung mit
dem echten SMTP-Konto stehen noch aus.

## 1. SMTP vor dem Update konfigurieren

In my.cyon ein Mailkonto anlegen, beispielsweise privatebar@vonrufs.ch.
Die tatsächlichen Zugangsdaten in der bestehenden Cyon-`.env` ergänzen:

```dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=smtps
MAIL_HOST=mail.cyon.ch
MAIL_PORT=465
MAIL_USERNAME=privatebar@vonrufs.ch
MAIL_PASSWORD="DAS_PASSWORT_DES_MAILKONTOS"
MAIL_FROM_ADDRESS=privatebar@vonrufs.ch
MAIL_FROM_NAME="PrivateBar"
MAIL_TIMEOUT=15
```

`APP_URL=https://privatebar.vonrufs.ch` kontrollieren. Ein vorhandenes MAIL_URL
entfernen, damit es diese Einzelwerte nicht übersteuert. Den vorhandenen
APP_KEY und sämtliche Datenbank-/Gerätezugänge erhalten. Das Passwort gehört
nicht in einen Cron-Befehl. SMTP-Einstellungen: [Cyon](https://www.cyon.ch/support/a/e-mail-konto-einrichten-imap-pop3-und-smtp-einstellungen).

## 2. Programmdateien aktualisieren

Vorher eine wiederherstellbare Kopie der bisherigen Programmdateien sichern.
Die vorhandene Cyon-Datenbanksicherung gemäss DEPLOYMENT.md prüfen.
Den normalen schedule:run-Cronjob für die kurze Aktualisierung pausieren.

Mit einem temporären my.cyon-Cronjob den Laravel-Wartungsmodus aktivieren:

```text
/usr/bin/php83 /home/silberf1/public_html/pbar/artisan down
```

Nach erfolgreicher Ausführung diesen temporären Cronjob sofort entfernen.
Das ZIP im Projektordner `/home/silberf1/public_html/pbar` entpacken und die
enthaltenen Programmdateien ersetzen. Das Archiv enthält die Verzeichnisse
app, config, routes und resources direkt in der Archivwurzel.
Die vorhandene `.env` und storage bleiben erhalten. Das Erstinstallationsskript
cyon-install.php nicht erneut ausführen.

## 3. Caches erneuern

Mit einem weiteren temporären Cronjob ausführen:

```text
/usr/bin/php83 /home/silberf1/public_html/pbar/artisan optimize
```

Nach Erfolg entfernen. Das erstellt unter anderem Konfigurations-, Routen- und
View-Caches für die neue Version. Kein Composer-Lauf und keine neue Migration
sind erforderlich: email_verified_at existiert bereits in Version 1.0.0.

Danach separat ausführen:

```text
/usr/bin/php83 /home/silberf1/public_html/pbar/artisan up
```

Nach Erfolg auch diesen Cronjob entfernen und den normalen schedule:run-Cronjob
wieder aktivieren. PHP- und Projektpfade sind anhand des bisher verwendeten
Cyon-Pfads angegeben und müssen zum tatsächlichen Hosting passen.

## 4. Mailfluss prüfen

1. Mit dem bestehenden Mitglied anmelden. Falls die Adresse noch unbestätigt
   ist, wird die Bestätigungsseite angezeigt und eine E-Mail angefordert.
2. Postfach und Spamordner prüfen. Den Bestätigungslink innerhalb von 30 Minuten
   öffnen. Auf einem anderen Gerät zuvor mit demselben Konto anmelden.
3. Bei SMTP-Fehlern die Konfiguration korrigieren, config:cache über einen
   temporären Cronjob erneuern und nach einer Minute erneut senden.
4. Nach Bestätigung unter Einstellungen → Mitglieder eine Person einladen.
5. Die Person nimmt die Einladung an und bestätigt anschliessend ihre Adresse
   über die zweite E-Mail.

Es wird kein Konto automatisch als bestätigt markiert. Bei einem Mailfehler
bleiben bestehende Konten erhalten. Fehlgeschlagene Einladungen werden widerrufen
und können neu erstellt werden. Resetlinks bleiben manuell teilbar.

Die Laravel-Wartung während dieses Dateiupdates ist nicht der in DEPLOYMENT.md
beschriebene Wiederherstellungsmodus für Datenbanken. Bei einer Datenbank-
wiederherstellung muss weiterhin der dort dokumentierte Ablauf verwendet werden.
