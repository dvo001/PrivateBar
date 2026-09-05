<?php

namespace App\Domain\Sync;

use App\Domain\Settings\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class Projector
{
    public const SHARED_KEYS = ['import_hour', 'import_frequency_hours'];

    public function validate(string $entity, string $id, array $p, bool $fromDevice): array
    {
        if ($entity === 'setting') {
            if (! in_array($p['key'] ?? null, self::SHARED_KEYS, true) || $id !== Settings::sharedId($p['key'])) {
                throw ValidationException::withMessages(['entity' => 'Diese Einstellung darf nicht synchronisiert werden.']);
            }
            Validator::make($p, ['value' => 'required|integer|min:0|max:168'])->validate();
            if (($p['key'] === 'import_hour' && $p['value'] > 23) || ($p['key'] === 'import_frequency_hours' && $p['value'] < 1)) {
                throw ValidationException::withMessages(['value' => 'Ungültige Importzeit.']);
            }

            return ['key' => $p['key'], 'value' => $p['value']];
        }
        Validator::make(['id' => $id], ['id' => 'required|uuid'])->validate();
        $rules = match ($entity) {
            'product' => ['name' => 'required|string|max:255', 'brand' => 'nullable|string|max:255', 'barcode' => 'nullable|regex:/^[0-9]{8,14}$/D', 'abv' => 'nullable|numeric|between:0,100', 'ingredient_id' => 'required|uuid|exists:ingredients,id', 'present' => 'required|boolean', 'generic' => 'required|boolean', 'manually_corrected' => 'required|boolean', 'source' => 'nullable|string|max:255', 'license' => 'nullable|string|max:2000', 'image_path' => 'nullable|regex:~^products/[a-f0-9]{64}\\.webp$~'],
            'ingredient' => ['name' => 'required|string|max:255', 'category_id' => 'required|exists:ingredient_categories,id', 'automatic' => 'required|boolean', 'synonyms' => 'sometimes|array|max:50', 'synonyms.*' => 'string|max:255'],
            'substitution' => ['required_id' => 'required|uuid|exists:ingredients,id', 'replacement_id' => 'required|uuid|exists:ingredients,id|different:required_id', 'enabled' => 'required|boolean'],
            'recipe' => ['name' => 'required|string|max:255', 'instructions' => 'required|string|max:50000', 'household' => 'required|boolean', 'alcoholic' => 'required|boolean', 'base_spirit' => 'nullable|string|max:100', 'taste' => 'nullable|string|max:100', 'method' => 'nullable|string|max:255', 'glass' => 'nullable|string|max:255', 'image_path' => 'nullable|regex:~^recipes/[a-f0-9]{64}\\.webp$~', 'parent_id' => 'nullable|uuid', 'hidden' => 'required|boolean', 'version' => 'required|integer|min:1', 'original_text' => 'nullable|string|max:50000', 'original_language' => 'required|string|max:10', 'translation_manual' => 'required|boolean', 'translation_pending' => 'required|boolean', 'fingerprint' => 'nullable|string|size:64', 'imported_at' => 'nullable|date',
                'ingredients' => 'required|array|min:1|max:50', 'ingredients.*.ingredient_id' => 'required|uuid|exists:ingredients,id', 'ingredients.*.amount' => 'nullable|numeric|min:0|max:10000', 'ingredients.*.unit' => 'nullable|string|max:30', 'ingredients.*.original_measure' => 'nullable|string|max:255', 'ingredients.*.role' => 'required|in:required,optional,garnish', 'ingredients.*.position' => 'required|integer|min:0|max:100',
                'sources' => 'present|array|max:10', 'sources.*.id' => 'required|uuid', 'sources.*.provider' => 'required|in:cocktaildb,opendrinks', 'sources.*.external_id' => 'required|string|max:255', 'sources.*.url' => 'required|url:https|max:255', 'sources.*.license' => 'required|string|max:2000', 'sources.*.original_json' => 'required|string|max:100000', 'sources.*.source_hash' => 'required|string|size:64', 'sources.*.imported_at' => 'required|date'],
            'favorite' => [], 'shopping' => [],
            'recipe_visibility' => ['hidden' => 'required|boolean'],
            'recipe_translation' => ['instructions' => 'required|string|max:20000'],
            'rating' => ['recipe_id' => 'required|uuid|exists:recipes,id', 'user_uuid' => 'required|uuid', 'stars' => 'required|integer|between:1,5'],
            default => throw ValidationException::withMessages(['entity' => 'Unbekannte synchronisierte Entität.']),
        };
        if ($fromDevice && $entity === 'rating') {
            throw ValidationException::withMessages(['entity' => 'Kioskgeräte dürfen keine persönlichen Bewertungen ändern.']);
        }
        if ($fromDevice && $entity === 'recipe' && (! (bool) ($p['household'] ?? false) || DB::table('recipes')->where('id', $id)->where('household', false)->exists())) {
            throw ValidationException::withMessages(['entity' => 'Importierte Originalrezepte werden auf Cyon verwaltet.']);
        }
        if ($entity === 'favorite' || str_starts_with($entity, 'recipe_')) {
            Validator::make(['recipe' => $id], ['recipe' => 'exists:recipes,id'])->validate();
        }
        if ($entity === 'shopping') {
            Validator::make(['ingredient' => $id], ['ingredient' => 'exists:ingredients,id'])->validate();
        }
        $valid = Validator::make($p, $rules)->validate();
        // Kein unbekanntes Anbieter- oder Gerätefeld wird in interne Tabellen übernommen.
        $valid = array_intersect_key($valid, array_flip(array_filter(array_keys($rules), fn ($k) => ! str_contains($k, '.'))));
        if ($fromDevice && $entity === 'recipe') {
            $valid['sources'] = [];
        }

        return $valid;
    }

    public function apply(string $entity, string $id, array $p, bool $deleted = false): void
    {
        if ($deleted && ! in_array($entity, ['favorite', 'shopping', 'substitution'], true)) {
            throw ValidationException::withMessages(['deleted' => 'Diese Entität verwendet keinen Löschvorgang.']);
        }
        $now = now();
        switch ($entity) {
            case 'product':
                $ingredient = $p['ingredient_id'];
                $present = $p['present'];
                unset($p['ingredient_id'],$p['present']);
                $duplicate = empty($p['barcode']) ? null : DB::table('products')->where('barcode', $p['barcode'])->where('id', '!=', $id)->first();
                if ($duplicate) {
                    // Deterministische Barcode-ID im Schreibpfad verhindert diesen Fall für neue Clients.
                    throw ValidationException::withMessages(['barcode' => 'Barcodekonflikt: Produkt vor dem Abgleich zusammenführen.']);
                }
                DB::table('products')->updateOrInsert(['id' => $id], $p + ['created_at' => $now, 'updated_at' => $now]);
                DB::table('product_ingredient_mappings')->where('product_id', $id)->delete();
                DB::table('product_ingredient_mappings')->insert(['product_id' => $id, 'ingredient_id' => $ingredient, 'manual' => true]);
                if ($present) {
                    DB::table('bar_inventory')->updateOrInsert(['product_id' => $id], ['created_at' => $now]);
                } else {
                    DB::table('bar_inventory')->where('product_id', $id)->delete();
                }
                break;
            case 'ingredient':
                $synonyms = $p['synonyms'] ?? [];
                unset($p['synonyms']);
                DB::table('ingredients')->updateOrInsert(['id' => $id], $p + ['created_at' => $now, 'updated_at' => $now]);
                foreach ($synonyms as $synonym) {
                    DB::table('ingredient_synonyms')->updateOrInsert(['name' => $synonym], ['ingredient_id' => $id]);
                }
                break;
            case 'substitution':
                if ($deleted) {
                    DB::table('ingredient_substitutions')->where('id', $id)->delete();
                } else {
                    DB::table('ingredient_substitutions')->updateOrInsert(['id' => $id], $p + ['created_at' => $now, 'updated_at' => $now]);
                }
                break;
            case 'recipe':
                $lines = $p['ingredients'];
                $sources = $p['sources'];
                unset($p['ingredients'],$p['sources']);
                DB::table('recipes')->updateOrInsert(['id' => $id], $p + ['created_at' => $now, 'updated_at' => $now]);
                DB::table('recipe_ingredients')->where('recipe_id', $id)->delete();
                foreach ($lines as $line) {
                    DB::table('recipe_ingredients')->insert(array_intersect_key($line, array_flip(['ingredient_id', 'amount', 'unit', 'original_measure', 'role', 'position'])) + ['recipe_id' => $id]);
                }
                foreach ($sources as $source) {
                    $source = array_intersect_key($source, array_flip(['id', 'provider', 'external_id', 'url', 'license', 'original_json', 'source_hash', 'imported_at']));
                    DB::table('recipe_sources')->updateOrInsert(['id' => $source['id']], $source + ['recipe_id' => $id]);
                }
                break;
            case 'favorite': case 'shopping':
                $table = $entity === 'favorite' ? 'favorites' : 'shopping_list_items';
                $key = $entity === 'favorite' ? 'recipe_id' : 'ingredient_id';
                if ($deleted) {
                    DB::table($table)->where($key, $id)->delete();
                } else {
                    DB::table($table)->updateOrInsert([$key => $id], ['created_at' => $now]);
                }
                break;
            case 'recipe_visibility': DB::table('recipes')->where('id', $id)->update(['hidden' => $p['hidden']]);
                break;
            case 'recipe_translation': DB::table('recipes')->where('id', $id)->update(['instructions' => $p['instructions'], 'translation_manual' => true, 'translation_pending' => false]);
                break;
            case 'rating': DB::table('recipe_ratings')->updateOrInsert(['id' => $id], $p + ['created_at' => $now, 'updated_at' => $now]);
                break;
            case 'setting': app(Settings::class)->set($p['key'], $p['value'], false);
                break;
        }
    }
}
