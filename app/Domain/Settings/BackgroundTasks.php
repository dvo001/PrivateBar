<?php

namespace App\Domain\Settings;

use App\Domain\Photos\PhotoCache;
use App\Domain\Recipes\Importer;
use App\Domain\Recipes\Translator;
use App\Domain\Sync\SyncClient;
use App\Infrastructure\Providers\OpenDrinks;
use App\Infrastructure\Providers\TheCocktailDb;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class BackgroundTasks
{
    public function __construct(private Settings $settings) {}

    public function tick(): void
    {
        if ($this->settings->maintenance()) {
            return;
        }
        $lock = Cache::lock('privatebar-tick', 300);
        if (! $lock->get()) {
            return;
        }
        try {
            if (config('privatebar.mode') === 'pi') {
                $this->pi();
            } else {
                $this->cloud();
            }
        } finally {
            $lock->release();
        }
    }

    private function pi(): void
    {
        $last = $this->settings->get('sync_last_attempt');
        $failed = $this->settings->get('sync_error');
        if ($this->settings->get('sync_requested') || ! $last || now()->diffInSeconds(Carbon::parse($last), true) >= ($failed ? 60 : 600)) {
            $this->settings->set('sync_requested', false);
            $this->settings->set('sync_last_attempt', now()->toIso8601String());
            try {
                app(SyncClient::class)->run();
            } catch (\Throwable) { /* Status steht in den Einstellungen. */
            }
        }
        $success = $this->settings->get('photo_index_success');
        if ($this->settings->get('photo_index_pending') || ! $success || now()->diffInHours(Carbon::parse($success), true) >= 24) {
            try {
                app(PhotoCache::class)->refresh();
            } catch (\Throwable) {
                $this->settings->set('photo_error', 'Fotoquelle nicht erreichbar. Bereits gespeicherte Fotos bleiben verfügbar.');
            }
        }
    }

    private function cloud(): void
    {
        if (! config('privatebar.providers_enabled')) {
            return;
        }
        $hour = (int) $this->settings->get('import_hour', 4, false);
        $interval = (int) $this->settings->get('import_frequency_hours', 24, false);
        $last = $this->settings->get('import_complete');
        $due = (! $last && now()->timezone('Europe/Zurich')->hour === $hour) || ($last && now()->diffInHours(Carbon::parse($last), true) >= $interval);
        if ($due) {
            $this->settings->set('import_pending', true);
        }
        if ($this->settings->get('import_pending')) {
            try {
                $provider = $this->settings->get('import_provider', 'cocktaildb');
                $cursor = $this->settings->get('import_cursor', '');
                $batch = app($provider === 'cocktaildb' ? TheCocktailDb::class : OpenDrinks::class)->batch($cursor);
                foreach ($batch['recipes'] as $dto) {
                    app(Importer::class)->ingest($dto);
                }
                $this->settings->set('import_cursor', $batch['cursor']);
                if ($batch['complete']) {
                    if ($provider === 'cocktaildb') {
                        $this->settings->set('import_provider', 'opendrinks');
                        $this->settings->set('import_cursor', '');
                    } else {
                        $this->settings->set('import_pending', false);
                        $this->settings->set('import_complete', now()->toIso8601String());
                        $this->settings->set('import_provider', 'cocktaildb');
                    }
                }
                $this->settings->set('import_error', null);
            } catch (\Throwable) {
                $this->settings->set('import_error', 'Rezeptimport nicht abgeschlossen. Anbieter-Konfiguration und Verbindung prüfen. Der nächste Lauf setzt fort.');
            }
        }
        foreach (DB::table('recipes')->where('translation_pending', true)->where('translation_manual', false)->limit(3)->pluck('id') as $id) {
            try {
                app(Translator::class)->one($id);
            } catch (\Throwable) {
                $this->settings->set('import_error', 'Übersetzungen ausstehend. Azure-Konfiguration und Verbindung prüfen.');
                break;
            }
        }
    }
}
