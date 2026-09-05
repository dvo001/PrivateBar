<?php

namespace App\Domain\Recipes;

use App\Domain\Settings\Settings;
use App\Domain\Sync\Journal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;

final class Importer
{
    public function __construct(private Settings $settings, private RecipeWriter $writer, private Journal $journal) {}

    public function ingest(array $dto): string
    {
        $this->settings->assertRunning();
        if (config('privatebar.mode') !== 'cloud') {
            throw new \RuntimeException('Rezeptimporte laufen ausschliesslich auf Cyon.');
        }
        Validator::make($dto, ['provider' => 'required|in:cocktaildb,opendrinks', 'external_id' => 'required|string|max:255', 'name' => 'required|string|max:255', 'instructions' => 'required|string|max:50000', 'ingredients' => 'required|array|min:1|max:50', 'ingredients.*.name' => 'required|string|max:255', 'url' => 'required|url:https|max:255', 'license' => 'required|string', 'language' => 'required|string|max:10'])->validate();

        return DB::transaction(function () use ($dto) {
            $this->settings->assertRunning();
            $hash = hash('sha256', json_encode($dto['original'], JSON_THROW_ON_ERROR));
            $source = DB::table('recipe_sources')->where('provider', $dto['provider'])->where('external_id', $dto['external_id'])->lockForUpdate()->first();
            if ($source && $source->source_hash === $hash) {
                return $source->recipe_id;
            }
            $lines = [];
            foreach ($dto['ingredients'] as $line) {
                $canonical = $this->ingredient($line['name']);
                $lines[] = array_merge((new Measures)->normalize($line['measure'] ?? ''), ['ingredient_id' => $canonical, 'role' => $line['role'] ?? 'required']);
            }
            $fingerprintLines = array_map(fn ($l) => $l['ingredient_id'].':'.$l['amount'].':'.$l['unit'], $lines);
            sort($fingerprintLines);
            $fingerprint = hash('sha256', Str::lower(Str::ascii($dto['name'])).'|'.implode('|', $fingerprintLines));
            $old = $source ? DB::table('recipes')->where('id', $source->recipe_id)->first() : DB::table('recipes')->where('fingerprint', $fingerprint)->where('household', false)->first();
            $id = $old->id ?? (string) Str::uuid();
            // Zweitquellen ergänzen die Herkunft, überschreiben aber keine andere Hauptquelle.
            $primary = DB::table('recipe_sources')->where('recipe_id', $id)->orderBy('imported_at')->first();
            $update = ! $primary || ($primary->provider === $dto['provider'] && $primary->external_id === $dto['external_id']);
            if ($update) {
                $manual = (bool) ($old->translation_manual ?? false);
                $translation = $dto['translation'] ?? null;
                $record = ['name' => $dto['name'], 'fingerprint' => $fingerprint, 'original_text' => $dto['instructions'], 'original_language' => $dto['language'],
                    'instructions' => $manual ? $old->instructions : ($translation ?: $dto['instructions']),
                    'translation_pending' => ! $manual && ! $translation && $dto['language'] !== 'de', 'household' => false,
                    'alcoholic' => $dto['alcoholic'] ?? true, 'glass' => $dto['glass'] ?? null, 'method' => $dto['method'] ?? null,
                    'imported_at' => $old->imported_at ?? now(), 'version' => ($old->version ?? 0) + 1, 'updated_at' => now(), 'created_at' => $old->created_at ?? now()];
                DB::table('recipes')->updateOrInsert(['id' => $id], $record);
                DB::table('recipe_ingredients')->where('recipe_id', $id)->delete();
                foreach ($lines as $position => $line) {
                    DB::table('recipe_ingredients')->insert($line + ['recipe_id' => $id, 'position' => $position]);
                }
            }
            DB::table('recipe_sources')->updateOrInsert(['provider' => $dto['provider'], 'external_id' => $dto['external_id']], ['id' => $source->id ?? (string) Str::uuid(), 'recipe_id' => $id, 'url' => $dto['url'], 'license' => $dto['license'], 'original_json' => json_encode($dto['original'], JSON_THROW_ON_ERROR), 'source_hash' => $hash, 'imported_at' => now()]);
            $this->writer->publish($id);

            return $id;
        });
    }

    public function ingredient(string $name): string
    {
        $key = Str::lower(trim($name));
        $existing = DB::table('ingredient_synonyms')->where('name', $key)->value('ingredient_id') ?? DB::table('ingredients')->whereRaw('LOWER(name) = ?', [$key])->value('id');
        if ($existing) {
            return $existing;
        }
        // Namensbasierte UUID verhindert doppelte Zutaten bei wiederholten Importen.
        $id = Uuid::uuid5(Uuid::NAMESPACE_URL, 'privatebar:ingredient:'.$key)->toString();
        DB::table('ingredients')->insertOrIgnore(['id' => $id, 'name' => trim($name), 'category_id' => 'other', 'automatic' => false, 'created_at' => now(), 'updated_at' => now()]);
        $this->journal->record('ingredient', $id, ['name' => trim($name), 'category_id' => 'other', 'automatic' => false]);

        return $id;
    }
}
