<?php

namespace App\Domain\Recipes;

final class Feasibility
{
    public const LABELS = ['Machbar', 'Mit Ersatz machbar', 'Fast machbar', 'Nicht machbar'];

    /** Ersatz ist genau eine gerichtete Regel, keine transitive Kette. */
    public function evaluate(array $lines, array $available, array $substitutions): array
    {
        $present = array_fill_keys($available, true);
        $missing = [];
        $replaced = false;
        $details = [];
        foreach ($lines as $line) {
            $id = $line['ingredient_id'];
            $replacement = null;
            if (isset($present[$id])) {
                $state = 'vorhanden';
            } elseif ($line['role'] !== 'required') {
                $state = $line['role'] === 'garnish' ? 'Garnitur' : 'optional';
            } else {
                foreach ($substitutions[$id] ?? [] as $candidate) {
                    if (isset($present[$candidate])) {
                        $replacement = $candidate;
                        break;
                    }
                }
                if ($replacement) {
                    $state = 'ersetzt';
                    $replaced = true;
                } else {
                    $state = 'fehlend';
                    $missing[$id] = $id;
                }
            }
            $details[] = array_merge($line, ['state' => $state, 'replacement_id' => $replacement]);
        }
        $rank = count($missing) >= 3 ? 3 : (count($missing) ? 2 : ($replaced ? 1 : 0));

        return ['rank' => $rank, 'label' => self::LABELS[$rank], 'missing' => array_values($missing), 'lines' => $details];
    }
}
