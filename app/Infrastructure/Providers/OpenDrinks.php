<?php

namespace App\Infrastructure\Providers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

final class OpenDrinks implements RecipeProvider
{
    public function batch(string $cursor): array
    {
        if (config('privatebar.mode') !== 'cloud' || ! config('privatebar.providers_enabled')) {
            throw new \RuntimeException('OpenDrinks ist noch nicht eingerichtet.');
        }
        $files = Cache::remember('opendrinks-file-list', 3600, fn () => Http::withHeaders(['Accept' => 'application/vnd.github+json'])->withUserAgent('PrivateBar/1.0')->connectTimeout(3)->timeout(15)
            ->get('https://api.github.com/repos/alfg/opendrinks/contents/src/recipes')->throw()->json());
        $files = array_values(array_filter($files, fn ($f) => ($f['type'] ?? '') === 'file' && str_ends_with($f['name'], '.json')));
        usort($files, fn ($a, $b) => strcmp($a['name'], $b['name']));
        $eligible = array_values(array_filter($files, fn ($f) => strcmp($f['name'], $cursor) > 0));
        $recipes = [];
        $next = $cursor;
        foreach (array_slice($eligible, 0, 5) as $file) {
            $row = Http::connectTimeout(3)->timeout(8)->get('https://raw.githubusercontent.com/alfg/opendrinks/master/src/recipes/'.rawurlencode($file['name']))->throw()->json();
            $lines = [];
            foreach ($row['ingredients'] ?? [] as $line) {
                if (is_string($line)) {
                    $lines[] = ['name' => $line, 'measure' => '', 'role' => 'required'];
                } else {
                    $lines[] = ['name' => $line['ingredient'] ?? $line['name'] ?? '', 'measure' => trim(($line['quantity'] ?? '').' '.($line['measure'] ?? '')), 'role' => 'required'];
                }
            }
            $recipes[] = ['provider' => 'opendrinks', 'external_id' => $file['name'], 'name' => $row['name'], 'instructions' => implode("\n", $row['directions'] ?? []), 'language' => 'en', 'ingredients' => $lines, 'alcoholic' => ! in_array('non-alcoholic', $row['keywords'] ?? [], true), 'url' => 'https://github.com/alfg/opendrinks/blob/master/src/recipes/'.rawurlencode($file['name']), 'license' => 'MIT – OpenDrinks und Beitragende; https://github.com/alfg/opendrinks/blob/master/LICENSE', 'original' => $row];
            $next = $file['name'];
        }

        return ['recipes' => $recipes, 'cursor' => count($eligible) <= 5 ? '' : $next, 'complete' => count($eligible) <= 5];
    }
}
