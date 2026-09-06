<?php

namespace Database\Seeders;

use App\Domain\Sync\Journal;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

final class AmarettoSeeder extends Seeder
{
    public function run(bool $publish = true): void
    {
        DB::transaction(function () use ($publish) {
            DB::table('ingredient_categories')->insertOrIgnore(['id' => 'liqueur', 'name' => 'Liköre', 'typical_abv' => 25]);
            // Bereits importierte Zutaten und private Korrekturen behalten ihre ID.
            $id = DB::table('ingredient_synonyms')->where('name', 'amaretto')->value('ingredient_id')
                ?? DB::table('ingredients')->whereRaw('LOWER(name) = ?', ['amaretto'])->value('id')
                ?? Uuid::uuid5(Uuid::NAMESPACE_URL, 'privatebar:ingredient:amaretto')->toString();
            $changed = DB::table('ingredients')->insertOrIgnore([
                'id' => $id, 'name' => 'Amaretto', 'category_id' => 'liqueur', 'automatic' => false,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            // "Disarono" ist der deutsche Produktname im OFF-Eintrag 8001110016303.
            foreach (['amaretto', 'disaronno', 'disarono'] as $synonym) {
                $changed += DB::table('ingredient_synonyms')->insertOrIgnore(['name' => $synonym, 'ingredient_id' => $id]);
            }
            if ($publish && $changed) {
                $ingredient = DB::table('ingredients')->where('id', $id)->first();
                app(Journal::class)->record('ingredient', $id, [
                    'name' => $ingredient->name, 'category_id' => $ingredient->category_id,
                    'automatic' => (bool) $ingredient->automatic,
                    'synonyms' => DB::table('ingredient_synonyms')->where('ingredient_id', $id)->pluck('name')->all(),
                ]);
            }
        });
    }
}
