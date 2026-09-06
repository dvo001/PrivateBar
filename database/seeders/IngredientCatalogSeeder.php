<?php

namespace Database\Seeders;

use App\Domain\Recipes\IngredientCatalog;
use Illuminate\Database\Seeder;

final class IngredientCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $result = app(IngredientCatalog::class)->install();
        $this->command?->info($result['changed'].' Zutaten ergänzt oder aktualisiert.');
        if ($result['conflicts']) {
            $this->command?->warn('Private Zuordnungen beibehalten: '.implode(', ', $result['conflicts']));
        }
    }
}
