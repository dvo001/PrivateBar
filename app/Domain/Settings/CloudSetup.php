<?php

namespace App\Domain\Settings;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

final class CloudSetup
{
    /** @param array<string, string> $input */
    public function initialize(array $input, string $token): void
    {
        if (config('privatebar.mode') !== 'cloud'
            || ! filter_var($input['email'] ?? '', FILTER_VALIDATE_EMAIL)
            || mb_strlen($input['password'] ?? '') < 12
            || trim($input['name'] ?? '') === ''
            || trim($input['device_name'] ?? '') === ''
            || ! preg_match('/^[a-f0-9]{64}$/D', $token)) {
            throw new RuntimeException('Installationskonfiguration ungültig.');
        }

        DB::transaction(function () use ($input, $token) {
            $settings = new Settings;
            $fingerprint = hash('sha256', $token);
            $existing = $settings->get('cyon_installation');
            if ($existing !== null) {
                if (! hash_equals((string) $existing, $fingerprint)) {
                    throw new RuntimeException('Andere Installation vorhanden.');
                }

                return;
            }
            if (DB::table('users')->exists() || DB::table('devices')->exists()) {
                throw new RuntimeException('Nur für eine neue Cloud-Instanz.');
            }
            $this->command('db:seed', ['--force' => true]);
            DB::table('users')->insert([
                'uuid' => (string) Str::uuid(), 'email' => mb_strtolower($input['email']),
                'name' => $input['name'], 'password' => Hash::make($input['password']),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('devices')->insert([
                'id' => (string) Str::uuid(), 'name' => $input['device_name'],
                'token_hash' => $fingerprint, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->command('privatebar:publish-state');
            $settings->set('cyon_installation', $fingerprint);
        });
    }

    /** @param array<string, mixed> $arguments */
    public function command(string $name, array $arguments = []): void
    {
        if (Artisan::call($name, $arguments + ['--no-interaction' => true]) !== 0) {
            throw new RuntimeException('Installationsschritt fehlgeschlagen.');
        }
    }
}
