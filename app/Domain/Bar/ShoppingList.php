<?php

namespace App\Domain\Bar;

use App\Domain\Recipes\IngredientCatalog;
use App\Domain\Settings\Settings;
use App\Domain\Sync\Journal;
use Illuminate\Support\Facades\DB;

final class ShoppingList
{
    public function __construct(private Inventory $inventory, private Journal $journal, private Settings $settings) {}

    public function add(array $ids): void
    {
        $this->settings->assertRunning();
        DB::transaction(function () use ($ids) {
            $this->settings->assertRunning();
            $map = app(IngredientCatalog::class)->identities();
            foreach (array_unique(array_map(fn ($id) => $map[$id] ?? $id, $ids)) as $id) {
                $equivalents = array_keys($map, $id, true);
                abort_unless(DB::table('recipe_ingredients')->whereIn('ingredient_id', $equivalents)->exists(), 422, 'Nur bekannte Rezeptzutaten können auf die Einkaufsliste.');
                if (DB::table('shopping_list_items')->whereIn('ingredient_id', $equivalents)->exists()) {
                    continue;
                }
                if (DB::table('shopping_list_items')->insertOrIgnore(['ingredient_id' => $id, 'created_at' => now()])) {
                    $this->journal->record('shopping', $id, []);
                }
            }
        });
    }

    public function remove(string $id): void
    {
        $this->settings->assertRunning();
        DB::transaction(function () use ($id) {
            $this->settings->assertRunning();
            $map = app(IngredientCatalog::class)->identities();
            foreach (array_unique([$id, ...array_keys($map, $map[$id] ?? $id, true)]) as $equivalent) {
                DB::table('shopping_list_items')->where('ingredient_id', $equivalent)->delete();
                $this->journal->record('shopping', $equivalent, [], true);
            }
        });
    }

    public function purchased(string $id): void
    {
        $this->settings->assertRunning();
        DB::transaction(function () use ($id) {
            $this->settings->assertRunning();
            $item = DB::table('shopping_list_items')->where('ingredient_id', $id)->lockForUpdate()->first();
            if (! $item) {
                return;
            }
            $ingredient = DB::table('ingredients')->where('id', $id)->first();
            $this->inventory->save(['name' => $ingredient->name.' (generisch)', 'ingredient_id' => $id, 'generic' => true]);
            $this->remove($id);
        });
    }
}
