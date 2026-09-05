<?php

namespace App\Domain\Sync;

use App\Domain\Settings\Settings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

final class SyncClient
{
    public function __construct(private Settings $settings, private Projector $projector) {}

    public function run(): void
    {
        $this->settings->assertRunning();
        if (config('privatebar.mode') !== 'pi') {
            throw new \RuntimeException('Nur der Raspberry Pi startet den Abgleich.');
        }
        $url = rtrim(config('privatebar.cloud_url'), '/');
        if (! str_starts_with($url, 'https://') || ! config('privatebar.device_token')) {
            throw new \RuntimeException('HTTPS-Serveradresse und Gerätezugang müssen eingerichtet sein.');
        }
        $lock = Cache::lock('privatebar-sync', 180);
        if (! $lock->get()) {
            return;
        }
        try {
            $this->settings->set('sync_state', 'Synchronisation läuft');
            $deadline = microtime(true) + 120;
            // Maximal fünf Netzwerkrunden; nächster Cronlauf setzt den Cursor fort.
            for ($round = 0; $round < 5; $round++) {
                $this->settings->assertRunning();
                $cursor = DB::table('sync_cursors')->where('peer', 'cloud')->first();
                $pending = DB::table('sync_events')->whereNull('confirmed_at')->orderBy('sequence')->limit(50)->get();
                $events = $pending->map(function ($e) {
                    return ['id' => $e->id, 'entity' => $e->entity, 'entity_id' => $e->entity_id, 'payload' => json_decode($e->payload, true, 512, JSON_THROW_ON_ERROR), 'deleted' => (bool) $e->deleted];
                })->all();
                foreach ($events as $event) {
                    if (microtime(true) > $deadline) {
                        $this->settings->set('sync_state', 'Weitere Daten ausstehend');

                        return;
                    }
                    $this->uploadMedia($url, $event['payload']['image_path'] ?? null);
                }
                $result = Http::withToken(config('privatebar.device_token'))->connectTimeout(3)->timeout(20)->post($url.'/api/v1/sync', ['schema_version' => 1, 'epoch' => $cursor?->epoch, 'cursor' => $cursor->cursor ?? 0, 'events' => $events])->throw()->json();
                if (($result['schema_version'] ?? null) !== 1) {
                    throw new \RuntimeException('Nicht unterstützte Serverversion.');
                }
                // Medien vor dem Cursorcommit laden, damit Abbrüche wiederholbar bleiben.
                foreach ($result['events'] as $event) {
                    if (microtime(true) > $deadline) {
                        $this->settings->set('sync_state', 'Weitere Daten ausstehend');

                        return;
                    }
                    $this->downloadMedia($url, $event['payload']['image_path'] ?? null);
                }
                $hasPending = DB::transaction(function () use ($result, $events) {
                    $this->settings->assertRunning();
                    $allowed = array_column($events, 'id');
                    DB::table('sync_events')->whereIn('id', array_intersect($result['accepted'], $allowed))->update(['confirmed_at' => now()]);
                    // Nicht bestätigte lokale Änderungen werden niemals von einem älteren Pull überschrieben.
                    if (DB::table('sync_events')->whereNull('confirmed_at')->exists()) {
                        return true;
                    }
                    foreach ($result['events'] as $event) {
                        if (DB::table('sync_inbox')->where('event_id', $event['id'])->exists()) {
                            continue;
                        }
                        $p = $this->projector->validate($event['entity'], $event['entity_id'], $event['payload'], false);
                        $this->projector->apply($event['entity'], $event['entity_id'], $p, (bool) $event['deleted']);
                        DB::table('sync_inbox')->insert(['event_id' => $event['id'], 'sequence' => $event['sequence'], 'created_at' => now()]);
                        DB::table('audit_entries')->insert(['actor' => $event['actor'], 'action' => 'received:'.$event['entity'], 'entity_id' => $event['entity_id'], 'details' => json_encode($p, JSON_THROW_ON_ERROR), 'created_at' => now()]);
                        if ($event['deleted']) {
                            DB::table('sync_tombstones')->updateOrInsert(['entity' => $event['entity'], 'entity_id' => $event['entity_id']], ['version' => $event['version'], 'created_at' => now()]);
                        }
                    }
                    DB::table('sync_cursors')->updateOrInsert(['peer' => 'cloud'], ['cursor' => $result['cursor'], 'epoch' => $result['epoch']]);

                    return false;
                });
                if (! $hasPending && ! $result['has_more']) {
                    $this->settings->set('sync_last_success', now()->toIso8601String());
                    $this->settings->set('sync_state', 'Aktuell');
                    $this->settings->set('sync_error', null);

                    return;
                }
            }
            $this->settings->set('sync_state', 'Weitere Daten ausstehend');
        } catch (\Throwable $e) {
            $this->settings->set('sync_state', 'Abgleich fehlgeschlagen');
            // Keine URLs, Header oder Zugangsdaten aus HTTP-Exceptions speichern.
            $this->settings->set('sync_error', 'Abgleich nicht abgeschlossen ('.class_basename($e).'). Verbindung, Gerätezugang und Serverprotokoll prüfen.');
            throw $e;
        } finally {
            $lock->release();
        }
    }

    private function uploadMedia(string $url, ?string $path): void
    {
        if (! $path || ! preg_match('~^(recipes|products)/[a-f0-9]{64}\.webp$~D', $path) || ! Storage::disk('local')->exists($path)) {
            return;
        }
        $epoch = DB::table('sync_cursors')->where('peer', 'cloud')->value('epoch');
        $key = 'media-upload:'.hash('sha256', $url.':'.config('privatebar.device_token').':'.$epoch.':'.$path);
        if (DB::table('provider_cache')->where('key', $key)->where('expires_at', '>', now())->exists()) {
            return;
        }
        Http::withToken(config('privatebar.device_token'))->connectTimeout(3)->timeout(15)->post($url.'/api/v1/media', ['path' => $path, 'content' => base64_encode(Storage::disk('local')->get($path))])->throw();
        DB::table('provider_cache')->updateOrInsert(['key' => $key], ['payload' => 'true', 'expires_at' => now()->addDays(30)]);
    }

    private function downloadMedia(string $url, ?string $path): void
    {
        if (! $path) {
            return;
        }
        if (! preg_match('~^(recipes|products)/([a-f0-9]{64})\.webp$~D', $path, $m)) {
            throw new \RuntimeException('Ungültiger Medienpfad.');
        }
        if (Storage::disk('local')->exists($path)) {
            return;
        }
        $data = Http::withToken(config('privatebar.device_token'))->connectTimeout(3)->timeout(15)->get($url.'/api/v1/media', ['path' => $path])->throw()->body();
        if (strlen($data) > 3 * 1024 * 1024 || ! hash_equals($m[2], hash('sha256', $data))) {
            throw new \RuntimeException('Medienprüfsumme stimmt nicht.');
        }
        Storage::disk('local')->put($path, $data);
    }
}
