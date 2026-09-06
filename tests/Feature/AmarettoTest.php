<?php

namespace Tests\Feature;

use App\Domain\Access\AccessGuard;
use App\Domain\Recipes\Importer;
use Database\Seeders\AmarettoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

final class AmarettoTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_installation_gets_amaretto_and_one_sync_event_on_retries(): void
    {
        config(['privatebar.mode' => 'cloud']);
        $this->artisan('db:seed', ['--class' => 'AmarettoSeeder', '--force' => true])->assertSuccessful();
        $this->artisan('db:seed', ['--class' => 'AmarettoSeeder', '--force' => true])->assertSuccessful();
        $this->assertDatabaseCount('ingredients', 1);
        $this->assertDatabaseCount('ingredient_synonyms', 3);
        $this->assertDatabaseCount('sync_events', 1);
        $payload = json_decode(DB::table('sync_events')->value('payload'), true);
        self::assertSame('Amaretto', $payload['name']);
        self::assertSame('liqueur', $payload['category_id']);
        self::assertFalse($payload['automatic']);
        self::assertContains('disarono', $payload['synonyms']);
    }

    public function test_existing_import_and_manual_corrections_are_preserved(): void
    {
        config(['privatebar.mode' => 'cloud']);
        DB::table('ingredient_categories')->insert(['id' => 'other', 'name' => 'Weitere Zutaten']);
        $id = app(Importer::class)->ingredient('Amaretto');
        DB::table('ingredients')->where('id', $id)->update(['name' => 'Mein Amaretto', 'automatic' => true]);
        DB::table('ingredient_synonyms')->insert(['name' => 'amaretto', 'ingredient_id' => $id]);
        app(AmarettoSeeder::class)->run();
        $this->assertDatabaseCount('ingredients', 1);
        $this->assertDatabaseHas('ingredients', ['id' => $id, 'name' => 'Mein Amaretto', 'category_id' => 'other', 'automatic' => true]);
        self::assertSame($id, app(Importer::class)->ingredient('Disaronno'));
    }

    public function test_disaronno_scan_suggests_amaretto_and_preserves_bottle_abv(): void
    {
        config(['privatebar.mode' => 'pi', 'privatebar.providers_enabled' => true]);
        $this->seed();
        Http::preventStrayRequests();
        Http::fake(['world.openfoodfacts.org/*' => Http::response(['product' => [
            'product_name_de' => 'Disarono', 'product_name' => 'Amaretto', 'brands' => 'Disaronno',
            'nutriments' => ['alcohol_100g' => 28], 'categories_tags' => ['en:liqueurs'],
        ]])]);
        $id = app(Importer::class)->ingredient('Amaretto');
        $this->withSession(['kiosk_unlocked' => true, 'boot_id' => app(AccessGuard::class)->bootId()])
            ->post('/scannen', ['barcode' => '8001110016303'])
            ->assertOk()->assertViewHas('product', fn ($p) => $p['ingredient_id'] === $id && $p['abv'] === 28);
        $this->post('/meine-bar', ['barcode' => '8001110016303', 'name' => 'Disaronno', 'brand' => 'Disaronno',
            'ingredient_id' => $id, 'abv' => 28, 'confirmed' => 1])->assertRedirect('/meine-bar');
        $this->assertDatabaseHas('products', ['barcode' => '8001110016303', 'abv' => 28]);
        $this->assertDatabaseHas('product_ingredient_mappings', ['ingredient_id' => $id, 'manual' => true]);
        self::assertTrue(Str::isUuid($id));
    }
}
