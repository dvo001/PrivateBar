<?php

use Symfony\Component\Process\Process;

$root = dirname(__DIR__);
if (! is_file($root.'/.env')) {
    copy($root.'/.env.example', $root.'/.env');
}
// Ein bestehender Schlüssel schützt u. a. SMB-Passwörter und wird nie automatisch rotiert.
if (preg_match('/^APP_KEY=\s*$/m', file_get_contents($root.'/.env'))) {
    require $root.'/vendor/autoload.php';
    $process = new Process([PHP_BINARY, 'artisan', 'key:generate'], $root);
    $process->mustRun();
}
echo "Umgebung vorbereitet. Bestehender Anwendungsschlüssel bleibt erhalten.\n";
