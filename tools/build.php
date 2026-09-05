<?php

// Deterministischer Build ohne Node-Laufzeit oder externe Browserressourcen.
$root = dirname(__DIR__);
@mkdir($root.'/public/assets', 0755, true);
$files = [$root.'/resources/css/app.css', $root.'/resources/js/app.js', $root.'/resources/js/vendor/zxing-browser.min.js', ...glob($root.'/resources/art/*.svg'), ...glob($root.'/resources/fonts/*.woff2')];
$manifest = [];
foreach ($files as $file) {
    $content = file_get_contents($file);
    if (! str_ends_with($file, 'woff2')) {
        $content = str_replace("\r\n", "\n", $content);
    }
    if (str_ends_with($file, '.css')) {
        $content = preg_replace('~/\*.*?\*/~s', '', $content);
    }
    $target = basename($file);
    file_put_contents($root.'/public/assets/'.$target, $content);
    $manifest[$target] = hash('sha256', $content);
}
ksort($manifest);
file_put_contents($root.'/public/assets/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
echo "Lokale Frontend-Ressourcen gebaut.\n";
