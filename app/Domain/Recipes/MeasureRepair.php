<?php

namespace App\Domain\Recipes;

use App\Domain\Settings\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class MeasureRepair
{
    public function run(bool $apply = false): int
    {
        app(Settings::class)->assertRunning();
        if (config('privatebar.mode') !== 'cloud') {
            throw new \RuntimeException('Importmengen werden ausschliesslich auf Cyon korrigiert.');
        }
        $changed = 0;
        DB::table('recipes')->where('household', false)->orderBy('id')->chunkById(100, function ($recipes) use ($apply, &$changed) {
            foreach ($recipes as $recipe) {
                $changed += DB::transaction(function () use ($recipe, $apply) {
                    app(Settings::class)->assertRunning();
                    $current = DB::table('recipes')->where('id', $recipe->id)->lockForUpdate()->first();
                    if (! $current || $current->household) {
                        return 0;
                    }
                    $lines = DB::table('recipe_ingredients')->where('recipe_id', $recipe->id)->orderBy('position')->get();
                    $dirty = false;
                    foreach ($lines as $line) {
                        if ($line->original_measure === null || trim($line->original_measure) === '') {
                            continue;
                        }
                        $normalized = (new Measures)->normalize($line->original_measure);
                        $sameAmount = $line->amount === null
                            ? $normalized['amount'] === null
                            : $normalized['amount'] !== null && (float) $line->amount === $normalized['amount'];
                        if ($sameAmount && $line->unit === $normalized['unit']) {
                            continue;
                        }
                        $dirty = true;
                        if ($apply) {
                            DB::table('recipe_ingredients')->where('id', $line->id)->update([
                                'amount' => $normalized['amount'], 'unit' => $normalized['unit'],
                            ]);
                        }
                        $line->amount = $normalized['amount'];
                        $line->unit = $normalized['unit'];
                    }
                    if ($dirty && $apply) {
                        $fingerprintLines = $lines->map(fn ($line) => $line->ingredient_id.':'.($line->amount === null ? '' : (float) $line->amount).':'.$line->unit)->all();
                        sort($fingerprintLines);
                        $fingerprint = hash('sha256', Str::lower(Str::ascii($current->name)).'|'.implode('|', $fingerprintLines));
                        DB::table('recipes')->where('id', $recipe->id)->update([
                            'fingerprint' => $fingerprint, 'version' => $current->version + 1, 'updated_at' => now(),
                        ]);
                        app(RecipeWriter::class)->publish($recipe->id);
                    }

                    return $dirty ? 1 : 0;
                });
            }
        });

        return $changed;
    }
}
