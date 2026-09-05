<?php

namespace App\Http\Controllers;

use App\Domain\Photos\PhotoCache;
use App\Domain\Settings\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final class PhotoController
{
    public function next(Request $request, PhotoCache $photos, Settings $settings)
    {
        abort_unless(config('privatebar.mode') === 'pi', 404);
        $photo = $photos->next($request->input('previous'));

        return response()->json(['id' => $photo?->id, 'url' => $photo ? url('/fotorahmen/bild/'.$photo->id) : null, 'seconds' => $settings->get('frame_seconds', 10), 'fade' => $settings->get('frame_fade', 1)]);
    }

    public function image(string $id)
    {
        abort_unless(config('privatebar.mode') === 'pi', 404);
        $photo = DB::table('photo_cache')->where('id', $id)->first();
        abort_unless($photo && Storage::disk('local')->exists($photo->cache_path), 404);

        return response()->file(Storage::disk('local')->path($photo->cache_path), ['Content-Type' => 'image/webp']);
    }

    public function media(string $folder, string $file)
    {
        abort_unless(in_array($folder, ['recipes', 'products'], true) && preg_match('/^[a-f0-9]{64}\.webp$/D', $file), 404);
        $path = $folder.'/'.$file;
        abort_unless(Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path), ['Content-Type' => 'image/webp']);
    }
}
