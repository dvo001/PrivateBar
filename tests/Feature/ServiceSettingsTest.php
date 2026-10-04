<?php

namespace Tests\Feature;

use App\Domain\Access\AccessGuard;
use App\Domain\Settings\BackgroundTasks;
use App\Domain\Settings\CloudConnection;
use App\Domain\Settings\Settings;
use App\Domain\Sync\SyncClient;
use App\Infrastructure\Providers\OpenFoodFacts;
use App\Infrastructure\Providers\TranslationProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ServiceSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('t', 32)), 'privatebar.mode' => 'pi',
            'privatebar.pin_hash' => Hash::make('123456'), 'privatebar.providers_enabled' => false,
            'privatebar.cloud_url' => 'https://cloud.example.test', 'privatebar.device_token' => 'legacy-device-token']);
        $this->withSession(['kiosk_unlocked' => true, 'boot_id' => app(AccessGuard::class)->bootId()]);
        Http::preventStrayRequests();
    }

    public function test_service_overrides_are_independent_and_keep_legacy_defaults(): void
    {
        $settings = app(Settings::class);
        self::assertFalse($settings->serviceEnabled('recipe_import_enabled'));
        config(['privatebar.providers_enabled' => true]);
        self::assertTrue($settings->serviceEnabled('translation_enabled'));
        $settings->set('translation_enabled', false);
        self::assertFalse($settings->serviceEnabled('translation_enabled'));
        self::assertTrue($settings->serviceEnabled('recipe_import_enabled'));
        $settings->set('product_lookup_enabled', true);
        config(['privatebar.providers_enabled' => false]);
        self::assertTrue($settings->serviceEnabled('product_lookup_enabled'));
    }

    public function test_pi_form_cannot_change_cloud_switches(): void
    {
        $this->post('/einstellungen/dienste', ['product_lookup_enabled' => 1, 'recipe_import_enabled' => 1, 'translation_enabled' => 1])->assertRedirect();
        self::assertTrue(app(Settings::class)->serviceEnabled('product_lookup_enabled'));
        self::assertNull(app(Settings::class)->get('recipe_import_enabled'));
        self::assertNull(app(Settings::class)->get('translation_enabled'));
        self::assertSame(0, DB::table('sync_events')->count());
        $this->post('/einstellungen/dienste', ['product_lookup_enabled' => 'invalid'])->assertSessionHasErrors('product_lookup_enabled');
    }

    public function test_cloud_form_saves_all_three_switches(): void
    {
        config(['privatebar.mode' => 'cloud']);
        $user = User::forceCreate(['uuid' => (string) Str::uuid(), 'name' => 'Test', 'email' => 'test@example.test',
            'password' => Hash::make('long-test-password'), 'email_verified_at' => now()]);
        $this->actingAs($user)->post('/einstellungen/dienste', ['recipe_import_enabled' => 0, 'translation_enabled' => 1, 'product_lookup_enabled' => 0])->assertRedirect();
        self::assertFalse(app(Settings::class)->serviceEnabled('recipe_import_enabled'));
        self::assertTrue(app(Settings::class)->serviceEnabled('translation_enabled'));
    }

    public function test_translation_runs_while_import_is_disabled(): void
    {
        $this->seed();
        config(['privatebar.mode' => 'cloud']);
        $settings = app(Settings::class);
        $settings->set('recipe_import_enabled', false);
        $settings->set('translation_enabled', true);
        $settings->set('import_pending', true);
        DB::table('recipes')->update(['translation_pending' => false]);
        $recipe = DB::table('recipes')->first();
        DB::table('recipes')->where('id', $recipe->id)->update(['translation_pending' => true, 'translation_manual' => false,
            'original_language' => 'en', 'original_text' => 'Unique translation independence test']);
        $this->mock(TranslationProvider::class)->shouldReceive('translate')->once()->andReturn('Unabhängige Übersetzung');
        app(BackgroundTasks::class)->tick();
        self::assertSame('Unabhängige Übersetzung', DB::table('recipes')->where('id', $recipe->id)->value('instructions'));
        self::assertTrue($settings->get('import_pending'));
        Http::assertNothingSent();
    }

    public function test_paused_import_resumes_its_cursor_while_translation_stays_disabled(): void
    {
        $this->seed();
        config(['privatebar.mode' => 'cloud', 'privatebar.cocktaildb_key' => 'test-key']);
        $settings = app(Settings::class);
        $settings->set('recipe_import_enabled', false);
        $settings->set('translation_enabled', false);
        $settings->set('import_pending', true);
        $settings->set('import_cursor', 'b');
        $this->mock(TranslationProvider::class)->shouldNotReceive('translate');
        app(BackgroundTasks::class)->tick();
        Http::assertNothingSent();
        self::assertSame('b', $settings->get('import_cursor'));
        $settings->set('recipe_import_enabled', true);
        Http::fake(['www.thecocktaildb.com/*' => Http::response(['drinks' => []])]);
        app(BackgroundTasks::class)->tick();
        self::assertSame('c', $settings->get('import_cursor'));
        Http::assertSentCount(1);
    }

    public function test_disabled_product_lookup_keeps_cached_products_without_http(): void
    {
        app(Settings::class)->set('product_lookup_enabled', false);
        config(['privatebar.providers_enabled' => true]);
        DB::table('provider_cache')->insert(['key' => 'off:12345678', 'payload' => json_encode(['name' => 'Cache']), 'expires_at' => now()->addDay()]);
        self::assertSame(['name' => 'Cache'], app(OpenFoodFacts::class)->lookup('12345678'));
        self::assertNull(app(OpenFoodFacts::class)->lookup('87654321'));
        Http::assertNothingSent();
    }

    public function test_connection_is_encrypted_preserves_blank_token_and_does_not_render_it(): void
    {
        $this->post('/einstellungen/verbindung', ['pin' => '123456', 'cloud_url' => 'https://cloud.example.test/', 'device_token' => 'new-private-device-token'])->assertRedirect('/einstellungen');
        $connection = app(CloudConnection::class);
        self::assertSame('new-private-device-token', $connection->token());
        self::assertStringNotContainsString('new-private-device-token', DB::table('local_settings')->where('key', 'device_token')->value('value'));
        $this->post('/einstellungen/verbindung', ['pin' => '123456', 'cloud_url' => $connection->url(), 'device_token' => ''])->assertRedirect();
        self::assertSame('new-private-device-token', $connection->token());
        $this->post('/einstellungen/lokal/oeffnen', ['pin' => '123456'])->assertOk()->assertDontSee('new-private-device-token')->assertDontSee('legacy-device-token');
        self::assertSame(0, DB::table('sync_events')->count());
        self::assertSame(0, DB::table('shared_settings')->count());
    }

    public function test_connection_requires_local_pin_and_blocks_changes_during_sync(): void
    {
        $data = ['pin' => '000000', 'cloud_url' => 'https://cloud.example.test', 'device_token' => 'new-private-device-token'];
        $this->post('/einstellungen/verbindung', $data)->assertSessionHasErrors('pin');
        self::assertNull(app(Settings::class)->get('cloud_url'));
        $data['pin'] = '123456';
        $lock = Cache::lock('privatebar-sync', 180);
        $lock->get();
        $this->post('/einstellungen/verbindung', $data)->assertSessionHasErrors('connection');
        $lock->release();
        $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.20'])->post('/einstellungen/verbindung', $data)->assertForbidden();
    }

    public function test_bad_urls_and_changed_host_without_new_token_are_rejected_without_flashing_token(): void
    {
        foreach (['http://cloud.example.test', 'https://user:pass@cloud.example.test', 'https://cloud.example.test?query=1', 'https://cloud.example.test#fragment'] as $url) {
            $this->post('/einstellungen/verbindung', ['pin' => '123456', 'cloud_url' => $url, 'device_token' => 'new-private-device-token'])->assertSessionHasErrors('cloud_url');
            self::assertNull(session()->getOldInput('device_token'));
            self::assertNull(app(Settings::class)->get('cloud_url'));
        }
        $this->post('/einstellungen/verbindung', ['pin' => '123456', 'cloud_url' => 'https://other.example.test'])->assertSessionHasErrors('device_token');
        Http::assertNothingSent();
    }

    public function test_connection_check_uses_new_credentials_and_never_synchronizes(): void
    {
        app(Settings::class)->set('cloud_url', 'https://new.example.test');
        app(Settings::class)->setSecret('device_token', 'new-private-device-token');
        Http::fake(['new.example.test/api/v1/device-check' => Http::response(['authenticated' => true, 'schema_version' => 1])]);
        $this->post('/einstellungen/verbindung/test', ['pin' => '123456'])->assertRedirect('/einstellungen');
        Http::assertSent(fn ($request) => $request->url() === 'https://new.example.test/api/v1/device-check'
            && $request->hasHeader('Authorization', 'Bearer new-private-device-token'));
        Http::assertSentCount(1);
        self::assertSame(0, DB::table('sync_events')->count());
        self::assertStringContainsString('erfolgreich', app(Settings::class)->get('cloud_connection_result'));
        Http::fake(['new.example.test/*' => Http::response(['secret' => 'new-private-device-token'], 401)]);
        $this->post('/einstellungen/verbindung/test', ['pin' => '123456'])->assertRedirect();
        self::assertStringNotContainsString('new-private-device-token', app(Settings::class)->get('cloud_connection_result'));
    }

    public function test_sync_uses_ui_connection_instead_of_environment(): void
    {
        app(Settings::class)->set('cloud_url', 'https://new.example.test');
        app(Settings::class)->setSecret('device_token', 'new-private-device-token');
        Http::fake(['new.example.test/api/v1/sync' => Http::response(['schema_version' => 1, 'epoch' => (string) Str::uuid(),
            'accepted' => [], 'events' => [], 'cursor' => 0, 'has_more' => false])]);
        app(SyncClient::class)->run();
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer new-private-device-token'));
        self::assertSame('Aktuell', app(Settings::class)->get('sync_state'));
    }

    public function test_device_check_requires_valid_https_device_and_respects_maintenance(): void
    {
        config(['privatebar.mode' => 'cloud']);
        $token = 'cloud-private-device-token';
        DB::table('devices')->insert(['id' => (string) Str::uuid(), 'name' => 'Pi', 'token_hash' => hash('sha256', $token), 'created_at' => now(), 'updated_at' => now()]);
        $this->withToken($token)->getJson('https://localhost/api/v1/device-check')->assertOk()->assertExactJson(['authenticated' => true, 'schema_version' => 1]);
        $this->withToken('invalid')->getJson('https://localhost/api/v1/device-check')->assertUnauthorized();
        $this->withToken($token)->getJson('http://localhost/api/v1/device-check')->assertForbidden();
        DB::table('devices')->update(['revoked_at' => now()]);
        $this->withToken($token)->getJson('https://localhost/api/v1/device-check')->assertUnauthorized();
        app(Settings::class)->set('maintenance', true);
        $this->getJson('https://localhost/api/v1/device-check')->assertStatus(503);
    }
}
