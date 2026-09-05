<?php

use App\Domain\Access\AccessGuard;
use App\Domain\Bar\Inventory;
use App\Domain\Recipes\RecipeWriter;
use App\Domain\Settings\BackgroundTasks;
use App\Domain\Settings\Settings;
use App\Domain\Sync\Journal;
use App\Domain\Sync\SyncClient;
use App\Domain\Updates\Installer;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Str;

Artisan::command('privatebar:health', function () {
    DB::select('SELECT 1');
    if (! is_file(public_path('assets/manifest.json'))) {
        $this->error('Frontend-Build fehlt.');

        return 1;
    }
    $this->info('Datenbank und Frontend bereit.');

    return 0;
});
Artisan::command('privatebar:pin', function () {
    if (config('privatebar.mode') !== 'pi') {
        $this->error('Nur auf dem Raspberry Pi.');

        return 1;
    }
    $pin = $this->secret('Neue sechsstellige Kiosk-PIN');
    if (! preg_match('/^[0-9]{6}$/D', (string) $pin)) {
        $this->error('Die PIN muss genau sechs Ziffern enthalten.');

        return 1;
    }
    app(Settings::class)->set('pin_hash', Hash::make($pin));
    $this->info('PIN sicher gespeichert.');

    return 0;
});
Artisan::command('privatebar:member {email} {name}', function () {
    if (config('privatebar.mode') !== 'cloud' || DB::table('users')->exists()) {
        $this->error('Nur zum Anlegen des ersten Online-Mitglieds.');

        return 1;
    }
    $email = mb_strtolower($this->argument('email'));
    $name = $this->argument('name');
    $password = $this->secret('Passwort mit mindestens zwölf Zeichen');
    if (! filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen((string) $password) < 12) {
        $this->error('E-Mail-Adresse oder Passwort ungültig.');

        return 1;
    }
    DB::table('users')->insert(['uuid' => (string) Str::uuid(), 'email' => $email, 'name' => $name, 'password' => Hash::make($password), 'created_at' => now(), 'updated_at' => now()]);
    $this->info('Erstes Mitglied angelegt.');

    return 0;
});
Artisan::command('privatebar:device {name}', function () {
    if (config('privatebar.mode') !== 'cloud') {
        $this->error('Gerätezugänge werden auf Cyon erzeugt.');

        return 1;
    }
    $token = bin2hex(random_bytes(32));
    $id = (string) Str::uuid();
    DB::table('devices')->insert(['id' => $id, 'name' => $this->argument('name'), 'token_hash' => hash('sha256', $token), 'created_at' => now(), 'updated_at' => now()]);
    $this->line('Geräte-ID: '.$id);
    $this->line('Einmalig angezeigter Gerätezugang: '.$token);

    return 0;
});
Artisan::command('privatebar:revoke-device {id}', function () {
    DB::table('devices')->where('id', $this->argument('id'))->update(['revoked_at' => now()]);
    $this->info('Gerätezugang widerrufen.');
});
Artisan::command('privatebar:tick', function () {
    app(BackgroundTasks::class)->tick();
});
Artisan::command('privatebar:sync', function () {
    app(SyncClient::class)->run();
});
Artisan::command('privatebar:boot', function () {
    if (config('privatebar.mode') !== 'pi') {
        return 1;
    }
    $s = app(Settings::class);
    $s->set('sync_requested', true);
    $s->set('photo_index_pending', true);
    $s->set('photo_index_cursor', '');

    return 0;
});
Artisan::command('privatebar:publish-catalog', function () {
    if (config('privatebar.mode') !== 'cloud') {
        return 1;
    }
    app(Settings::class)->assertRunning();
    DB::transaction(function () {
        foreach (DB::table('ingredients')->get() as $i) {
            app(Journal::class)->record('ingredient', $i->id, ['name' => $i->name, 'category_id' => $i->category_id, 'automatic' => (bool) $i->automatic]);
        }
        DB::table('recipes')->orderBy('id')->chunk(100, function ($rows) {
            foreach ($rows as $r) {
                app(RecipeWriter::class)->publish($r->id);
            }
        });
    });
    $this->info('Vollständiger Katalog für den Erstabgleich veröffentlicht.');

    return 0;
});
Artisan::command('privatebar:new-epoch', function () {
    if (! app(Settings::class)->maintenance() || config('privatebar.mode') !== 'cloud') {
        $this->error('Nur auf Cyon im Wartungsmodus nach einer Wiederherstellung.');

        return 1;
    }
    DB::table('sync_cursors')->updateOrInsert(['peer' => 'server'], ['epoch' => (string) Str::uuid(), 'cursor' => 0]);
    $this->info('Neue Wiederherstellungsepoche gesetzt.');

    return 0;
});
Artisan::command('privatebar:maintenance {state}', function () {
    if (! in_array($this->argument('state'), ['on', 'off'])) {
        return 1;
    }
    if ($this->argument('state') === 'off' && config('privatebar.mode') === 'pi' && ! app(AccessGuard::class)->pin((string) $this->secret('Kiosk-PIN'))) {
        $this->error('PIN ungültig.');

        return 1;
    }
    app(Settings::class)->set('maintenance', $this->argument('state') === 'on');

    return 0;
});
Artisan::command('privatebar:install-update', function () {
    $s = app(Settings::class);
    if (! $s->get('update_requested')) {
        return 0;
    }
    $s->assertRunning();
    $s->set('update_requested', false);
    app(Installer::class)->install();

    return 0;
});
Artisan::command('privatebar:monitor-state', function () {
    if (config('privatebar.mode') !== 'pi') {
        return 1;
    }
    $s = app(Settings::class);
    $this->line(json_encode(['enabled' => (bool) $s->get('monitor_enabled', false), 'off' => $s->get('monitor_off', '23:00'), 'on' => $s->get('monitor_on', '08:00'), 'maintenance' => $s->maintenance()], JSON_THROW_ON_ERROR));

    return 0;
});
Schedule::command('privatebar:tick')->everyMinute()->withoutOverlapping(5);

