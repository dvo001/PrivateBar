<?php

namespace App\Domain\Recipes;

use App\Domain\Settings\Settings;
use App\Domain\Sync\Journal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Ramsey\Uuid\Uuid;

final class IngredientGlossary
{
    public function create(array $data): string
    {
        return DB::transaction(function () use ($data) {
            app(Settings::class)->assertRunning();
            $key = IngredientCatalog::key($data['name']);
            if (DB::table('ingredients')->whereRaw('LOWER(name) = ?', [$key])->exists()
                || DB::table('ingredient_synonyms')->where('name', $key)->exists()) {
                throw ValidationException::withMessages(['name' => 'Diese Cocktailzutat oder dieser Name ist bereits vorhanden.']);
            }
            $id = Uuid::uuid5(Uuid::NAMESPACE_URL, 'privatebar:ingredient:'.$key)->toString();
            DB::table('ingredients')->insert(['id' => $id, 'name' => trim($data['name']), 'category_id' => $data['category_id'],
                'automatic' => false, 'created_at' => now(), 'updated_at' => now()]);
            $this->update($id, $data);

            return $id;
        });
    }

    public function update(string $id, array $data): void
    {
        DB::transaction(function () use ($id, $data) {
            app(Settings::class)->assertRunning();
            $ingredient = DB::table('ingredients')->where('id', $id)->lockForUpdate()->first();
            abort_unless($ingredient !== null, 404);
            $synonyms = array_values(array_unique(array_filter(array_map(fn ($s) => mb_strtolower(trim($s)), array_merge(explode(',', $data['synonyms'] ?? ''), [$ingredient->name, $data['name']])))));
            if (count($synonyms) > 50) {
                throw ValidationException::withMessages(['synonyms' => 'Höchstens 50 Namen und Synonyme pro Zutat.']);
            }
            if (DB::table('ingredient_synonyms')->whereIn('name', $synonyms)->where('ingredient_id', '!=', $id)->exists()) {
                throw ValidationException::withMessages(['synonyms' => 'Ein Synonym gehört bereits zu einer anderen Cocktailzutat.']);
            }
            DB::table('ingredients')->where('id', $id)->update(['name' => $data['name'], 'category_id' => $data['category_id'], 'updated_at' => now()]);
            DB::table('ingredient_synonyms')->where('ingredient_id', $id)->whereNotIn('name', $synonyms)->delete();
            foreach ($synonyms as $synonym) {
                DB::table('ingredient_synonyms')->updateOrInsert(['name' => $synonym], ['ingredient_id' => $id]);
            }
            app(Journal::class)->record('ingredient', $id, ['name' => $data['name'], 'category_id' => $data['category_id'], 'automatic' => (bool) $ingredient->automatic, 'synonyms' => DB::table('ingredient_synonyms')->where('ingredient_id', $id)->pluck('name')->all()]);
        });
    }
}
