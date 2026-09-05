<?php

namespace App\Domain\Photos;

use App\Domain\Settings\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final class PhotoCache
{
    public function __construct(private Settings $settings, private ImageProcessor $images) {}

    public function refresh(int $limit = 20): void
    {
        $this->settings->assertRunning();
        if (config('privatebar.mode') !== 'pi') {
            throw new \RuntimeException('Fotos bleiben ausschliesslich auf dem Raspberry Pi.');
        }
        $root = config('privatebar.photo_mount');
        if (! is_dir($root) || ! is_readable($root)) {
            throw new \RuntimeException('Die Fotoquelle ist nicht erreichbar. Der vorhandene Cache bleibt verfügbar.');
        }
        $cursor = $this->settings->get('photo_index_cursor', '');
        $processed = 0;
        $last = $cursor;
        $more = false;
        // SPL hält lediglich den aktuellen Verzeichniszweig, niemals Bildinhalte aller Fotos.
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        $candidates = [];
        foreach ($iterator as $file) {
            if ($file->isLink() || ! $file->isFile() || ! in_array(strtolower($file->getExtension()), ['jpg', 'jpeg', 'png', 'webp'], true)) {
                continue;
            }
            $path = $file->getPathname();
            if (strcmp($path, $cursor) <= 0) {
                continue;
            }
            $candidates[$path] = $file->getMTime();
            if (count($candidates) > $limit) {
                ksort($candidates, SORT_STRING);
                array_pop($candidates);
                $more = true;
            }
        }
        ksort($candidates, SORT_STRING);
        foreach ($candidates as $path => $mtime) {
            $this->settings->assertRunning();
            $id = hash('sha256', $path);
            $last = $path;
            $old = DB::table('photo_cache')->where('id', $id)->first();
            if ($old && (int) $old->source_mtime === $mtime && Storage::disk('local')->exists($old->cache_path)) {
                continue;
            }
            try {
                $result = $this->images->compress($path, 'frame', false);
                $cap = (int) $this->settings->get('photo_cache_mb', 2048) * 1024 * 1024;
                if ($result['bytes'] > $cap) {
                    Storage::disk('local')->delete($result['path']);

                    continue;
                }
                DB::table('photo_cache')->updateOrInsert(['id' => $id], ['source_path' => $path, 'source_mtime' => $mtime, 'cache_path' => $result['path'], 'bytes' => $result['bytes'], 'last_used_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
                if ($old && $old->cache_path !== $result['path'] && ! DB::table('photo_cache')->where('cache_path', $old->cache_path)->exists()) {
                    Storage::disk('local')->delete($old->cache_path);
                }
                $processed++;
            } catch (\RuntimeException) { /* Defekte oder übergrosse Einzelbilder überspringen. */
            }
            $this->evict();
        }
        $this->settings->set('photo_index_cursor', $more ? $last : '');
        $this->settings->set('photo_index_pending', $more);
        $this->settings->set('photo_error', null);
        if (! $more) {
            $this->settings->set('photo_index_success', now()->toIso8601String());
        }
    }

    public function evict(): void
    {
        $cap = (int) $this->settings->get('photo_cache_mb', 2048) * 1024 * 1024;
        $total = (int) DB::table('photo_cache')->sum('bytes');
        while ($total > $cap) {
            $old = DB::table('photo_cache')->orderBy('last_used_at')->orderBy('id')->first();
            if (! $old) {
                break;
            }
            DB::table('photo_cache')->where('id', $old->id)->delete();
            if (! DB::table('photo_cache')->where('cache_path', $old->cache_path)->exists()) {
                Storage::disk('local')->delete($old->cache_path);
            }
            $total -= $old->bytes;
        }
    }

    public function next(?string $previous): ?object
    {
        $query = DB::table('photo_cache');
        if (DB::table('photo_cache')->count() > 1 && $previous) {
            $query->where('id', '!=', $previous);
        }
        $count = (clone $query)->count();
        if (! $count) {
            return null;
        }
        $row = $query->orderBy('id')->offset(random_int(0, $count - 1))->first();
        if (! Storage::disk('local')->exists($row->cache_path)) {
            DB::table('photo_cache')->where('id', $row->id)->delete();

            return null;
        }
        DB::table('photo_cache')->where('id', $row->id)->update(['last_used_at' => now()]);

        return $row;
    }
}
