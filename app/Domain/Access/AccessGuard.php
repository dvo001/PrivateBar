<?php

namespace App\Domain\Access;

use App\Domain\Settings\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class AccessGuard
{
    public function attempt(string $key, int $limit, int $minutes, callable $verify): bool
    {
        // Die Datenbank, nicht Session oder Cache, hält Sperren über Neustarts fest.
        $result = DB::transaction(function () use ($key, $limit, $minutes, $verify) {
            $key = hash('sha256', $key);
            DB::table('access_attempts')->insertOrIgnore(['key' => $key, 'failures' => 0]);
            $row = DB::table('access_attempts')->where('key', $key)->lockForUpdate()->first();
            if ($row->locked_until && now()->lt($row->locked_until)) {
                return 'locked';
            }
            if ($row->locked_until) {
                $row->failures = 0;
            }
            if ($verify()) {
                DB::table('access_attempts')->where('key', $key)->update(['failures' => 0, 'locked_until' => null]);

                return true;
            }
            $failures = $row->failures + 1;
            DB::table('access_attempts')->where('key', $key)->update(['failures' => $failures, 'locked_until' => $failures >= $limit ? now()->addMinutes($minutes) : null]);

            return $failures >= $limit ? 'locked' : false;
        });
        if ($result === 'locked') {
            throw ValidationException::withMessages(['access' => "Zugang gesperrt. Bitte nach {$minutes} Minuten erneut versuchen."]);
        }

        return $result;
    }

    public function pin(string $pin): bool
    {
        return $this->attempt('shared-kiosk-pin', 8, 5, function () use ($pin) {
            $hash = app(Settings::class)->get('pin_hash', config('privatebar.pin_hash'));

            return preg_match('/^[0-9]{6}$/D', $pin) && $hash && Hash::check($pin, $hash);
        });
    }

    public function bootId(): string
    {
        $path = config('privatebar.boot_id_path');

        return is_readable($path) ? trim(file_get_contents($path)) : 'unknown-boot';
    }
}
