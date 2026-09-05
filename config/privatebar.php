<?php

return [
    'mode' => env('PRIVATEBAR_MODE', 'pi'),
    'timezone' => 'Europe/Zurich',
    'instance_id' => env('PRIVATEBAR_INSTANCE_ID', 'pi-housebar'),
    'pin_hash' => env('PRIVATEBAR_PIN_HASH'),
    'boot_id_path' => '/proc/sys/kernel/random/boot_id',
    'cloud_url' => env('PRIVATEBAR_CLOUD_URL', 'https://privatebar.vonrufs.ch'),
    'device_token' => env('PRIVATEBAR_DEVICE_TOKEN'),
    'providers_enabled' => env('PRIVATEBAR_PROVIDERS_ENABLED', false),
    'cocktaildb_key' => env('COCKTAILDB_KEY'),
    'azure_key' => env('AZURE_TRANSLATOR_KEY'),
    'azure_region' => env('AZURE_TRANSLATOR_REGION', 'switzerlandnorth'),
    'off_user_agent' => 'PrivateBar/1.0 (https://privatebar.vonrufs.ch; private household)',
    'photo_mount' => env('PRIVATEBAR_PHOTO_MOUNT', '/mnt/privatebar-photos'),
    'release_public_key' => env('PRIVATEBAR_RELEASE_PUBLIC_KEY'),
    'release_token' => env('PRIVATEBAR_RELEASE_TOKEN'),
    'release_manifest' => env('PRIVATEBAR_RELEASE_MANIFEST'),
    'version' => '0.1.0',
    'schema_version' => 1,
];
