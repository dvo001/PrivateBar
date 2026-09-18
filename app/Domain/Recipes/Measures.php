<?php

namespace App\Domain\Recipes;

final class Measures
{
    public function display(array $line): string
    {
        $original = trim($line['original_measure'] ?? '');
        $fraction = strtr($original, ['⁄' => '/']);
        $fraction = preg_replace('~\\s*/\\s*~u', '/', $fraction);
        $fraction = preg_replace('~\\s+(?:parts?|teile?|anteile?)$~iu', '', $fraction);
        $fractions = ['1/2' => '½', '1/3' => '⅓', '2/3' => '⅔', '1/4' => '¼', '3/4' => '¾', '1/8' => '⅛', '3/8' => '⅜', '5/8' => '⅝', '7/8' => '⅞'];
        if (($line['unit'] ?? null) === 'Anteil' && (isset($fractions[$fraction]) || in_array($fraction, $fractions, true))) {
            return ($fractions[$fraction] ?? $fraction).' Anteil';
        }
        if (($line['amount'] ?? null) === null) {
            $number = '(?:\\d+(?:[.,]\\d+)?|[.,]\\d+)';
            if (preg_match('~^('.$number.')\\s*(?:-|–|—|to|bis)\\s*('.$number.')\\s*([^\\d]+)$~iuD', $original, $range)) {
                $low = $this->normalize($range[1].' '.$range[3]);
                $high = $this->normalize($range[2].' '.$range[3]);
                if ($low['unit'] === 'cl' && $high['unit'] === 'cl' && $low['amount'] <= $high['amount']) {
                    return $this->volumeNumber($low['amount']).'–'.$this->volumeNumber($high['amount']).' cl';
                }
            }

            return $fractions[$original] ?? $original;
        }

        if (($line['unit'] ?? null) === 'cl') {
            return $this->volumeNumber((float) $line['amount']).' cl';
        }

        return trim(rtrim(rtrim(number_format((float) $line['amount'], 3, '.', ''), '0'), '.').' '.($line['unit'] ?? ''));
    }

    private function volumeNumber(float $amount): string
    {
        // Normal bar measures use 0.1 cl; preserve genuinely smaller quantities.
        $precision = $amount > 0 && $amount < 0.1 ? 3 : 1;

        return rtrim(rtrim(number_format($amount, $precision, '.', ''), '0'), '.');
    }

    public function normalize(?string $input): array
    {
        $original = trim($input ?? '');
        $unknown = ['amount' => null, 'unit' => null, 'original_measure' => $original];
        $s = strtr(mb_strtolower($original), [
            '½' => ' 1/2', '¼' => ' 1/4', '¾' => ' 3/4', '⅓' => ' 1/3', '⅔' => ' 2/3',
            '⅛' => ' 1/8', '⅜' => ' 3/8', '⅝' => ' 5/8', '⅞' => ' 7/8', ',' => '.', '⁄' => '/',
        ]);
        $s = preg_replace('/\s+/u', ' ', trim($s));
        // Match complete quantities: a range or an annotation must never become a unit.
        if (! preg_match('~^(?:(?<whole>\d+)[ -]+)?(?<numerator>\d+)\s*/\s*(?<denominator>\d+)\s*(?<unit>[^\d]*)$~uD', $s, $m)) {
            if (! preg_match('~^(?<numerator>(?:\d+(?:\.\d+)?|\.\d+))\s*(?<unit>[^\d]*)$~uD', $s, $m)) {
                return $unknown;
            }
        }
        $denominator = isset($m['denominator']) ? (float) $m['denominator'] : 1;
        if ($denominator == 0) {
            return $unknown;
        }
        $amount = (float) ($m['whole'] ?? 0) + (float) $m['numerator'] / $denominator;
        $unit = trim($m['unit']);
        $factor = match ($unit) {
            'oz', 'ounce', 'ounces', 'fl oz', 'fl. oz.', 'fluid ounce', 'fluid ounces' => 2.957352956,
            'ml', 'milliliter', 'milliliters', 'millilitre', 'millilitres' => 0.1,
            'cl', 'centiliter', 'centiliters', 'centilitre', 'centilitres', 'zentiliter' => 1,
            'dl', 'deciliter', 'deciliters', 'decilitre', 'decilitres', 'deziliter' => 10,
            'l', 'liter', 'liters', 'litre', 'litres' => 100,
            'tsp', 'tsp.', 'teaspoon', 'teaspoons', 'tl', 'teelöffel' => 0.5,
            'tbsp', 'tbsp.', 'tablespoon', 'tablespoons', 'el', 'esslöffel' => 1.5,
            default => null,
        };
        if ($factor !== null) {
            return ['amount' => round($amount * $factor, 3), 'unit' => 'cl', 'original_measure' => $original];
        }
        $normalizedUnit = match ($unit) {
            'g', 'gram', 'grams', 'gramm' => 'g',
            'kg', 'kilogram', 'kilograms', 'kilogramm' => 'g',
            'part', 'parts', 'teil', 'teile', 'anteil', 'anteile' => 'Anteil',
            '%', 'percent', 'prozent' => '%',
            'piece', 'pieces', 'stück' => 'Stück',
            'dash', 'dashes', 'spritzer' => 'Spritzer',
            'drop', 'drops', 'tropfen' => 'Tropfen',
            'pinch', 'pinches', 'prise', 'prisen' => 'Prise',
            'slice', 'slices', 'scheibe', 'scheiben' => 'Scheibe',
            default => null,
        };
        // Unitless quantities, cups, shots and unknown qualifiers stay as written.
        if ($normalizedUnit === null) {
            return $unknown;
        }
        if (in_array($unit, ['kg', 'kilogram', 'kilograms', 'kilogramm'], true)) {
            $amount *= 1000;
        }

        return ['amount' => round($amount, 3), 'unit' => $normalizedUnit, 'original_measure' => $original];
    }
}
