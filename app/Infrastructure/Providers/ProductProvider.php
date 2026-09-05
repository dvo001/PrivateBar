<?php

namespace App\Infrastructure\Providers;

interface ProductProvider
{
    public function lookup(string $barcode): ?array;
}
