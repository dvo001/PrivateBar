<?php

namespace Tests\Unit;

use App\Domain\Recipes\Alcohol;
use App\Domain\Recipes\Feasibility;
use App\Domain\Recipes\Measures;
use PHPUnit\Framework\TestCase;

final class RecipeLogicTest extends TestCase
{
    private function lines(array $ids): array
    {
        return array_map(fn ($id) => ['ingredient_id' => $id, 'role' => 'required'], $ids);
    }

    public function test_all_four_statuses_and_directed_multiple_replacements(): void
    {
        $f = new Feasibility;
        $lines = $this->lines(['gin', 'lemon', 'syrup']);
        self::assertSame(0, $f->evaluate($lines, ['gin', 'lemon', 'syrup'], [])['rank']);
        self::assertSame(1, $f->evaluate($lines, ['vodka', 'lime', 'syrup'], ['gin' => ['vodka'], 'lemon' => ['lime']])['rank']);
        self::assertSame(2, $f->evaluate($lines, ['gin'], [])['rank']);
        self::assertSame(3, $f->evaluate($lines, [], [])['rank']);
        self::assertSame(2, $f->evaluate($this->lines(['lime']), ['lemon'], ['lemon' => ['lime']])['rank']);
        self::assertSame(2, $f->evaluate($this->lines(['a']), ['c'], ['a' => ['b'], 'b' => ['c']])['rank']);
    }

    public function test_optional_garnish_and_duplicate_missing_ingredients(): void
    {
        $lines = [['ingredient_id' => 'mint', 'role' => 'optional'], ['ingredient_id' => 'lime', 'role' => 'garnish']];
        $r = (new Feasibility)->evaluate($lines, [], []);
        self::assertSame(0, $r['rank']);
        self::assertSame('Garnitur', $r['lines'][1]['state']);
        self::assertSame(2, (new Feasibility)->evaluate($this->lines(['a', 'a', 'b']), [], [])['rank']);
    }

    public function test_abv_uses_actual_value_zero_and_category_fallback_without_ice(): void
    {
        $lines = [['ingredient_id' => 'gin', 'amount' => 4, 'unit' => 'cl'], ['ingredient_id' => 'tonic', 'amount' => 120, 'unit' => 'ml'], ['ingredient_id' => 'ice', 'amount' => 100, 'unit' => 'g']];
        self::assertSame(10.0, (new Alcohol)->estimate($lines, [], ['gin' => 40, 'tonic' => 0]));
        self::assertSame(0.0, (new Alcohol)->estimate($lines, ['gin' => 0], ['gin' => 40, 'tonic' => 0]));
        self::assertNull((new Alcohol)->estimate([], [], []));
    }

    public function test_metric_measures_preserve_original_and_fractions(): void
    {
        $m = new Measures;
        self::assertSame(0.5, $m->normalize('5 ml')['amount']);
        self::assertSame(4.436, $m->normalize('1 1/2 oz')['amount']);
        self::assertSame(1.479, $m->normalize('½ oz')['amount']);
        self::assertSame('a splash', $m->normalize('a splash')['original_measure']);
        self::assertNull($m->normalize('a splash')['amount']);
    }
}
