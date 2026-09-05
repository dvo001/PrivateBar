<?php

namespace App\Infrastructure\Providers;

interface TranslationProvider
{
    public function translate(string $text, string $language): string;
}
