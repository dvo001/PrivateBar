<?php

namespace Tests\Feature;

use App\Domain\Settings\Settings;
use App\Domain\Sync\Journal;
use App\Domain\Sync\SyncClient;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class SyncClientTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['privatebar.mode' => 'pi', 'privatebar.device_token' => 'test-device', 'privatebar.cloud_url' => 'https://cloud.example.test']);
        $this->seed();
    }

    public function test_pull_is_durable_replay_safe_and_private_settings_never_leave(): void
    {
        app(Settings::class)->setSecret('smb_password', 'never-send-this');
        $event = ['id' => (string) Str::uuid(), 'sequence' => 1, 'entity' => 'shopping', 'entity_id' => DatabaseSeeder::id('Gin'), 'payload' => [], 'deleted' => false, 'version' => 1, 'actor' => 'user:test'];
        $epoch = (string) Str::uuid();
        Http::fake(['cloud.example.test/api/v1/sync' => Http::response(['schema_version' => 1, 'epoch' => $epoch, 'accepted' => [], 'events' => [$event], 'cursor' => 1, 'has_more' => false])]);
        app(SyncClient::class)->run();
        app(SyncClient::class)->run();
        self::assertSame(1, DB::table('shopping_list_items')->count());
        self::assertSame(1, DB::table('sync_inbox')->count());
        self::assertSame(1, (int) DB::table('sync_cursors')->where('peer', 'cloud')->value('cursor'));
        Http::assertSent(fn ($r) => ! str_contains($r->body(), 'smb') && ! str_contains($r->body(), 'never-send-this'));
        self::assertNotNull(app(Settings::class)->get('sync_last_success'));
    }

    public function test_failed_pull_does_not_advance_cursor_and_can_resume(): void
    {
        $epoch = (string) Str::uuid();
        $event = ['id' => (string) Str::uuid(), 'sequence' => 2, 'entity' => 'shopping', 'entity_id' => DatabaseSeeder::id('missing'), 'payload' => [], 'deleted' => false, 'version' => 2, 'actor' => 'user:test'];
        Http::fake(['cloud.example.test/*' => Http::response(['schema_version' => 1, 'epoch' => $epoch, 'accepted' => [], 'events' => [$event], 'cursor' => 2, 'has_more' => false])]);
        try {
            app(SyncClient::class)->run();
            self::fail();
        } catch (ValidationException) {
        }
        self::assertSame(0, DB::table('sync_inbox')->count());
        self::assertSame(0, DB::table('sync_cursors')->where('peer', 'cloud')->count());
        self::assertNotNull(app(Settings::class)->get('sync_error'));
    }

    public function test_outbox_payload_is_retried_until_acknowledged(): void
    {
        $id = DatabaseSeeder::id('Gin');
        $eventId = app(Journal::class)->record('shopping', $id, []);
        $epoch = (string) Str::uuid();
        Http::fake(['cloud.example.test/*' => Http::response(['schema_version' => 1, 'epoch' => $epoch, 'accepted' => [$eventId], 'events' => [['id' => $eventId, 'sequence' => 1, 'entity' => 'shopping', 'entity_id' => $id, 'payload' => [], 'deleted' => false, 'version' => 1, 'actor' => 'kiosk:test']], 'cursor' => 1, 'has_more' => false])]);
        app(SyncClient::class)->run();
        self::assertNotNull(DB::table('sync_events')->where('id', $eventId)->value('confirmed_at'));
        Http::assertSent(fn ($r) => $r['events'][0]['id'] === $eventId);
    }
}
