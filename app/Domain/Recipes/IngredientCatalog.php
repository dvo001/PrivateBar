<?php

namespace App\Domain\Recipes;

use App\Domain\Settings\Settings;
use App\Domain\Sync\Journal;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

final class IngredientCatalog
{
    public static function key(string $name): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $name)));
    }

    /** Behält historische IDs; eindeutige alte Importnamen verweisen über Synonyme auf die Hauptzutat. */
    public function identities(): array
    {
        $rows = DB::table('ingredients')->get(['id', 'name']);
        $synonyms = DB::table('ingredient_synonyms')->pluck('ingredient_id', 'name')->all();
        $ids = $rows->pluck('id')->all();
        $map = [];
        foreach ($rows as $row) {
            $target = $synonyms[self::key($row->name)] ?? $row->id;
            $map[$row->id] = in_array($target, $ids, true) ? $target : $row->id;
        }
        // Widersprüchliche private Synonyme dürfen keine zyklischen Zuordnungen erzeugen.
        foreach ($map as $id => $target) {
            $visited = [$id];
            while (($map[$target] ?? $target) !== $target && ! in_array($target, $visited, true)) {
                $visited[] = $target;
                $target = $map[$target];
            }
            $map[$id] = in_array($target, $visited, true) ? $id : $target;
        }

        return $map;
    }

    public function choices(): Collection
    {
        $map = $this->identities();

        return DB::table('ingredients')->join('ingredient_categories', 'category_id', '=', 'ingredient_categories.id')
            ->select('ingredients.*', 'ingredient_categories.name as category_name')
            ->orderBy('ingredient_categories.name')->orderBy('ingredients.name')->get()
            ->filter(fn ($row) => ($map[$row->id] ?? $row->id) === $row->id);
    }

    public function install(bool $publish = true): array
    {
        return DB::transaction(function () use ($publish) {
            if ($publish) {
                app(Settings::class)->assertRunning();
            }
            $definitions = IngredientDefinitions::additions();
            foreach (DB::table('ingredient_categories')->get() as $category) {
                $definitions[$category->name.' – noch nicht zugeordnet'] = [$category->id, []];
            }
            foreach (IngredientDefinitions::extraSynonyms() as $name => $aliases) {
                $category = DB::table('ingredients')->where('name', $name)->value('category_id');
                if ($category) {
                    $definitions[$name] = [$category, $aliases];
                }
            }
            $protected = DB::table('audit_entries')->where('action', 'write:ingredient')
                ->where(fn ($q) => $q->where('actor', 'like', 'user:%')->orWhere('actor', 'like', 'kiosk:%'))
                ->pluck('entity_id')->all();
            $changedIds = [];
            $conflicts = [];
            foreach ($definitions as $name => [$category, $aliases]) {
                $keys = array_values(array_unique(array_merge([self::key($name)], $aliases)));
                $existing = DB::table('ingredients')->whereRaw('LOWER(name) = ?', [self::key($name)])->first();
                if (! $existing) {
                    // Einen bereits importierten englischen Namen wiederverwenden.
                    $existing = DB::table('ingredients')->whereIn(DB::raw('LOWER(name)'), $keys)
                        ->orderBy('id')->first();
                }
                // Privat umbenannte Hauptzutaten anhand ihres bekannten Namens erkennen.
                if (! $existing) {
                    $synonymId = DB::table('ingredient_synonyms')->where('name', self::key($name))->value('ingredient_id');
                    if ($synonymId && ! $this->oldWhiskyAlias($name, $synonymId, $protected)) {
                        $existing = DB::table('ingredients')->where('id', $synonymId)->first();
                    }
                }
                $id = $existing->id ?? Uuid::uuid5(Uuid::NAMESPACE_URL, 'privatebar:ingredient:'.self::key($name))->toString();
                if (! $existing) {
                    DB::table('ingredients')->insert(['id' => $id, 'name' => $name, 'category_id' => $category, 'automatic' => false, 'created_at' => now(), 'updated_at' => now()]);
                    $changedIds[$id] = true;
                } elseif (! in_array($id, $protected, true) && $existing->category_id === 'other' && in_array(self::key($existing->name), $keys, true)) {
                    if ($existing->name !== $name || $existing->category_id !== $category) {
                        DB::table('ingredients')->where('id', $id)->update(['name' => $name, 'category_id' => $category, 'updated_at' => now()]);
                        $changedIds[$id] = true;
                    }
                }
                foreach ($keys as $key) {
                    $owner = DB::table('ingredient_synonyms')->where('name', $key)->value('ingredient_id');
                    if (in_array($id, $protected, true) && $owner !== $id) {
                        // Auch bewusst entfernte Synonyme sind eine private Korrektur.
                        continue;
                    }
                    $named = DB::table('ingredients')->whereRaw('LOWER(name) = ?', [$key])->value('id');
                    if (($named && $named !== $id && in_array($named, $protected, true)) || ($owner && $owner !== $id && ! $this->oldWhiskyAlias($key, $owner, $protected))) {
                        $conflicts[] = $key;

                        continue;
                    }
                    if ($owner !== $id) {
                        DB::table('ingredient_synonyms')->updateOrInsert(['name' => $key], ['ingredient_id' => $id]);
                        $changedIds[$id] = true;
                    }
                }
            }
            if ($publish) {
                foreach (array_keys($changedIds) as $id) {
                    $this->publish($id);
                }
            }

            return ['changed' => count($changedIds), 'conflicts' => array_values(array_unique($conflicts))];
        });
    }

    private function oldWhiskyAlias(string $name, string $id, array $protected): bool
    {
        return in_array(self::key($name), ['bourbon', 'scotch'], true)
            && $id === Uuid::uuid5(Uuid::NAMESPACE_URL, 'privatebar:seed:Whisky')->toString()
            && ! in_array($id, $protected, true);
    }

    public function publish(string $id): void
    {
        $row = DB::table('ingredients')->where('id', $id)->first();
        app(Journal::class)->record('ingredient', $id, ['name' => $row->name, 'category_id' => $row->category_id,
            'automatic' => (bool) $row->automatic,
            'synonyms' => DB::table('ingredient_synonyms')->where('ingredient_id', $id)->pluck('name')->all()]);
    }
}
