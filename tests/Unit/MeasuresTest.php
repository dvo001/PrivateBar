<?php

namespace Tests\Unit;

use App\Domain\Recipes\Measures;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MeasuresTest extends TestCase
{
    #[DataProvider('quantities')]
    public function test_normalizes_complete_quantities(string $original, float $amount, string $unit): void
    {
        self::assertSame(['amount' => $amount, 'unit' => $unit, 'original_measure' => $original], (new Measures)->normalize($original));
    }

    public function test_display_retains_exact_fractions_without_guessing_a_volume(): void
    {
        $measures = new Measures;
        self::assertSame('½', $measures->display($measures->normalize('1/2')));
        self::assertSame('⅓', $measures->display($measures->normalize('1/3')));
        self::assertSame('⅓ Anteil', $measures->display($measures->normalize('1/3 part')));
        self::assertSame('⅔ Anteil', $measures->display($measures->normalize('⅔ parts')));
        self::assertSame('33 %', $measures->display($measures->normalize('33%')));
        self::assertSame('0.5 cl', $measures->display($measures->normalize('5 ml')));
        self::assertSame('2.2 cl', $measures->display($measures->normalize('3/4 oz')));
        self::assertSame('5.9–8.9 cl', $measures->display($measures->normalize('2 -3 oz')));
        self::assertSame('5.9–8.9 cl', $measures->display($measures->normalize('2–3 oz')));
        self::assertSame('0.01 cl', $measures->display($measures->normalize('0.1 ml')));
        self::assertSame('0 cl', $measures->display($measures->normalize('0 cl')));
        self::assertSame('3-2 oz', $measures->display($measures->normalize('3-2 oz')));
        self::assertSame('2 Stück', $measures->display($measures->normalize('2 pieces')));
    }

    public static function quantities(): array
    {
        return [
            ['1-1/2 oz', 4.436, 'cl'], ['1½ oz', 4.436, 'cl'],
            ['.5 oz', 1.479, 'cl'], ['1 / 2 oz', 1.479, 'cl'],
            ['⅛ oz', 0.37, 'cl'], ['1⁄2 oz', 1.479, 'cl'],
            ['1,5 CL', 1.5, 'cl'], ['5 millilitres', 0.5, 'cl'],
            ['1 dl', 10.0, 'cl'], ['0.1 litre', 10.0, 'cl'],
            ['2 fl. oz.', 5.915, 'cl'], ['1 fluid ounce', 2.957, 'cl'],
            ['2 TL', 1.0, 'cl'], ['1 tbsp.', 1.5, 'cl'],
            ['0.05 kg', 50.0, 'g'], ['10 grams', 10.0, 'g'],
            ['2 dashes', 2.0, 'Spritzer'], ['3 drops', 3.0, 'Tropfen'],
            ['1 part', 1.0, 'Anteil'], ['33%', 33.0, '%'],
        ];
    }

    #[DataProvider('uncertainQuantities')]
    public function test_preserves_ambiguous_or_invalid_quantities_without_inventing_units(string $original): void
    {
        self::assertSame(['amount' => null, 'unit' => null, 'original_measure' => $original], (new Measures)->normalize($original));
    }

    public static function uncertainQuantities(): array
    {
        return array_map(fn ($text) => [$text], [
            '1/3', '⅓', '2', '1/0 oz', '1-2 oz', '1 to 2 oz',
            '1 oz (30 ml)', '2 cups', '1 shot', 'a splash', 'Juice of 1 lime',
            '-1 cl', '1.5.5 cl', '1/2/3 oz', '',
        ]);
    }
}
