<?php

use Symfony\Component\Process\Process;

// php tools/release-manifest.php VERSION ARCHIVE HTTPS_URL PRIVATE_KEY OUTPUT
if ($argc !== 6) {
    fwrite(STDERR, "Version, Archiv, HTTPS-Adresse, privater Signaturschlüssel und Ausgabedatei angeben.\n");
    exit(1);
}
require dirname(__DIR__).'/vendor/autoload.php';
[, $version, $archive, $url, $keyPath, $output] = $argv;
if (! preg_match('/^\d+\.\d+\.\d+$/D', $version) || ! is_file($archive) || ! str_starts_with($url, 'https://')) {
    exit(1);
}
foreach ([['vendor/bin/pint', '--test'], ['vendor/bin/phpstan', 'analyse', '--memory-limit=512M'], ['vendor/bin/phpunit', '--fail-on-risky', '--fail-on-warning']] as $check) {
    $process = new Process([PHP_BINARY, ...$check], dirname(__DIR__));
    $process->setTimeout(300);
    $process->mustRun();
}
$verification = json_decode(file_get_contents(dirname(__DIR__).'/deploy/release-approval.json'), true, 512, JSON_THROW_ON_ERROR);
if (($verification['version'] ?? null) !== $version || ! ($verification['pi_verified'] ?? false) || ! ($verification['cyon_verified'] ?? false) || ! ($verification['backward_compatible_migrations'] ?? false)) {
    fwrite(STDERR, "Release gesperrt: Zielumgebungen und Migrationskompatibilität sind noch nicht bestätigt.\n");
    exit(1);
}
$payload = json_encode(['version' => $version, 'url' => $url, 'sha256' => hash_file('sha256', $archive), 'bytes' => filesize($archive), 'php' => '8.3', 'api_min' => 1, 'api_max' => 1, 'backward_compatible_migrations' => true, 'checks_passed' => true], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
$key = openssl_pkey_get_private(file_get_contents($keyPath));
if (! $key || ! openssl_sign($payload, $signature, $key, OPENSSL_ALGO_SHA256)) {
    fwrite(STDERR, "Signieren fehlgeschlagen.\n");
    exit(1);
}
file_put_contents($output, json_encode(['payload' => base64_encode($payload), 'signature' => base64_encode($signature)], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n");
echo "Signiertes Manifest erstellt.\n";
