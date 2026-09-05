<?php

namespace App\Infrastructure\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

final class OpenFoodFacts implements ProductProvider
{
    public function lookup(string $barcode): ?array
    {
        if (! preg_match('/^[0-9]{8,14}$/D', $barcode)) {
            throw new \InvalidArgumentException('Der Barcode muss 8 bis 14 Ziffern enthalten.');
        }
        $cache = DB::table('provider_cache')->where('key', 'off:'.$barcode)->where('expires_at', '>', now())->first();
        if ($cache) {
            return json_decode($cache->payload, true, 512, JSON_THROW_ON_ERROR);
        }
        if (! config('privatebar.providers_enabled')) {
            return null;
        }
        if (RateLimiter::tooManyAttempts('off-products', 60)) {
            throw new \RuntimeException('Die Produktsuche ist ausgelastet. Bitte in einer Minute erneut versuchen.');
        }
        RateLimiter::hit('off-products', 60);
        $response = Http::withUserAgent(config('privatebar.off_user_agent'))->connectTimeout(3)->timeout(8)
            ->get('https://world.openfoodfacts.org/api/v2/product/'.$barcode.'.json', ['fields' => 'code,product_name,product_name_de,brands,image_front_small_url,nutriments,categories_tags']);
        if ($response->status() === 404) {
            $result = null;
        } else {
            $response->throw();
            $p = $response->json('product');
            $result = $p ? ['barcode' => $barcode, 'name' => $p['product_name_de'] ?? $p['product_name'] ?? '', 'brand' => $p['brands'] ?? '',
                'abv' => $p['nutriments']['alcohol_100g'] ?? null, 'image_url' => $p['image_front_small_url'] ?? null,
                'categories' => $p['categories_tags'] ?? [], 'source' => 'Open Food Facts', 'license' => 'Daten: ODbL; Bilder: CC BY-SA 3.0 – Open Food Facts und Beitragende.'] : null;
        }
        DB::table('provider_cache')->updateOrInsert(['key' => 'off:'.$barcode], ['payload' => json_encode($result, JSON_THROW_ON_ERROR), 'expires_at' => now()->addDays($result ? 30 : 1)]);

        return $result;
    }
}
