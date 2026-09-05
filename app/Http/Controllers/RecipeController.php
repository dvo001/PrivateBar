<?php

namespace App\Http\Controllers;

use App\Domain\Photos\ImageProcessor;
use App\Domain\Recipes\Catalog;
use App\Domain\Recipes\RandomRecipe;
use App\Domain\Recipes\RecipeWriter;
use App\Domain\Sync\Journal;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Ramsey\Uuid\Uuid;

final class RecipeController
{
    public function home(Catalog $catalog, RandomRecipe $random)
    {
        $daily = $random->daily();

        return view('home', ['daily' => $daily ? $catalog->find($daily) : null, 'empty' => ! DB::table('bar_inventory')->exists(), 'recipes' => array_slice($catalog->matching(['rank' => 0]), 0, 4)]);
    }

    public function index(Request $request, Catalog $catalog)
    {
        $filters = $request->validate(['q' => 'nullable|string|max:100', 'rank' => 'nullable|integer|between:0,3', 'alcoholic' => 'nullable|in:0,1', 'base_spirit' => 'nullable|string|max:100', 'taste' => 'nullable|string|max:100', 'method' => 'nullable|string|max:100', 'sort' => 'nullable|in:name,rating', 'page' => 'nullable|integer|min:1', 'hidden' => 'nullable|boolean']);
        if ($request->routeIs('feasible')) {
            $filters['rank'] = 0;
        }
        if ($request->routeIs('favorites')) {
            $filters['favorites'] = true;
        }

        return view('recipes.index', ['recipes' => $catalog->paginate($filters, (int) $request->input('page', 1)), 'filters' => $filters]);
    }

    public function show(string $id, Catalog $catalog)
    {
        return view('recipes.show', ['recipe' => $catalog->find($id), 'names' => $catalog->context()['names'], 'sources' => DB::table('recipe_sources')->where('recipe_id', $id)->get(), 'favorite' => DB::table('favorites')->where('recipe_id', $id)->exists()]);
    }

    public function random(RandomRecipe $random)
    {
        $id = $random->choose();

        return $id ? redirect('/rezepte/'.$id) : redirect('/entdecken?rank=2')->with('message', 'Momentan ist kein Drink vollständig machbar. Hier findest du fast machbare Rezepte und ihre fehlenden Zutaten für die Einkaufsliste.');
    }

    public function form(?string $id = null)
    {
        return view('recipes.form', ['recipe' => $id ? DB::table('recipes')->where('id', $id)->firstOrFail() : null, 'lines' => $id ? DB::table('recipe_ingredients')->where('recipe_id', $id)->orderBy('position')->get()->map(fn ($l) => (array) $l)->all() : [], 'ingredients' => DB::table('ingredients')->orderBy('name')->get()]);
    }

    public function save(Request $request, RecipeWriter $writer, ImageProcessor $images, ?string $id = null)
    {
        $data = $request->validate(['name' => 'required|string|max:255', 'instructions' => 'required|string|max:20000', 'alcoholic' => 'required|boolean', 'base_spirit' => 'nullable|string|max:100', 'taste' => 'nullable|string|max:100', 'method' => 'nullable|string|max:100', 'glass' => 'nullable|string|max:100', 'photo' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:20480',
            'ingredients' => 'required|array|min:1|max:30', 'ingredients.*.ingredient_id' => 'required|uuid|exists:ingredients,id', 'ingredients.*.amount' => 'required_if:ingredients.*.role,required|nullable|numeric|min:0.001|max:10000', 'ingredients.*.unit' => 'nullable|in:cl,g,Stück,Prise,Spritzer', 'ingredients.*.role' => 'required|in:required,optional,garnish']);
        if (! collect($data['ingredients'])->contains('role', 'required')) {
            throw ValidationException::withMessages(['ingredients' => 'Mindestens eine Pflichtzutat ist erforderlich.']);
        }
        if ($request->hasFile('photo')) {
            try {
                $data['image_path'] = $images->compress($request->file('photo')->getPathname())['path'];
            } catch (\RuntimeException $e) {
                throw ValidationException::withMessages(['photo' => $e->getMessage()]);
            }
        }

        return redirect('/rezepte/'.$writer->save($data, $id))->with('message', 'Rezept gespeichert.');
    }

    public function favorite(string $id, Request $request, Journal $journal)
    {
        abort_unless(DB::table('recipes')->where('id', $id)->exists(), 404);
        $present = $request->boolean('present');
        DB::transaction(function () use ($id, $present, $journal) {
            if ($present) {
                DB::table('favorites')->updateOrInsert(['recipe_id' => $id], ['created_at' => now()]);
            } else {
                DB::table('favorites')->where('recipe_id', $id)->delete();
            }
            $journal->record('favorite', $id, [], ! $present);
        });

        return back();
    }

    public function rate(string $id, Request $request, Journal $journal)
    {
        abort_unless(config('privatebar.mode') === 'cloud' && auth()->guard()->check(), 403, 'Bewertungen sind nur mit einem persönlichen Onlinekonto möglich.');
        abort_unless(DB::table('recipes')->where('id', $id)->exists(), 404);
        $data = $request->validate(['stars' => 'required|integer|between:1,5']);
        DB::transaction(function () use ($id, $data, $journal) {
            $uuid = User::query()->where('id', auth()->guard()->id())->value('uuid');
            $key = Uuid::uuid5(Uuid::NAMESPACE_URL, 'privatebar:rating:'.$id.':'.$uuid)->toString();
            DB::table('recipe_ratings')->updateOrInsert(['recipe_id' => $id, 'user_uuid' => $uuid], ['id' => $key, 'stars' => $data['stars'], 'created_at' => now(), 'updated_at' => now()]);
            $journal->record('rating', $key, ['recipe_id' => $id, 'user_uuid' => $uuid, 'stars' => (int) $data['stars']]);
        });

        return back()->with('message', 'Bewertung gespeichert.');
    }

    public function hide(string $id, Request $request, RecipeWriter $writer)
    {
        $writer->hide($id, $request->boolean('hidden'));

        return redirect('/entdecken');
    }

    public function translation(string $id, Request $request, RecipeWriter $writer)
    {
        $data = $request->validate(['instructions' => 'required|string|max:20000']);
        $writer->translateManually($id, $data['instructions']);

        return back()->with('message', 'Deutsche Übersetzung gespeichert und vor automatischem Überschreiben geschützt.');
    }
}
