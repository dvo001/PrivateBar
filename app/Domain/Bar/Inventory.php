<?php

namespace App\Domain\Bar;

use App\Domain\Settings\Settings;
use App\Domain\Sync\Journal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;

final class Inventory
{
    public function __construct(private Journal $journal, private Settings $settings) {}

    public function save(array $data): string
    {
        $this->settings->assertRunning();

        return DB::transaction(function () use ($data) {
            $this->settings->assertRunning();
            $barcode = $data['barcode'] ?? null;
            $existing = $barcode ? DB::table('products')->where('barcode', $barcode)->lockForUpdate()->first() : null;
            $id = $existing->id ?? ($barcode ? Uuid::uuid5(Uuid::NAMESPACE_URL, 'privatebar:barcode:'.$barcode)->toString() : ($data['id'] ?? (string) Str::uuid()));
            $product = ['name' => $data['name'], 'brand' => $data['brand'] ?? null, 'barcode' => $barcode ?: null,
                'abv' => $data['abv'] ?? null, 'generic' => (bool) ($data['generic'] ?? false), 'manually_corrected' => true,
                'image_path' => $data['image_path'] ?? $existing?->image_path, 'source' => $data['source'] ?? $existing?->source,
                'license' => $data['license'] ?? $existing?->license, 'updated_at' => now()];
            DB::table('products')->updateOrInsert(['id' => $id], array_merge($product, ['created_at' => $existing->created_at ?? now()]));
            DB::table('product_ingredient_mappings')->where('product_id', $id)->delete();
            DB::table('product_ingredient_mappings')->insert(['product_id' => $id, 'ingredient_id' => $data['ingredient_id'], 'manual' => true]);
            DB::table('bar_inventory')->updateOrInsert(['product_id' => $id], ['created_at' => now()]);
            $this->journal->record('product', $id, $this->snapshot($id));
            // Ein Barcodeprodukt ersetzt einen früheren generischen Eintrag dieser Zutat.
            if ($barcode) {
                $genericIds = DB::table('products')->join('product_ingredient_mappings', 'products.id', '=', 'product_id')
                    ->where('generic', true)->where('ingredient_id', $data['ingredient_id'])->pluck('products.id');
                foreach ($genericIds as $genericId) {
                    $this->remove($genericId);
                }
            }

            return $id;
        });
    }

    public function snapshot(string $id): array
    {
        $p = (array) DB::table('products')->where('id', $id)->first();
        $p['ingredient_id'] = DB::table('product_ingredient_mappings')->where('product_id', $id)->value('ingredient_id');
        $p['present'] = DB::table('bar_inventory')->where('product_id', $id)->exists();
        unset($p['created_at'],$p['updated_at']);

        return $p;
    }

    public function remove(string $id): void
    {
        $this->settings->assertRunning();
        DB::transaction(function () use ($id) {
            $this->settings->assertRunning();
            abort_unless(DB::table('products')->where('id', $id)->exists(), 404);
            DB::table('bar_inventory')->where('product_id', $id)->delete();
            // Produktkorrekturen bleiben beim Entfernen aus dem Bestand erhalten.
            $this->journal->record('product', $id, $this->snapshot($id));
        });
    }

    public function list()
    {
        return DB::table('bar_inventory')->join('products', 'bar_inventory.product_id', '=', 'products.id')
            ->join('product_ingredient_mappings', 'products.id', '=', 'product_ingredient_mappings.product_id')
            ->join('ingredients', 'ingredient_id', '=', 'ingredients.id')
            ->select('products.*', 'ingredients.name as ingredient_name')->orderBy('products.name')->paginate(30);
    }
}
