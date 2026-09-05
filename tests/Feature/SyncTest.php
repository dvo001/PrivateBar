<?php

namespace Tests\Feature;

use App\Domain\Sync\Projector;
use App\Domain\Sync\SyncServer;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class SyncTest extends TestCase
{
    use RefreshDatabase;

    private object $device;

    protected function setUp(): void
    {
        parent::setUp();
        config(['privatebar.mode' => 'cloud']);
        $this->seed();
        $id = (string) Str::uuid();
        DB::table('devices')->insert(['id' => $id, 'name' => 'Pi', 'token_hash' => hash('sha256', 'test-device'), 'created_at' => now(), 'updated_at' => now()]);
        $this->device = DB::table('devices')->first();
    }

    private function exchange(array $events, int $cursor = 0): array
    {
        return app(SyncServer::class)->exchange(['schema_version' => 1, 'cursor' => $cursor, 'events' => $events], $this->device);
    }

    private function event(bool $deleted = false): array
    {
        return ['id' => (string) Str::uuid(), 'entity' => 'shopping', 'entity_id' => DatabaseSeeder::id('Gin'), 'payload' => [], 'deleted' => $deleted];
    }

    public function test_retry_conflicts_tombstones_and_cursor_are_idempotent(): void
    {
        $add = $this->event();
        $first = $this->exchange([$add]);
        $retry = $this->exchange([$add]);
        self::assertSame($first['cursor'], $retry['cursor']);
        self::assertSame(1, DB::table('sync_events')->count());
        self::assertSame(1, DB::table('shopping_list_items')->count());
        $delete = $this->event(true);
        $last = $this->exchange([$delete], $first['cursor']);
        self::assertSame(0, DB::table('shopping_list_items')->count());
        self::assertSame(1, DB::table('sync_tombstones')->count());
        $this->exchange([$add], $last['cursor']);
        self::assertSame(0, DB::table('shopping_list_items')->count());
        $this->exchange([$this->event()], $last['cursor']);
        self::assertSame(1, DB::table('shopping_list_items')->count());
        self::assertSame(3, DB::table('audit_entries')->count());
    }

    public function test_api_v1_accepts_extra_envelope_fields_but_rejects_local_data(): void
    {
        $this->withServerVariables(['HTTPS' => 'on'])->withToken('test-device')->postJson('https://localhost/api/v1/sync', ['schema_version' => 1, 'cursor' => 0, 'events' => [], 'future_feature' => true])->assertOk()->assertJsonPath('schema_version', 1);
        $this->expectException(ValidationException::class);
        app(Projector::class)->validate('setting', 'smb_password', ['value' => 'secret'], true);
    }

    public function test_device_may_not_impersonate_member_rating_or_import_owner(): void
    {
        $this->expectException(ValidationException::class);
        app(Projector::class)->validate('rating', (string) Str::uuid(), ['recipe_id' => DatabaseSeeder::id('recipe:Gin Tonic'), 'user_uuid' => (string) Str::uuid(), 'stars' => 5], true);
    }

    public function test_ingredient_synonyms_survive_projection_and_snapshot_is_valid(): void
    {
        $id = DatabaseSeeder::id('Gin');
        $payload = ['name' => 'Gin', 'category_id' => DB::table('ingredients')->where('id', $id)->value('category_id'), 'automatic' => false, 'synonyms' => ['London Dry Gin']];
        $projector = app(Projector::class);
        $projector->apply('ingredient', $id, $projector->validate('ingredient', $id, $payload, false));
        $this->assertDatabaseHas('ingredient_synonyms', ['name' => 'London Dry Gin', 'ingredient_id' => $id]);
        $this->artisan('privatebar:publish-state')->assertSuccessful();
        self::assertGreaterThan(20, DB::table('sync_events')->count());
        foreach (DB::table('sync_events')->get() as $event) {
            $projector->validate($event->entity, $event->entity_id, json_decode($event->payload, true), false);
        }
    }

    public function test_revoked_device_rejected(): void
    {
        DB::table('devices')->update(['revoked_at' => now()]);
        $this->withServerVariables(['HTTPS' => 'on'])->withToken('test-device')->postJson('https://localhost/api/v1/sync', ['schema_version' => 1, 'cursor' => 0, 'events' => []])->assertUnauthorized();
    }
}
