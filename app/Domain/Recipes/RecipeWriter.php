<?php

namespace App\Domain\Recipes;

use App\Domain\Settings\Settings;
use App\Domain\Sync\Journal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class RecipeWriter
{
    public function __construct(private Journal $journal, private Settings $settings) {}

    public function save(array $data, ?string $id = null): string
    {
        $this->settings->assertRunning();

        return DB::transaction(function () use ($data, $id) {
            $this->settings->assertRunning();
            $old = $id ? DB::table('recipes')->where('id', $id)->lockForUpdate()->first() : null;
            if ($id) {
                abort_unless($old !== null, 404);
            }
            $copy = $old && ! $old->household;
            $target = $copy || ! $id ? (string) Str::uuid() : $id;
            $record = array_intersect_key($data, array_flip(['name', 'instructions', 'alcoholic', 'base_spirit', 'taste', 'method', 'glass', 'image_path']));
            $record += ['household' => true, 'parent_id' => $copy ? $id : $old?->parent_id, 'version' => $copy ? 1 : (($old->version ?? 0) + 1), 'updated_at' => now(), 'created_at' => $copy ? now() : ($old->created_at ?? now())];
            DB::table('recipes')->updateOrInsert(['id' => $target], $record);
            DB::table('recipe_ingredients')->where('recipe_id', $target)->delete();
            foreach ($data['ingredients'] as $position => $line) {
                DB::table('recipe_ingredients')->insert(['recipe_id' => $target, 'ingredient_id' => $line['ingredient_id'], 'amount' => $line['amount'] ?? null, 'unit' => $line['unit'] ?? null, 'role' => $line['role'], 'position' => $position, 'original_measure' => $line['original_measure'] ?? null]);
            }
            $this->publish($target);

            return $target;
        });
    }

    public function snapshot(string $id): array
    {
        $recipe = (array) DB::table('recipes')->where('id', $id)->first();
        $recipe['ingredients'] = DB::table('recipe_ingredients')->where('recipe_id', $id)->orderBy('position')->get()->map(function ($row) {
            $a = (array) $row;
            unset($a['id'],$a['recipe_id']);

            return $a;
        })->all();
        $recipe['sources'] = DB::table('recipe_sources')->where('recipe_id', $id)->get()->map(fn ($s) => (array) $s)->all();

        return $recipe;
    }

    public function publish(string $id): void
    {
        $snapshot = $this->snapshot($id);
        DB::table('recipe_versions')->insert(['recipe_id' => $id, 'version' => $snapshot['version'], 'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR), 'actor' => $this->journal->actor(), 'created_at' => now()]);
        $this->journal->record('recipe', $id, $snapshot);
    }

    public function hide(string $id, bool $hidden): void
    {
        $this->settings->assertRunning();
        DB::transaction(function () use ($id, $hidden) {
            $this->settings->assertRunning();
            abort_unless(DB::table('recipes')->where('id', $id)->exists(), 404);
            DB::table('recipes')->where('id', $id)->update(['hidden' => $hidden]);
            $this->journal->record('recipe_visibility', $id, ['hidden' => $hidden]);
        });
    }

    public function translateManually(string $id, string $text): void
    {
        $this->settings->assertRunning();
        DB::transaction(function () use ($id, $text) {
            $this->settings->assertRunning();
            abort_unless(DB::table('recipes')->where('id', $id)->exists(), 404);
            DB::table('recipes')->where('id', $id)->update(['instructions' => $text, 'translation_manual' => true, 'translation_pending' => false, 'updated_at' => now()]);
            $this->journal->record('recipe_translation', $id, ['instructions' => $text]);
        });
    }
}
