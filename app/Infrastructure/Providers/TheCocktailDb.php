<?php

namespace App\Infrastructure\Providers;

use Illuminate\Support\Facades\Http;

final class TheCocktailDb implements RecipeProvider
{
    public function batch(string $cursor): array
    {
        if (config('privatebar.mode') !== 'cloud' || ! config('privatebar.providers_enabled') || ! config('privatebar.cocktaildb_key')) {
            throw new \RuntimeException('TheCocktailDB ist noch nicht eingerichtet.');
        }
        $alphabet = 'abcdefghijklmnopqrstuvwxyz0123456789';
        $letter = strlen($cursor) === 1 && str_contains($alphabet, $cursor) ? $cursor : 'a';
        $position = strpos($alphabet, $letter);
        $rows = Http::connectTimeout(3)->timeout(15)->get('https://www.thecocktaildb.com/api/json/v1/'.rawurlencode(config('privatebar.cocktaildb_key')).'/search.php', ['f' => $letter])->throw()->json('drinks') ?? [];
        $recipes = [];
        foreach ($rows as $row) {
            $lines = [];
            for ($i = 1; $i <= 15; $i++) {
                if (! empty($row['strIngredient'.$i])) {
                    $lines[] = ['name' => $row['strIngredient'.$i], 'measure' => $row['strMeasure'.$i] ?? '', 'role' => 'required'];
                }
            }
            $recipes[] = ['provider' => 'cocktaildb', 'external_id' => (string) $row['idDrink'], 'name' => $row['strDrink'],
                'instructions' => $row['strInstructions'] ?? '', 'language' => 'en', 'ingredients' => $lines,
                'translation' => $row['strInstructionsDE'] ?? null, 'alcoholic' => ($row['strAlcoholic'] ?? '') !== 'Non alcoholic',
                'glass' => $row['strGlass'] ?? null, 'method' => $row['strCategory'] ?? null,
                'url' => 'https://www.thecocktaildb.com/drink.php?c='.$row['idDrink'],
                'license' => 'TheCocktailDB – Nutzung gemäss Anbieterbedingungen; keine Bildübernahme ohne Lizenznachweis.', 'original' => $row];
        }

        return ['recipes' => $recipes, 'cursor' => $position === strlen($alphabet) - 1 ? 'a' : $alphabet[$position + 1], 'complete' => $position === strlen($alphabet) - 1];
    }
}
