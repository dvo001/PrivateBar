# Korrektur des Bildabgleichs vom 18. September 2026

Der Medien-Endpunkt `/api/v1/media` enthielt eine als Zeichenkette formulierte
Validierungsregel mit `recipes|products`. Laravel teilte diese Zeichenkette am
senkrechten Strich auf. Dadurch erreichte ein unvollständiger regulärer Ausdruck
die Prüfung und verursachte `preg_match(): No ending delimiter '~' found`.

Die Regel wird jetzt als Array übergeben. Beide erlaubten Verzeichnisse bleiben
zulässig; SHA-256-Dateinamen und die Endung `.webp` bleiben verpflichtend.
Keine Änderung an API, Datenbank oder Umgebungskonfiguration.

## Auf Cyon anwenden

Die korrigierte Datei `app/Http/Controllers/SyncController.php` per
my.cyon-Dateimanager nach
`/home/silberf1/public_html/pbar/app/Http/Controllers/SyncController.php`
übertragen und die vorhandene Datei ersetzen. Danach auf dem Pi unter
Einstellungen «Jetzt synchronisieren» auslösen und die neuesten Logeinträge
prüfen. Die gleiche Datei beim nächsten Codeabgleich auch auf den Pi übertragen.

Der Fehler betrifft den Bildabgleich. Eine erfolgreiche Korrektur bestätigt
weder die Azure-Verbindung noch die Behebung eines separaten APP_KEY-Fehlers.
