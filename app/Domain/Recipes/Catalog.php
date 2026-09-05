<?php

namespace App\Domain\Recipes;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class Catalog
{
    private ?array $context = null;

    public function context(): array
    {
        if ($this->context !== null) {
            return $this->context;
        }
        $ingredients = DB::table('ingredients')->join('ingredient_categories', 'category_id', '=', 'ingredient_categories.id')
            ->select('ingredients.*', 'typical_abv')->get();
        $products = DB::table('bar_inventory')->join('products', 'bar_inventory.product_id', '=', 'products.id')
            ->join('product_ingredient_mappings', 'products.id', '=', 'product_ingredient_mappings.product_id')
            ->orderBy('products.id')->get(['ingredient_id', 'abv']);
        $available = $ingredients->where('automatic', true)->pluck('id')->merge($products->pluck('ingredient_id'))->unique()->all();
        $substitutions = DB::table('ingredient_substitutions')->where('enabled', true)->orderBy('replacement_id')->get()->groupBy('required_id')->map(fn ($rows) => $rows->pluck('replacement_id')->all())->all();
        $abv = [];
        // Bei mehreren Flaschen deterministisch das erste Produkt mit bekanntem Wert.
        foreach ($products as $p) {
            if ($p->abv !== null && ! isset($abv[$p->ingredient_id])) {
                $abv[$p->ingredient_id] = (float) $p->abv;
            }
        }

        return $this->context = ['available' => $available, 'substitutions' => $substitutions, 'abv' => $abv, 'fallback' => $ingredients->pluck('typical_abv', 'id')->all(), 'names' => $ingredients->pluck('name', 'id')->all()];
    }

    public function decorate(object $recipe, array $lines): object
    {
        $context = $this->context();
        $recipe->feasibility = (new Feasibility)->evaluate($lines, $context['available'], $context['substitutions']);
        $recipe->abv = (new Alcohol)->estimate($recipe->feasibility['lines'], $context['abv'], $context['fallback']);

        return $recipe;
    }

    private function query(array $filters)
    {
        $ratings = DB::table('recipe_ratings')->selectRaw('recipe_id, AVG(stars) as rating')->groupBy('recipe_id');
        $query = DB::table('recipes')->leftJoinSub($ratings, 'ratings', 'recipes.id', '=', 'ratings.recipe_id')
            ->select('recipes.*', 'rating')->where('hidden', (bool) ($filters['hidden'] ?? false));
        if ($q = trim($filters['q'] ?? '')) {
            $query->whereRaw("name LIKE ? ESCAPE '!'", ['%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q).'%']);
        }
        foreach (['base_spirit', 'taste', 'method'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }
        if (isset($filters['alcoholic']) && $filters['alcoholic'] !== '') {
            $query->where('alcoholic', (bool) $filters['alcoholic']);
        }
        if (! empty($filters['favorites'])) {
            $query->whereExists(fn ($q) => $q->selectRaw('1')->from('favorites')->whereColumn('favorites.recipe_id', 'recipes.id'));
        }

        return $query;
    }

    /** Bounded batches avoid loading the entire catalogue and images into memory. */
    public function matching(array $filters = []): array
    {
        $summaries = [];
        $this->query($filters)->orderBy('recipes.id')->chunk(200, function ($recipes) use (&$summaries, $filters) {
            $lines = DB::table('recipe_ingredients')->whereIn('recipe_id', $recipes->pluck('id'))->orderBy('position')->get()->groupBy('recipe_id');
            foreach ($recipes as $recipe) {
                $data = ($lines[$recipe->id] ?? collect())->map(fn ($l) => (array) $l)->all();
                $recipe = $this->decorate($recipe, $data);
                if (isset($filters['rank']) && $filters['rank'] !== '' && $recipe->feasibility['rank'] !== (int) $filters['rank']) {
                    continue;
                }
                unset($recipe->instructions,$recipe->original_text,$recipe->feasibility['lines']);
                $summaries[] = $recipe;
            }
        });
        usort($summaries, function ($a, $b) use ($filters) {
            $rank = $a->feasibility['rank'] <=> $b->feasibility['rank'];
            if ($rank) {
                return $rank;
            }
            if (($filters['sort'] ?? '') !== 'name') {
                $rating = ($b->rating === null ? -1 : (float) $b->rating) <=> ($a->rating === null ? -1 : (float) $a->rating);
                if ($rating) {
                    return $rating;
                }
            }

            return strnatcasecmp(Str::ascii($a->name), Str::ascii($b->name)) ?: strcmp($a->name, $b->name);
        });

        return $summaries;
    }

    public function paginate(array $filters, int $page = 1): LengthAwarePaginator
    {
        $all = $this->matching($filters);

        return new LengthAwarePaginator(array_slice($all, (max(1, $page) - 1) * 24, 24), count($all), 24, max(1, $page), ['path' => request()->url(), 'query' => request()->query()]);
    }

    public function find(string $id): object
    {
        $recipe = $this->query(['hidden' => DB::table('recipes')->where('id', $id)->value('hidden')])->where('recipes.id', $id)->first();
        abort_unless($recipe, 404);
        $lines = DB::table('recipe_ingredients')->join('ingredients', 'ingredient_id', '=', 'ingredients.id')->where('recipe_id', $id)->orderBy('position')
            ->get(['recipe_ingredients.*', 'ingredients.name'])->map(fn ($l) => (array) $l)->all();

        return $this->decorate($recipe, $lines);
    }
}
