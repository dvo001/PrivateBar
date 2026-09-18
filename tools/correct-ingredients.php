<?php

use Illuminate\Contracts\Console\Kernel;
use PrivateBar\Correction\Correction;

// Ausschliesslich über PHP CLI/Cron, niemals als Web-Endpunkt.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$options = array_slice($argv, 1);
if (array_diff($options, ['--apply', '--categories-only', '--disable-automatic-alcohol'])) {
    fwrite(STDERR, "Aufruf: php tools/correct-ingredients.php [--apply] [--categories-only] [--disable-automatic-alcohol]\n");
    exit(1);
}
if (in_array('--categories-only', $options, true) && in_array('--disable-automatic-alcohol', $options, true)) {
    fwrite(STDERR, "Kategorienlauf und automatische Zutaten getrennt ausführen.\n");
    exit(1);
}
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
require __DIR__.'/ingredient-correction/Correction.php';
$lock = fopen(storage_path('app/private/ingredient-correction.lock'), 'c');
if (! $lock || ! flock($lock, LOCK_EX | LOCK_NB)) {
    fwrite(STDERR, "Ein Korrekturlauf ist bereits aktiv oder die Sperrdatei ist nicht zugänglich.\n");
    exit(1);
}
try {
    $plan = json_decode(file_get_contents(__DIR__.'/ingredient-correction/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
    $apply = in_array('--apply', $options, true);
    $report = (new Correction)->run($plan, $apply, in_array('--categories-only', $options, true), in_array('--disable-automatic-alcohol', $options, true));
    echo ($apply ? 'ANGEWENDET' : 'VORSCHAU – keine Daten gespeichert').': '.count($report)." Änderungen\n";
    foreach ($report as $line) {
        echo $line."\n";
    }
    foreach ($plan['review'] as $line) {
        echo 'MANUELL PRÜFEN: '.$line."\n";
    }
} catch (Throwable $exception) {
    // Datenbank-Ausnahmen können Verbindungsdetails enthalten.
    fwrite(STDERR, 'ABBRUCH – keine Änderungen gespeichert. '.($exception instanceof RuntimeException && ! $exception instanceof PDOException ? $exception->getMessage() : 'Technischer Fehler; lokale Prüfung erforderlich.')."\n");
    exit(1);
} finally {
    flock($lock, LOCK_UN);
    fclose($lock);
}
