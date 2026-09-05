<?php

namespace App\Domain\Recipes;

final class Alcohol
{
    public function estimate(array $lines, array $productAbv, array $fallbackAbv): ?float
    {
        $volume = 0.0;
        $alcohol = 0.0;
        foreach ($lines as $line) {
            if (! in_array($line['unit'], ['cl', 'ml', 'l'], true) || ! $line['amount']) {
                continue;
            }
            if (($line['role'] ?? 'required') !== 'required' && ($line['state'] ?? 'vorhanden') !== 'vorhanden') {
                continue;
            }
            $id = $line['replacement_id'] ?? $line['ingredient_id'];
            $v = (float) $line['amount'] * match ($line['unit']) {
                'ml' => 0.1, 'l' => 100, default => 1
            };
            $abv = $productAbv[$id] ?? $fallbackAbv[$id] ?? null;
            if ($abv === null) {
                return null;
            }
            $volume += $v;
            $alcohol += $v * $abv;
        }

        return $volume > 0 ? round($alcohol / $volume, 1) : null;
    }
}
