<?php

namespace App\Http\Controllers;

use App\Domain\Bar\Inventory;
use App\Domain\Bar\ShoppingList;
use App\Domain\Photos\ImageProcessor;
use App\Infrastructure\Providers\ProductProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

final class BarController
{
    public function index(Inventory $inventory)
    {
        return view('bar.index', ['products' => $inventory->list()]);
    }

    public function form()
    {
        return view('bar.form', ['product' => [], 'ingredients' => DB::table('ingredients')->orderBy('name')->get(), 'present' => false, 'scanning' => false]);
    }

    public function scanner()
    {
        return view('bar.scan');
    }

    public function lookup(Request $request, ProductProvider $provider, Inventory $inventory, ImageProcessor $images)
    {
        $data = $request->validate(['barcode' => 'required|regex:/^[0-9]{8,14}$/D']);
        $barcode = $data['barcode'];
        $existing = DB::table('products')->where('barcode', $barcode)->first();
        $message = null;
        if ($existing) {
            $product = $inventory->snapshot($existing->id);
        } else {
            try {
                $product = $provider->lookup($barcode) ?? ['barcode' => $barcode];
            } catch (\Throwable) {
                $product = ['barcode' => $barcode];
                $message = 'Die Produktsuche ist gerade nicht erreichbar. Du kannst die Flasche manuell erfassen.';
            }
            if (! empty($product['image_url']) && parse_url($product['image_url'], PHP_URL_HOST) === 'images.openfoodfacts.org') {
                $tmp = tempnam(sys_get_temp_dir(), 'privatebar-off-');
                try {
                    Http::connectTimeout(2)->timeout(5)->withOptions(['sink' => $tmp, 'allow_redirects' => false])->get($product['image_url'])->throw();
                    $product['image_path'] = $images->compress($tmp, 'products')['path'];
                } catch (\Throwable) { /* Ein Bildfehler verhindert keine Produkterfassung. */
                } finally {
                    @unlink($tmp);
                }
            }
            $product['ingredient_id'] = $this->suggest($product);
        }
        $request->session()->put('barcode_confirmation', ['barcode' => $barcode, 'source' => $product['source'] ?? null, 'license' => $product['license'] ?? null, 'image_path' => $product['image_path'] ?? null]);

        return view('bar.form', ['product' => $product, 'ingredients' => DB::table('ingredients')->orderBy('name')->get(), 'present' => $existing && DB::table('bar_inventory')->where('product_id', $existing->id)->exists(), 'scanning' => true, 'lookupMessage' => $message]);
    }

    private function suggest(array $product): ?string
    {
        $text = mb_strtolower(($product['name'] ?? '').' '.implode(' ', $product['categories'] ?? []));
        $synonyms = DB::table('ingredient_synonyms')->orderByRaw('LENGTH(name) DESC')->get();
        foreach ($synonyms as $synonym) {
            if (mb_strlen($synonym->name) > 2 && preg_match('/(?<![\pL])'.preg_quote($synonym->name, '/').'(?![\pL])/u', $text)) {
                return $synonym->ingredient_id;
            }
        }

        return null;
    }

    public function save(Request $request, Inventory $inventory)
    {
        $data = $request->validate(['name' => 'required|string|max:255', 'brand' => 'nullable|string|max:255', 'barcode' => 'nullable|regex:/^[0-9]{8,14}$/D', 'abv' => 'nullable|numeric|between:0,100', 'ingredient_id' => 'required|uuid|exists:ingredients,id', 'confirmed' => 'accepted']);
        $confirmation = $request->session()->get('barcode_confirmation', []);
        if (($confirmation['barcode'] ?? null) === ($data['barcode'] ?? null)) {
            $data += array_intersect_key($confirmation, array_flip(['source', 'license', 'image_path']));
        }
        $inventory->save($data);
        $request->session()->forget('barcode_confirmation');

        return redirect('/meine-bar')->with('message', 'Flasche zum Barbestand hinzugefügt.');
    }

    public function remove(Request $request, string $id, Inventory $inventory)
    {
        $request->validate(['confirmed' => 'accepted']);
        $inventory->remove($id);

        return redirect('/meine-bar')->with('message', 'Flasche aus dem Bestand entfernt.');
    }

    public function shopping()
    {
        return view('bar.shopping', ['items' => DB::table('shopping_list_items')->join('ingredients', 'ingredient_id', '=', 'ingredients.id')->join('ingredient_categories', 'category_id', '=', 'ingredient_categories.id')->select('ingredients.*', 'ingredient_categories.name as category')->orderBy('ingredient_categories.name')->orderBy('ingredients.name')->get()->groupBy('category')]);
    }

    public function addShopping(Request $request, ShoppingList $shopping)
    {
        $data = $request->validate(['ingredients' => 'required|array|min:1|max:50', 'ingredients.*' => 'required|uuid|exists:ingredients,id']);
        $shopping->add($data['ingredients']);

        return back()->with('message', 'Zur Einkaufsliste hinzugefügt.');
    }

    public function purchase(string $id, ShoppingList $shopping)
    {
        $shopping->purchased($id);

        return back()->with('message', 'Gekauft und als generischer Eintrag zur Bar hinzugefügt.');
    }

    public function removeShopping(string $id, ShoppingList $shopping)
    {
        $shopping->remove($id);

        return back();
    }
}