Artisan::command('privatebar:smb-config', function () {
    if (config('privatebar.mode') !== 'pi') {
        return 1;
    }
    $s = app(Settings::class);
    $s->assertRunning();
    $this->line(json_encode(['requested' => (bool) $s->get('smb_mount_requested'), 'server' => $s->get('smb_server'), 'share' => $s->get('smb_share'), 'subpath' => $s->get('smb_subpath', ''), 'username' => $s->get('smb_user', ''), 'password' => $s->secret('smb_password') ?? ''], JSON_THROW_ON_ERROR));

    return 0;
});
Artisan::command('privatebar:smb-result {result}', function () {
    if (config('privatebar.mode') !== 'pi') {
        return 1;
    }
    $s = app(Settings::class);
    $ok = $this->argument('result') === 'ok';
    $s->set('smb_mount_requested', false);
    $s->set('smb_test_requested', false);
    $s->set('smb_test_result', $ok ? 'Verbindung hergestellt. Fotoquelle wird nur gelesen.' : 'Verbindung fehlgeschlagen. Server, Freigabe und Zugangsdaten prüfen. Der Cache bleibt verfügbar.');
    if ($ok) {
        $s->set('photo_index_pending', true);
        $s->set('photo_index_cursor', '');
    }

    return 0;
});

