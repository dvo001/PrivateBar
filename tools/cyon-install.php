<?php

use App\Domain\Settings\CloudSetup;
use App\Domain\Settings\Settings;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Never expose installation or its output through HTTP.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

ini_set('display_errors', '0');
ini_set('log_errors', '0');
umask(0077);
$stage = 'Voraussetzungen';
$root = dirname(__DIR__);
$directory = $root.'/storage/app/private/cyon-install';

// Only fixed stage labels are emitted: exception messages may contain secrets.
set_error_handler(static function (): never {
    throw new RuntimeException('Datei- oder Laufzeitfehler.');
});

/** Atomically replace private files; a crash must not truncate keys or tokens. */
function installationWrite(string $path, string $contents): void
{
    $temporary = $path.'.tmp';
    if (is_link($path) || is_link($temporary)) {
        throw new RuntimeException('Symlink nicht erlaubt.');
    }
    $file = fopen($temporary, 'wb');
    if ($file === false || ! chmod($temporary, 0600)
        || fwrite($file, $contents) !== strlen($contents) || ! fflush($file) || ! fsync($file)) {
        throw new RuntimeException('Datei konnte nicht gespeichert werden.');
    }
    fclose($file);
    if (! rename($temporary, $path)) {
        throw new RuntimeException('Datei konnte nicht ersetzt werden.');
    }
}

try {
    if (PHP_VERSION_ID < 80300) {
        throw new RuntimeException;
    }
    foreach (['ctype', 'curl', 'dom', 'fileinfo', 'filter', 'gd', 'hash', 'mbstring', 'openssl', 'pcre', 'pdo', 'pdo_mysql', 'session', 'tokenizer', 'xml'] as $extension) {
        if (! extension_loaded($extension)) {
            throw new RuntimeException;
        }
    }
    chdir($root);
    if (! is_dir($directory) || is_link($directory) || realpath($directory) !== $directory) {
        throw new RuntimeException;
    }
    chmod($directory, 0700);
    if (is_link($directory.'/lock')) {
        throw new RuntimeException;
    }
    $lock = fopen($directory.'/lock', 'c');
    if ($lock === false || ! flock($lock, LOCK_EX | LOCK_NB)) {
        echo "Installation läuft bereits.\n";
        exit(0);
    }
    if (is_file($directory.'/complete')) {
        echo "Installation bereits abgeschlossen. Cronjob entfernen.\n";
        exit(0);
    }
    require $root.'/vendor/autoload.php';
    $stage = 'Konfiguration';
    foreach ([$root.'/.env', $directory.'/input.json'] as $path) {
        if (! is_file($path) || is_link($path) || ! chmod($path, 0600)) {
            throw new RuntimeException;
        }
    }
    $input = json_decode(file_get_contents($directory.'/input.json'), true, 512, JSON_THROW_ON_ERROR);
    if (! is_array($input) || count(array_filter($input, 'is_string')) !== count($input)
        || ! filter_var($input['email'] ?? '', FILTER_VALIDATE_EMAIL)
        || mb_strlen($input['password'] ?? '') < 12
        || trim($input['name'] ?? '') === '' || trim($input['device_name'] ?? '') === '') {
        throw new RuntimeException;
    }
    $environment = file_get_contents($root.'/.env');
    $values = Dotenv\Dotenv::parse($environment);
    if (($values['PRIVATEBAR_MODE'] ?? '') !== 'cloud' || ($values['APP_ENV'] ?? '') !== 'production'
        || ($values['APP_DEBUG'] ?? '') !== 'false' || ($values['SESSION_SECURE_COOKIE'] ?? '') !== 'true'
        || ! str_starts_with($values['APP_URL'] ?? '', 'https://')
        || ($values['DB_CONNECTION'] ?? '') !== 'mariadb') {
        throw new RuntimeException;
    }
    if (is_file($root.'/bootstrap/cache/config.php')) {
        unlink($root.'/bootstrap/cache/config.php');
    }
    if (($values['APP_KEY'] ?? '') === '') {
        $key = 'base64:'.base64_encode(random_bytes(32));
        $environment = preg_replace('/^APP_KEY=.*\R?/m', '', $environment);
        installationWrite($root.'/.env', rtrim($environment)."\nAPP_KEY=".$key."\n");
    } else {
        $key = $values['APP_KEY'];
        $decoded = str_starts_with($key, 'base64:') ? base64_decode(substr($key, 7), true) : $key;
        if ($decoded === false || strlen($decoded) !== 32) {
            throw new RuntimeException;
        }
    }
    $tokenFile = $directory.'/device-token.txt';
    if (! file_exists($tokenFile)) {
        installationWrite($tokenFile, bin2hex(random_bytes(32))."\n");
    }
    if (is_link($tokenFile) || ! chmod($tokenFile, 0600)) {
        throw new RuntimeException;
    }
    $token = trim(file_get_contents($tokenFile));
    $stage = 'Laravel starten';
    $app = require $root.'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    // Laravel registers its own error handler during bootstrap; suppress secret-bearing logs.
    config(['logging.default' => 'null']);
    $setup = new CloudSetup;
    if (config('privatebar.mode') !== 'cloud' || ! $app->environment('production') || config('app.debug')) {
        throw new RuntimeException;
    }
    $stage = 'Bestehende Instanz prüfen';
    $fingerprint = Schema::hasTable('local_settings')
        ? (new Settings)->get('cyon_installation') : null;
    if ($fingerprint !== null && ! hash_equals((string) $fingerprint, hash('sha256', $token))) {
        throw new RuntimeException;
    }
    foreach (['users', 'devices'] as $table) {
        if ($fingerprint === null && Schema::hasTable($table)
            && DB::table($table)->exists()) {
            throw new RuntimeException;
        }
    }
    $stage = 'Migrationen';
    $setup->command('migrate', ['--force' => true]);
    $stage = 'Grunddaten, Mitglied und Gerätezugang';
    $setup->initialize($input, $token);
    $stage = 'Optimierung';
    $setup->command('optimize');
    $stage = 'Gesundheitsprüfung';
    $setup->command('privatebar:health');
    $stage = 'Abschluss';
    installationWrite($directory.'/complete', gmdate('c')."\n");
    unlink($directory.'/input.json');
    echo "Installation abgeschlossen. Cronjob entfernen, device-token.txt geschützt auf den Pi übernehmen und danach löschen.\n";
} catch (Throwable) {
    fwrite(STDERR, 'Installation gestoppt: '.$stage.". Konfiguration prüfen; danach erneut starten. Keine Geheimnisse protokolliert.\n");
    exit(1);
}
