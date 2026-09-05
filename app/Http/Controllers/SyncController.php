<?php

namespace App\Http\Controllers;

use App\Domain\Access\MemberLinks;
use App\Domain\Sync\SyncServer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final class SyncController
{
    private function device(Request $request): object
    {
        abort_unless(config('privatebar.mode') === 'cloud' && $request->secure(), 403);
        $token = $request->bearerToken();
        abort_unless($token !== null && $token !== '', 401);
        $device = DB::table('devices')->where('token_hash', hash('sha256', $token))->whereNull('revoked_at')->first();
        abort_unless($device !== null, 401, 'Gerätezugang ungültig oder widerrufen.');

        return $device;
    }

    public function exchange(Request $request, SyncServer $server)
    {
        $device = $this->device($request);
        abort_if(strlen($request->getContent()) > 4 * 1024 * 1024, 413);

        return response()->json($server->exchange($request->all(), $device));
    }

    public function media(Request $request)
    {
        $this->device($request);
        $data = $request->validate(['path' => 'required|regex:~^(recipes|products)/[a-f0-9]{64}\\.webp$~D', 'content' => 'sometimes|required|string|max:4194304']);
        if ($request->isMethod('post')) {
            $bytes = base64_decode($data['content'] ?? '', true);
            $hash = pathinfo(basename($data['path']), PATHINFO_FILENAME);
            abort_unless($bytes && strlen($bytes) <= 3 * 1024 * 1024 && hash_equals($hash, hash('sha256', $bytes)), 422, 'Bildprüfsumme ungültig.');
            $info = @getimagesizefromstring($bytes);
            abort_unless($info && $info['mime'] === 'image/webp' && $info[0] * $info[1] <= 4000000, 422, 'Bildformat ungültig.');
            Storage::disk('local')->put($data['path'], $bytes);

            return response()->json(['stored' => true]);
        }
        abort_unless(Storage::disk('local')->exists($data['path']), 404);

        return response()->file(Storage::disk('local')->path($data['path']), ['Content-Type' => 'image/webp']);
    }

    public function recovery(Request $request, MemberLinks $links)
    {
        $this->device($request);
        $data = $request->validate(['email' => 'required|email|max:255']);
        abort_if(DB::table('sessions')->whereNotNull('user_id')->where('last_activity', '>', now()->subMinutes((int) config('session.lifetime'))->timestamp)->exists(), 409, 'Ein Mitglied ist noch angemeldet und kann den Reset-Link erstellen.');

        return response()->json($links->issue('reset', $data['email'], null));
    }
}
