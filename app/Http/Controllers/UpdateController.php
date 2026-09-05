<?php

namespace App\Http\Controllers;

use App\Domain\Access\AccessGuard;
use App\Domain\Settings\Settings;
use App\Domain\Updates\ReleaseVerifier;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class UpdateController
{
    private function pin(Request $request, AccessGuard $guard): void
    {
        if (! $guard->pin((string) $request->input('pin'))) {
            throw ValidationException::withMessages(['pin' => 'Die PIN ist nicht korrekt.']);
        }
    }

    public function check(Request $request, AccessGuard $guard, ReleaseVerifier $verifier, Settings $settings)
    {
        $this->pin($request, $guard);
        try {
            $release = $verifier->latest();
            $settings->set('update_state', version_compare($release['version'], config('privatebar.version'), '>') ? 'Freigegebene Version '.$release['version'].' verfügbar.' : 'Die installierte Version ist aktuell.');
            $settings->set('update_error', null);
        } catch (\Throwable) {
            $settings->set('update_error', 'Updateprüfung fehlgeschlagen. Release-Konfiguration, Signatur und Verbindung prüfen.');
        }

        return redirect('/einstellungen');
    }

    public function install(Request $request, AccessGuard $guard, Settings $settings, ReleaseVerifier $verifier)
    {
        $this->pin($request, $guard);
        try {
            $verifier->latest();
        } catch (\Throwable) {
            throw ValidationException::withMessages(['update' => 'Es liegt kein verifiziertes Release vor. Bitte zuerst die Release-Konfiguration prüfen.']);
        }
        $settings->set('update_requested', true);
        $settings->set('update_state', 'Installation angefordert.');

        return redirect('/einstellungen');
    }
}
