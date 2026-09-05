<?php

namespace App\Domain\Settings;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

final class Settings
{
    public function get(string $key, mixed $default = null, bool $local = true): mixed
    {
        $value = DB::table($local ? 'local_settings' : 'shared_settings')->where('key', $key)->value('value');

        return $value === null ? $default : json_decode($value, true, 512, JSON_THROW_ON_ERROR);
    }

    public function set(string $key, mixed $value, bool $local = true): void
    {
        DB::table($local ? 'local_settings' : 'shared_settings')->updateOrInsert(['key' => $key], [
            ...($local ? [] : ['id' => self::sharedId($key)]),
            'value' => json_encode($value, JSON_THROW_ON_ERROR), 'updated_at' => now(), 'created_at' => now(),
        ]);
    }

    public function secret(string $key): ?string
    {
        $value = $this->get($key);

        return $value ? Crypt::decryptString($value) : null;
    }

    public function setSecret(string $key, string $value): void
    {
        $this->set($key, Crypt::encryptString($value));
    }

    public function maintenance(): bool
    {
        return (bool) $this->get('maintenance', false);
    }

    public function assertRunning(): void
    {
        DB::table('local_settings')->insertOrIgnore(['key' => 'maintenance', 'value' => 'false', 'created_at' => now(), 'updated_at' => now()]);
        $value = DB::table('local_settings')->where('key', 'maintenance')->lockForUpdate()->value('value');
        if (json_decode($value, true, 512, JSON_THROW_ON_ERROR)) {
            throw new \RuntimeException('PrivateBar ist im Wartungsmodus.');
        }
    }

    public static function sharedId(string $key): string
    {
        return Uuid::uuid5(Uuid::NAMESPACE_URL, 'privatebar:setting:'.$key)->toString();
    }
}
