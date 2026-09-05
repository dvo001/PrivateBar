<?php

namespace App\Domain\Bar;

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
            foreach (array_unique($ids) as $id) {
                abort_unless(DB::table('recipe_ingredients')->where('ingredient_id', $id)->exists(), 422, 'Nur bekannte Rezeptzutaten können auf die Einkaufsliste.');
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
            DB::table('shopping_list_items')->where('ingredient_id', $id)->delete();
            $this->journal->record('shopping', $id, [], true);
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
