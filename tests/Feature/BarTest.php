<?php

namespace Tests\Feature;

use App\Domain\Access\AccessGuard;
use App\Domain\Bar\Inventory;
use App\Domain\Bar\ShoppingList;
use App\Domain\Recipes\Catalog;
use App\Domain\Recipes\RandomRecipe;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class BarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['privatebar.mode' => 'pi']);
        $this->seed();
    }

    private function id(string $name): string
    {
        return DatabaseSeeder::id($name);
    }

    private function unlocked(): static
    {
        return $this->withSession(['kiosk_unlocked' => true, 'boot_id' => app(AccessGuard::class)->bootId()]);
    }

    public function test_all_main_pages_render_and_empty_bar_has_exact_actions(): void
    {
        foreach (['/', '/machbar', '/entdecken', '/favoriten', '/meine-bar', '/meine-bar/neu', '/scannen', '/einkaufsliste', '/einstellungen', '/rezepte/neu', '/rezepte/'.$this->id('recipe:Gin Tonic')] as $path) {
            $level = ob_get_level();
            $this->unlocked()->get($path)->assertOk();
            self::assertSame($level, ob_get_level(), $path);
        }
        $this->unlocked()->get('/meine-bar')->assertSee('Bar bitte füllen')->assertSee('Flasche scannen')->assertSee('Manuell hinzufügen');
    }

    public function test_multiple_products_cover_one_ingredient_and_basics_are_automatic(): void
    {
        $i = app(Inventory::class);
        $gin = $this->id('Gin');
        $one = $i->save(['name' => 'Gin A', 'ingredient_id' => $gin]);
        $two = $i->save(['name' => 'Gin B', 'ingredient_id' => $gin]);
        $i->remove($one);
        self::assertContains($gin, app(Catalog::class)->context()['available']);
        $i->remove($two);
        self::assertNotContains($gin, app(Catalog::class)->context()['available']);
        self::assertContains($this->id('Wasser'), app(Catalog::class)->context()['available']);
    }

    public function test_barcode_confirmation_corrections_and_rescan_removal(): void
    {
        $data = ['name' => 'Mein Gin', 'brand' => 'Hausmarke', 'barcode' => '7612345678901', 'ingredient_id' => $this->id('Gin'), 'abv' => 42.5, 'confirmed' => 1];
        $this->unlocked()->post('/meine-bar', array_diff_key($data, ['confirmed' => 1]))->assertSessionHasErrors('confirmed');
        self::assertSame(0, DB::table('products')->count());
        $this->unlocked()->post('/meine-bar', $data)->assertRedirect('/meine-bar');
        $this->unlocked()->post('/scannen', ['barcode' => $data['barcode']])->assertOk()->assertSee('bereits vorhanden')->assertSee('Entfernen bestätigen');
        $id = DB::table('products')->value('id');
        app(Inventory::class)->remove($id);
        $this->unlocked()->post('/scannen', ['barcode' => $data['barcode']])->assertSee('42.5')->assertSee('Mein Gin');
        $this->unlocked()->post('/meine-bar', $data)->assertRedirect();
        self::assertSame(1, DB::table('products')->count());
    }

    public function test_shopping_purchase_is_idempotent_and_barcode_replaces_generic(): void
    {
        $id = $this->id('Gin');
        $shopping = app(ShoppingList::class);
        $shopping->add([$id, $id]);
        self::assertSame(1, DB::table('shopping_list_items')->count());
        $shopping->purchased($id);
        $shopping->purchased($id);
        self::assertSame(0, DB::table('shopping_list_items')->count());
        self::assertSame(1, DB::table('bar_inventory')->count());
        app(Inventory::class)->save(['name' => 'Gin', 'barcode' => '7612345678901', 'ingredient_id' => $id]);
        self::assertSame(1, DB::table('bar_inventory')->count());
        self::assertFalse((bool) DB::table('bar_inventory')->join('products', 'product_id', '=', 'id')->value('generic'));
    }

    public function test_rating_order_unrated_and_ties_with_feasibility_grouping(): void
    {
        $ids = DB::table('recipes')->pluck('id', 'name');
        foreach (['Negroni' => 4, 'Daiquiri' => 4, 'Margarita' => 5] as $name => $stars) {
            DB::table('recipe_ratings')->insert(['id' => Str::uuid(), 'recipe_id' => $ids[$name], 'user_uuid' => Str::uuid(), 'stars' => $stars, 'created_at' => now(), 'updated_at' => now()]);
        }
        $recipes = app(Catalog::class)->matching(['rank' => 3]);
        $names = array_column($recipes, 'name');
        self::assertSame(['Margarita', 'Daiquiri', 'Negroni'], array_slice($names, 0, 3));
        $this->unlocked()->post('/rezepte/'.$ids['Negroni'].'/bewertung', ['stars' => 5])->assertForbidden();
    }

    public function test_random_excludes_last_six_and_only_exactly_feasible(): void
    {
        DB::table('ingredients')->update(['automatic' => true]);
        $random = app(RandomRecipe::class);
        $seen = [];
        for ($n = 0; $n < 7; $n++) {
            $seen[] = $random->choose();
        }
        self::assertCount(7, array_unique($seen));
        self::assertSame(6, DB::table('random_history')->count());
        DB::table('ingredients')->update(['automatic' => false]);
        self::assertNull(app(RandomRecipe::class)->choose());
    }
}