Artisan::command('privatebar:publish-state', function () {
    if (config('privatebar.mode') !== 'cloud') {
        return 1;
    }
    $result = DB::transaction(function () {
        app(Settings::class)->assertRunning();
        $start = (int) DB::table('sync_events')->max('sequence');
        $journal = app(Journal::class);
        foreach (DB::table('ingredients')->get() as $i) {
            $journal->record('ingredient', $i->id, ['name' => $i->name, 'category_id' => $i->category_id, 'automatic' => (bool) $i->automatic, 'synonyms' => DB::table('ingredient_synonyms')->where('ingredient_id', $i->id)->pluck('name')->all()]);
        }
        foreach (DB::table('ingredient_substitutions')->get() as $s) {
            $journal->record('substitution', $s->id, ['required_id' => $s->required_id, 'replacement_id' => $s->replacement_id, 'enabled' => (bool) $s->enabled]);
        }
        DB::table('products')->orderBy('id')->chunk(100, function ($rows) use ($journal) {
            foreach ($rows as $p) {
                $journal->record('product', $p->id, app(Inventory::class)->snapshot($p->id));
            }
        });
        DB::table('recipes')->orderBy('id')->chunk(100, function ($rows) {
            foreach ($rows as $r) {
                app(RecipeWriter::class)->publish($r->id);
            }
        });
        foreach (DB::table('favorites')->get() as $f) {
            $journal->record('favorite', $f->recipe_id, []);
        }
        foreach (DB::table('shopping_list_items')->get() as $s) {
            $journal->record('shopping', $s->ingredient_id, []);
        }
        foreach (DB::table('recipe_ratings')->get() as $r) {
            $journal->record('rating', $r->id, ['recipe_id' => $r->recipe_id, 'user_uuid' => $r->user_uuid, 'stars' => (int) $r->stars]);
        }
        foreach (DB::table('shared_settings')->get() as $s) {
            $journal->record('setting', $s->key, ['value' => json_decode($s->value, true)]);
        }

        return ['cursor' => $start, 'epoch' => DB::table('sync_cursors')->where('peer', 'server')->value('epoch')];
    });
    $this->line(json_encode($result, JSON_THROW_ON_ERROR));

    return 0;
});
Artisan::command('privatebar:reset-projection {epoch} {cursor} {--discard-pending}', function () {
    $settings = app(Settings::class);
    if (config('privatebar.mode') !== 'pi' || ! $settings->maintenance()) {
        $this->error('Nur direkt auf dem Pi im Wartungsmodus.');

        return 1;
    }
    if (! Str::isUuid($this->argument('epoch')) || ! ctype_digit($this->argument('cursor'))) {
        return 1;
    }
    if (! app(AccessGuard::class)->pin((string) $this->secret('Kiosk-PIN'))) {
        $this->error('PIN ungültig.');

        return 1;
    }
    $pending = DB::table('sync_events')->whereNull('confirmed_at')->count();
    if ($pending && ! $this->option('discard-pending')) {
        $this->error('Nicht bestätigte lokale Änderungen vorhanden. Zuerst prüfen; bewusstes Verwerfen erfordert --discard-pending.');

        return 1;
    }
    if (! $this->confirm('Synchronisierte lokale Daten durch den geprüften Cyon-Stand ersetzen? Lokale PIN und Fotorahmendaten bleiben erhalten.', false)) {
        return 1;
    }
    DB::transaction(function () {
        foreach (['random_history', 'favorites', 'shopping_list_items', 'recipe_ratings', 'recipe_versions', 'recipe_sources', 'recipe_ingredients', 'recipes', 'bar_inventory', 'product_ingredient_mappings', 'products', 'ingredient_substitutions', 'ingredient_synonyms', 'ingredients', 'shared_settings', 'sync_events', 'sync_inbox', 'sync_cursors', 'sync_tombstones'] as $table) {
            DB::table($table)->delete();
        }
        DB::table('sync_cursors')->insert(['peer' => 'cloud', 'cursor' => (int) $this->argument('cursor'), 'epoch' => $this->argument('epoch')]);
        DB::table('audit_entries')->insert(['actor' => 'kiosk:'.config('privatebar.instance_id'), 'action' => 'restore:reset-projection', 'entity_id' => null, 'details' => 'Kontrollierter Neuaufbau der synchronisierten Projektion.', 'created_at' => now()]);
    });
    $settings->set('sync_requested', true);
    $settings->set('daily_recommendation', []);
    $this->info('Projektion vorbereitet. Wartungsmodus nach Prüfung mit PIN beenden und synchronisieren.');

    return 0;
});
