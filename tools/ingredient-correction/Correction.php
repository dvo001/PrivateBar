<?php

namespace PrivateBar\Correction;

use App\Domain\Bar\Inventory;
use App\Domain\Recipes\IngredientCatalog;
use App\Domain\Settings\Settings;
use App\Domain\Sync\Journal;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class Correction
{
    public function run(array $plan, bool $apply = false, bool $categoriesOnly = false, bool $disableAutomatic = false): array
    {
        if (! $categoriesOnly && config('privatebar.mode') !== 'cloud') {
            throw new RuntimeException('Zutatenkorrektur nur auf Cyon; auf dem Pi --categories-only verwenden.');
        }
        app(Settings::class)->assertRunning();
        $report = [];
        $changed = [];
        DB::beginTransaction();
        try {
            foreach ($plan['categories'] as $id => $definition) {
                $row = DB::table('ingredient_categories')->where('id', $id)->lockForUpdate()->first();
                $current = $row ? ['name' => $row->name, 'typical_abv' => $row->typical_abv] : null;
                if ($current == $definition['after']) {
                    continue;
                }
                if ($current != $definition['before']) {
                    throw new RuntimeException('Kategorie seit Export verändert: '.$id);
                }
                DB::table('ingredient_categories')->updateOrInsert(['id' => $id], $definition['after']);
                $report[] = 'Kategorie: '.$definition['after']['name'];
                if ($apply) {
                    DB::table('audit_entries')->insert(['actor' => 'system:ingredient-correction-2026-09-08', 'action' => 'correction:category', 'entity_id' => $id,
                        'details' => json_encode(['before' => $current, 'after' => $definition['after']], JSON_THROW_ON_ERROR), 'created_at' => now()]);
                }
            }
            if (! $categoriesOnly) {
                foreach ($plan['new_ingredients'] as $definition) {
                    $row = DB::table('ingredients')->where('id', $definition['id'])->lockForUpdate()->first();
                    if ($row) {
                        if ($row->name !== $definition['name'] || $row->category_id !== $definition['category_id']) {
                            throw new RuntimeException('Neue Zutat kollidiert: '.$definition['name']);
                        }

                        continue;
                    }
                    if (DB::table('ingredients')->where('name', $definition['name'])->exists()
                        || DB::table('ingredient_synonyms')->where('name', IngredientCatalog::key($definition['name']))->exists()) {
                        throw new RuntimeException('Zutat oder Synonym existiert unter anderer ID: '.$definition['name']);
                    }
                    DB::table('ingredients')->insert($definition + ['automatic' => false, 'created_at' => now(), 'updated_at' => now()]);
                    $changed[$definition['id']] = true;
                    $report[] = 'Neue Zutat: '.$definition['name'];
                }
                foreach ($plan['ingredients'] as $definition) {
                    $row = DB::table('ingredients')->where('id', $definition['id'])->lockForUpdate()->first();
                    $current = $row ? ['name' => $row->name, 'category_id' => $row->category_id] : null;
                    if ($current === $definition['after']) {
                        continue;
                    }
                    if ($current !== $definition['before']) {
                        throw new RuntimeException('Zutat fehlt oder wurde verändert: '.$definition['before']['name']);
                    }
                    if ($definition['before']['name'] !== $definition['after']['name']) {
                        if (DB::table('ingredients')->where('name', $definition['after']['name'])->where('id', '!=', $row->id)->exists()) {
                            throw new RuntimeException('Zielname bereits belegt: '.$definition['after']['name']);
                        }
                        foreach ([$definition['before']['name'], $definition['after']['name']] as $name) {
                            $key = IngredientCatalog::key($name);
                            $owner = DB::table('ingredient_synonyms')->where('name', $key)->value('ingredient_id');
                            if ($owner && $owner !== $row->id) {
                                throw new RuntimeException('Synonymkonflikt: '.$name);
                            }
                            DB::table('ingredient_synonyms')->insertOrIgnore(['name' => $key, 'ingredient_id' => $row->id]);
                        }
                    }
                    DB::table('ingredients')->where('id', $row->id)->update($definition['after'] + ['updated_at' => now()]);
                    $changed[$row->id] = true;
                    $report[] = 'Zutat: '.$row->name.' → '.$definition['after']['name'].' ['.$definition['after']['category_id'].']';
                }
                if ($disableAutomatic) {
                    foreach ($plan['optional_automatic'] as $definition) {
                        $row = DB::table('ingredients')->where('id', $definition['id'])->lockForUpdate()->first();
                        if (! $row || $row->name !== $definition['name']) {
                            throw new RuntimeException('Automatische Zutat fehlt oder wurde umbenannt: '.$definition['name']);
                        }
                        if ($row->automatic) {
                            DB::table('ingredients')->where('id', $row->id)->update(['automatic' => false, 'updated_at' => now()]);
                            $changed[$row->id] = true;
                            $report[] = 'Nicht mehr automatisch vorhanden: '.$row->name;
                        }
                    }
                }
                if ($apply) {
                    foreach (array_keys($changed) as $id) {
                        app(IngredientCatalog::class)->publish($id);
                    }
                }
                foreach ($plan['products'] as $definition) {
                    $row = DB::table('products')->where('id', $definition['id'])->lockForUpdate()->first();
                    if (! $row || $row->name !== $definition['name'] || $row->barcode !== $definition['barcode']) {
                        throw new RuntimeException('Produkt fehlt oder wurde verändert: '.$definition['name']);
                    }
                    $mappings = DB::table('product_ingredient_mappings')->where('product_id', $row->id)->lockForUpdate()->pluck('ingredient_id')->all();
                    if ($mappings === [$definition['after']]) {
                        continue;
                    }
                    if ($mappings !== [$definition['before']]) {
                        throw new RuntimeException('Produktzuordnung verändert: '.$definition['name']);
                    }
                    DB::table('product_ingredient_mappings')->where('product_id', $row->id)->update(['ingredient_id' => $definition['after'], 'manual' => true]);
                    DB::table('products')->where('id', $row->id)->update(['manually_corrected' => true, 'updated_at' => now()]);
                    if ($apply) {
                        app(Journal::class)->record('product', $row->id, app(Inventory::class)->snapshot($row->id));
                    }
                    $report[] = 'Produktzuordnung: '.$row->name;
                }
            }
            if ($apply) {
                DB::commit();
            } else {
                DB::rollBack();
            }
        } catch (\Throwable $exception) {
            DB::rollBack();
            throw $exception;
        }

        return $report;
    }
}
