<?php

namespace App\Infrastructure\Providers;

use App\Domain\Settings\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

final class ApiNinjas implements RecipeProvider
{
    public function batch(string $cursor): array
    {
        if (config('privatebar.mode') !== 'cloud' || ! app(Settings::class)->serviceEnabled('recipe_import_enabled') || ! config('privatebar.api_ninjas_key')) {
            throw new \RuntimeException('API Ninjas ist noch nicht eingerichtet.');
        }
        $queries = array_values(array_unique(array_filter(array_map('trim', explode(',', (string) config('privatebar.api_ninjas_queries'))))));
        $position = ctype_digit($cursor) ? (int) $cursor : 0;
        if (! isset($queries[$position])) {
            return ['recipes' => [], 'cursor' => '', 'complete' => true];
        }
        DB::transaction(function () {
            $settings = app(Settings::class);
            $key = 'api_ninjas_calls_'.now()->timezone('Europe/Zurich')->format('Y-m');
            DB::table('local_settings')->insertOrIgnore(['key' => $key, 'value' => '0', 'created_at' => now(), 'updated_at' => now()]);
            $calls = (int) DB::table('local_settings')->where('key', $key)->lockForUpdate()->value('value');
            if ($calls >= (int) config('privatebar.api_ninjas_monthly_limit', 2800)) {
                throw new \RuntimeException('Das monatliche API-Ninjas-Kontingent ist ausgeschöpft.');
            }
            $settings->set($key, $calls + 1);
        });
        $response = Http::withHeaders(['X-Api-Key' => config('privatebar.api_ninjas_key')])->acceptJson()->withoutRedirecting()->connectTimeout(3)->timeout(15)
            ->get('https://api.api-ninjas.com/v1/cocktail', ['name' => $queries[$position]]);
        // Keine Anbieterantwort oder Zugangsdaten in Ausnahmen übernehmen.
        if (! $response->successful()) {
            throw new \RuntimeException('API Ninjas ist nicht erreichbar oder das Kontingent ist erschöpft.');
        }
        $rows = $response->json();
        if (! is_array($rows) || ! array_is_list($rows) || count($rows) > 10) {
            throw new \RuntimeException('API Ninjas liefert eine ungültige Antwort.');
        }
        $recipes = [];
        foreach ($rows as $row) {
            Validator::make(is_array($row) ? $row : [], ['name' => 'required|string|max:255', 'instructions' => 'required|string|max:50000',
                'ingredients' => 'required|array|min:1|max:50', 'ingredients.*' => 'required|string|max:255'])->validate();
            $lines = array_map(fn ($line) => $this->ingredient($line), $row['ingredients']);
            $identity = array_map(fn ($line) => Str::lower(Str::ascii($line['name'])), $lines);
            sort($identity);
            $recipes[] = ['provider' => 'api-ninjas', 'external_id' => hash('sha256', Str::lower(Str::ascii(trim($row['name']))).'|'.implode('|', $identity)),
                'name' => trim($row['name']), 'instructions' => $row['instructions'], 'language' => 'en', 'ingredients' => $lines,
                'url' => 'https://api-ninjas.com/api/cocktail', 'license' => 'API Ninjas – Nutzung gemäss Anbieterbedingungen: https://api-ninjas.com/tos', 'original' => $row];
        }

        return ['recipes' => $recipes, 'cursor' => $position + 1 < count($queries) ? (string) ($position + 1) : '', 'complete' => $position + 1 >= count($queries)];
    }

    private function ingredient(string $line): array
    {
        // Mengen nur bei erkennbarer Einheit abtrennen; unklare Angaben erhalten.
        $number = '(?:\d+(?:[.,]\d+)?|[.,]\d+|[¼½¾⅓⅔⅛⅜⅝⅞])';
        $quantity = $number.'(?:[ -]+\d+/\d+|\s*/\s*\d+)?(?:\s*(?:to|–|-|bis)\s*'.$number.')?';
        $units = 'fl\.?\s*oz\.?|fluid ounces?|oz|ounces?|ml|cl|dl|l|millilit(?:er|re)s?|centilit(?:er|re)s?|lit(?:er|re)s?|tsp\.?|tbsp\.?|teaspoons?|tablespoons?|dashes?|drops?|pinch(?:es)?|parts?|g|grams?|slices?|pieces?|cups?|shots?';
        if (preg_match('~^('.$quantity.'\s*(?:'.$units.'))\s+(?:\([^)]*\)\s*)?(?:of\s+)?(.+)$~iuD', trim($line), $match)) {
            return ['name' => trim($match[2]), 'measure' => trim($match[1]), 'role' => 'required'];
        }

        return ['name' => trim($line), 'measure' => '', 'role' => 'required'];
    }
}
