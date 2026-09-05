<?php

namespace App\Domain\Recipes;

final class Measures
{
    public function normalize(?string $input): array
    {
        $original = trim($input ?? '');
        $s = strtr(mb_strtolower($original), ['½' => ' 1/2', '¼' => ' 1/4', '¾' => ' 3/4', '⅓' => ' 1/3', '⅔' => ' 2/3', ',' => '.']);
        if (! preg_match('~^\s*(?:(\d+)\s+)?(\d+(?:\.\d+)?)(?:/(\d+))?\s*(.*)$~u', $s, $m)) {
            return ['amount' => null, 'unit' => null, 'original_measure' => $original];
        }
        $amount = (float) ($m[1] ?: 0) + (float) $m[2] / max(1, (float) ($m[3] ?: 1));
        $unit = trim($m[4]);
        $factor = match ($unit) {
            'oz','ounce','ounces','fl oz' => 2.957352956, 'ml' => 0.1, 'l' => 100, 'cl' => 1, 'tsp','teaspoon','teaspoons' => 0.5, 'tbsp','tablespoon','tablespoons' => 1.5, default => null
        };

        return ['amount' => round($amount * ($factor ?? 1), 3), 'unit' => $factor !== null ? 'cl' : ($unit ?: 'Stück'), 'original_measure' => $original];
    }
}
