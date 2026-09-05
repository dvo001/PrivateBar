<?php

namespace App\Domain\Recipes;

use App\Domain\Settings\Settings;
use App\Domain\Sync\Journal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class IngredientGlossary
{
    public function update(string $id, array $data): void
    {
        DB::transaction(function () use ($id, $data) {
            app(Settings::class)->assertRunning();
            $ingredient = DB::table('ingredients')->where('id', $id)->lockForUpdate()->first();
            abort_unless($ingredient !== null, 404);
            $synonyms = array_values(array_unique(array_filter(array_map(fn ($s) => mb_strtolower(trim($s)), array_merge(explode(',', $data['synonyms'] ?? ''), [$ingredient->name, $data['name']])))));
            if (DB::table('ingredient_synonyms')->whereIn('name', $synonyms)->where('ingredient_id', '!=', $id)->exists()) {
                throw ValidationException::withMessages(['synonyms' => 'Ein Synonym gehört bereits zu einer anderen Cocktailzutat.']);
            }
            DB::table('ingredients')->where('id', $id)->update(['name' => $data['name'], 'category_id' => $data['category_id'], 'updated_at' => now()]);
            foreach ($synonyms as $synonym) {
                DB::table('ingredient_synonyms')->updateOrInsert(['name' => $synonym], ['ingredient_id' => $id]);
            }
            app(Journal::class)->record('ingredient', $id, ['name' => $data['name'], 'category_id' => $data['category_id'], 'automatic' => (bool) $ingredient->automatic, 'synonyms' => DB::table('ingredient_synonyms')->where('ingredient_id', $id)->pluck('name')->all()]);
        });
    }
}
