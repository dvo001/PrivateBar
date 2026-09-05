<?php

namespace App\Domain\Recipes;

use App\Domain\Settings\Settings;
use App\Infrastructure\Providers\TranslationProvider;
use Illuminate\Support\Facades\DB;

final class Translator
{
    public function __construct(private TranslationProvider $provider, private Settings $settings, private RecipeWriter $writer) {}

    public function one(string $id): void
    {
        $this->settings->assertRunning();
        if (config('privatebar.mode') !== 'cloud') {
            throw new \RuntimeException('Übersetzungen laufen ausschliesslich auf Cyon.');
        }
        $row = DB::table('recipes')->where('id', $id)->first();
        if (! $row || $row->translation_manual || ! $row->translation_pending) {
            return;
        }
        $original = $row->original_text;
        $hash = hash('sha256', 'de:glossary-v1:'.$row->original_language.':'.$original);
        $translation = DB::table('translation_cache')->where('hash', $hash)->value('translation');
        if (! $translation) {
            $translation = $this->provider->translate($original, $row->original_language);
            $translation = str_ireplace(['highball glass', 'cocktail shaker', 'simple syrup', 'muddle', 'strain'], ['Longdrinkglas', 'Cocktailshaker', 'Zuckersirup', 'zerdrücken', 'abseihen'], $translation);
            DB::table('translation_cache')->insertOrIgnore(['hash' => $hash, 'translation' => $translation, 'created_at' => now()]);
        }
        DB::transaction(function () use ($id, $original, $translation) {
            $this->settings->assertRunning();
            $current = DB::table('recipes')->where('id', $id)->lockForUpdate()->first();
            if (! $current || $current->translation_manual || $current->original_text !== $original) {
                return;
            }
            DB::table('recipes')->where('id', $id)->update(['instructions' => $translation, 'translation_pending' => false, 'updated_at' => now(), 'version' => $current->version + 1]);
            $this->writer->publish($id);
        });
    }
}
