<?php

namespace Tests\Feature;

use App\Domain\Access\AccessGuard;
use App\Domain\Bar\Inventory;
use App\Domain\Bar\ShoppingList;
use App\Domain\Recipes\Catalog;
use App\Domain\Recipes\Importer;
use App\Domain\Recipes\IngredientCatalog;
use App\Domain\Recipes\IngredientDefinitions;
use App\Domain\Recipes\IngredientGlossary;
use App\Domain\Sync\Projector;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

final class IngredientCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['privatebar.mode' => 'pi']);
        $this->seed();
        $this->withSession(['kiosk_unlocked' => true, 'boot_id' => app(AccessGuard::class)->bootId()]);
    }

    public function test_all_catalog_gaps_and_synonyms_are_available_without_marking_stock_present(): void
    {
        foreach (IngredientDefinitions::additions() as $name => [$category, $aliases]) {
            $id = DB::table('ingredients')->where('name', $name)->value('id');
            self::assertNotNull($id, $name);
            $this->assertDatabaseHas('ingredients', ['id' => $id, 'category_id' => $category, 'automatic' => false]);
            foreach ($aliases as $alias) {
                self::assertSame($id, app(Importer::class)->ingredient($alias), $alias);
            }
        }
        foreach (IngredientDefinitions::extraSynonyms() as $name => $aliases) {
            foreach ($aliases as $alias) {
                self::assertSame(DatabaseSeeder::id($name), app(Importer::class)->ingredient($alias));
            }
        }
        self::assertSame(['Eis', 'Salz', 'Wasser', 'Zucker'], DB::table('ingredients')->where('automatic', true)->orderBy('name')->pluck('name')->all());
        $before = DB::table('ingredients')->count();
        $this->artisan('db:seed', ['--class' => 'IngredientCatalogSeeder', '--force' => true])->assertSuccessful();
        $this->artisan('db:seed', ['--class' => 'IngredientCatalogSeeder', '--force' => true])->assertSuccessful();
        self::assertSame($before, DB::table('ingredients')->count());
        $this->assertDatabaseCount('sync_events', 0);
    }

    public function test_legacy_import_aliases_match_stock_recipes_and_shopping_without_deleting_ids(): void
    {
        config(['privatebar.mode' => 'cloud']);
        DB::table('ingredient_synonyms')->where('name', 'fresh lime juice')->delete();
        $recipe = app(Importer::class)->ingest(['provider' => 'opendrinks', 'external_id' => 'legacy', 'name' => 'Alias test',
            'instructions' => 'Mix', 'language' => 'en', 'ingredients' => [['name' => 'fresh lime juice', 'measure' => '2 cl']],
            'url' => 'https://example.test/recipe', 'license' => 'MIT', 'original' => ['test' => true]]);
        $oldId = DB::table('recipe_ingredients')->where('recipe_id', $recipe)->value('ingredient_id');
        $canonical = DatabaseSeeder::id('Limettensaft');
        self::assertNotSame($canonical, $oldId);
        $product = app(Inventory::class)->save(['name' => 'Legacy bottle', 'ingredient_id' => $oldId, 'abv' => 0]);
        app(IngredientCatalog::class)->install();
        self::assertSame(0, app(Catalog::class)->find($recipe)->feasibility['rank']);
        self::assertSame(0.0, app(Catalog::class)->find($recipe)->abv);
        $this->assertDatabaseHas('ingredients', ['id' => $oldId]);
        $this->assertDatabaseHas('recipe_ingredients', ['recipe_id' => $recipe, 'ingredient_id' => $oldId]);
        self::assertFalse(app(IngredientCatalog::class)->choices()->contains('id', $oldId));
        app(Inventory::class)->remove($product);
        app(ShoppingList::class)->add([$canonical, $oldId]);
        $this->assertDatabaseCount('shopping_list_items', 1);
        app(ShoppingList::class)->purchased($canonical);
        $this->assertDatabaseCount('shopping_list_items', 0);
        self::assertSame(0, app(Catalog::class)->find($recipe)->feasibility['rank']);
        $event = DB::table('sync_events')->where('entity', 'ingredient')->where('entity_id', $canonical)->latest('sequence')->first();
        $payload = json_decode($event->payload, true);
        self::assertContains('fresh lime juice', app(Projector::class)->validate('ingredient', $canonical, $payload, false)['synonyms']);
    }

    public function test_private_names_and_synonym_owners_are_not_overwritten(): void
    {
        $id = DB::table('ingredients')->where('name', 'Aperol')->value('id');
        app(IngredientGlossary::class)->update($id, ['name' => 'Mein Aperitif', 'category_id' => 'other', 'synonyms' => 'private blend']);
        $result = app(IngredientCatalog::class)->install();
        $this->assertDatabaseHas('ingredients', ['id' => $id, 'name' => 'Mein Aperitif', 'category_id' => 'other']);
        $this->assertDatabaseMissing('ingredients', ['name' => 'Aperol']);
        self::assertSame($id, app(Importer::class)->ingredient('aperol'));
        self::assertSame(0, $result['changed']);
    }

    public function test_broad_area_can_be_refined_without_creating_a_second_bottle(): void
    {
        $general = DB::table('ingredients')->where('name', 'Liköre – noch nicht zugeordnet')->value('id');
        $amaretto = DB::table('ingredients')->where('name', 'Amaretto')->value('id');
        $product = app(Inventory::class)->save(['name' => 'Flasche', 'barcode' => '8001110016303', 'ingredient_id' => $general]);
        self::assertNotContains($amaretto, app(Catalog::class)->context()['available']);
        $this->get('/meine-bar/'.$product.'/bearbeiten')->assertOk()->assertSee('Alle Bereiche')->assertSee('Zutat suchen');
        $this->post('/meine-bar/'.$product.'/bearbeiten', ['name' => 'Disaronno', 'barcode' => '8001110016303',
            'ingredient_id' => $amaretto, 'abv' => 28, 'confirmed' => 1])->assertRedirect('/meine-bar');
        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseHas('product_ingredient_mappings', ['product_id' => $product, 'ingredient_id' => $amaretto]);
        $this->post('/meine-bar/'.$product.'/bearbeiten', ['name' => 'Wrong', 'barcode' => '12345678',
            'ingredient_id' => $amaretto, 'confirmed' => 1])->assertStatus(422);
    }

    public function test_custom_ingredient_is_validated_searchable_and_synchronised(): void
    {
        $this->post('/einstellungen/zutaten', ['name' => 'Hausblütensirup', 'category_id' => 'syrup', 'synonyms' => 'house flower syrup'])->assertRedirect();
        $id = DB::table('ingredients')->where('name', 'Hausblütensirup')->value('id');
        self::assertTrue(Str::isUuid($id));
        $this->get('/einstellungen/zutaten?q=house%20flower&category=syrup')->assertOk()->assertSee('Hausblütensirup');
        self::assertSame($id, app(Importer::class)->ingredient('House flower syrup'));
        $this->post('/einstellungen/zutaten', ['name' => 'house flower syrup', 'category_id' => 'syrup'])->assertSessionHasErrors('name');
        $this->post('/einstellungen/zutaten', ['name' => 'Doppelter Name', 'category_id' => 'syrup', 'synonyms' => 'gin'])->assertSessionHasErrors('synonyms');
        $this->assertDatabaseMissing('ingredients', ['name' => 'Doppelter Name']);
        $this->assertDatabaseHas('sync_events', ['entity' => 'ingredient', 'entity_id' => $id]);
    }

    public function test_syrups_and_alcohol_free_products_do_not_get_a_spirit_suggestion(): void
    {
        config(['privatebar.providers_enabled' => true]);
        Http::preventStrayRequests();
        foreach (['Amaretto syrup', 'Alkoholfreier Gin'] as $index => $name) {
            Http::fake(['world.openfoodfacts.org/*' => Http::response(['product' => ['product_name' => $name, 'brands' => 'Disaronno', 'categories_tags' => ['en:whiskies']]])]);
            $this->post('/scannen', ['barcode' => '1234567'.$index])->assertOk()
                ->assertViewHas('product', fn ($product) => $product['ingredient_id'] === null);
        }
    }

    public function test_synonyms_can_be_removed_and_the_complete_list_is_projected(): void
    {
        $id = DatabaseSeeder::id('Limettensaft');
        $this->get('/einstellungen/zutaten?q=Limettensaft')->assertSee('fresh lime juice');
        $this->post('/einstellungen/zutaten/'.$id, ['name' => 'Frischer Limettensaft', 'category_id' => 'juice', 'synonyms' => 'lime juice'])->assertRedirect();
        $this->assertDatabaseMissing('ingredient_synonyms', ['ingredient_id' => $id, 'name' => 'fresh lime juice']);
        $this->assertDatabaseHas('ingredient_synonyms', ['ingredient_id' => $id, 'name' => 'limettensaft']);
        $event = DB::table('sync_events')->where('entity_id', $id)->latest('sequence')->first();
        $payload = json_decode($event->payload, true);
        // Simuliert einen Pi, der das inzwischen entfernte Synonym noch gespeichert hat.
        DB::table('ingredient_synonyms')->insert(['ingredient_id' => $id, 'name' => 'fresh lime juice']);
        $projector = app(Projector::class);
        $projector->apply('ingredient', $id, $projector->validate('ingredient', $id, $payload, false));
        $this->assertDatabaseMissing('ingredient_synonyms', ['ingredient_id' => $id, 'name' => 'fresh lime juice']);
        $projector->apply('ingredient', $id, ['name' => 'Frischer Limettensaft', 'category_id' => 'juice', 'automatic' => false]);
        $this->assertDatabaseHas('ingredient_synonyms', ['ingredient_id' => $id, 'name' => 'lime juice']);
        app(IngredientCatalog::class)->install();
        $this->assertDatabaseMissing('ingredient_synonyms', ['ingredient_id' => $id, 'name' => 'fresh lime juice']);
    }
}
