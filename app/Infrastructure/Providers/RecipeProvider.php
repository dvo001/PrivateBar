<?php

namespace App\Infrastructure\Providers;

interface RecipeProvider
{
    /** Liefert einen begrenzten Batch mit internem DTO und fortsetzbarem Cursor. */
    public function batch(string $cursor): array;
}
