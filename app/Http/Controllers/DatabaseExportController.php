<?php

namespace App\Http\Controllers;

use App\Domain\Backups\DatabaseExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Throwable;

final class DatabaseExportController
{
    public function download(Request $request, DatabaseExport $export)
    {
        abort_unless(config('privatebar.mode') === 'cloud', 403);
        $data = $request->validate(['password' => 'required|string|max:1024']);
        $user = $request->user();
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['password' => 'Das Passwort ist nicht korrekt.']);
        }
        try {
            $path = $export->create();
        } catch (Throwable) {
            // Datenbank-Exceptions können SQL und Daten enthalten; nicht weiterreichen.
            throw ValidationException::withMessages(['export' => 'Export nicht erstellt. Datenbanktyp, freien Speicher und Serverkonfiguration prüfen.']);
        }

        return response()->download($path, 'privatebar-datenbank-'.now()->timezone('Europe/Zurich')->format('Y-m-d-His').'.sql',
            ['Content-Type' => 'application/sql', 'Cache-Control' => 'no-store, private'])->deleteFileAfterSend(true);
    }
}
