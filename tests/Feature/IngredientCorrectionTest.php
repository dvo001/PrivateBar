<?php

namespace Tests\Feature;

use App\Domain\Sync\Projector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PrivateBar\Correction\Correction;
use RuntimeException;
use Tests\TestCase;

final class IngredientCorrectionTest extends TestCase
{
    use RefreshDatabase;

    private array $plan;

    protected function setUp(): void
    {
        parent::setUp();
        require_once base_path('tools/ingredient-correction/Correction.php');
        config(['privatebar.mode' => 'cloud']);
        $this->plan = json_decode(file_get_contents(base_path('tools/ingredient-correction/manifest.json')), true, 512, JSON_THROW_ON_ERROR);
        foreach ($this->plan['categories'] as $id => $definition) {
            if ($definition['before']) {
                DB::table('ingredient_categories')->insert(['id' => $id] + $definition['before']);
            }
        }
        foreach ($this->plan['ingredients'] as $definition) {
            DB::table('ingredients')->insert(['id' => $definition['id'], 'automatic' => false] + $definition['before']);
        }
        foreach ($this->plan['optional_automatic'] as $definition) {
            DB::table('ingredients')->insertOrIgnore($definition + ['category_id' => 'other']);
            DB::table('ingredients')->where('id', $definition['id'])->update(['automatic' => true]);
        }
        $newIds = array_column($this->plan['new_ingredients'], 'id');
        foreach ($this->plan['products'] as $definition) {
            foreach ([$definition['before'], $definition['after']] as $id) {
                if (! in_array($id, $newIds, true)) {
                    DB::table('ingredients')->insertOrIgnore(['id' => $id, 'name' => 'Fixture '.$id, 'category_id' => 'other']);
                }
            }
            DB::table('products')->insert(['id' => $definition['id'], 'name' => $definition['name'], 'barcode' => $definition['barcode'], 'abv' => 21]);
            DB::table('product_ingredient_mappings')->insert(['product_id' => $definition['id'], 'ingredient_id' => $definition['before']]);
        }
        DB::table('bar_inventory')->insert(['product_id' => $this->plan['products'][0]['id'], 'created_at' => now()]);
    }

    public function test_preview_rolls_back_and_apply_preserves_stock_and_is_idempotent(): void
    {
        $before = DB::table('ingredients')->count();
        $correction = new Correction;
        $preview = $correction->run($this->plan);
        self::assertNotEmpty($preview);
        self::assertSame($before, DB::table('ingredients')->count());
        $this->assertDatabaseCount('sync_events', 0);
        $this->assertDatabaseCount('audit_entries', 0);
        self::assertSame($preview, $correction->run($this->plan, true));
        $this->assertDatabaseCount('bar_inventory', 1);
        $this->assertDatabaseCount('products', 8);
        $this->assertDatabaseCount('ingredients', $before + 5);
        foreach (DB::table('sync_events')->orderBy('sequence')->get() as $event) {
            $payload = json_decode($event->payload, true);
            app(Projector::class)->validate($event->entity, $event->entity_id, $payload, false);
        }
        $events = DB::table('sync_events')->count();
        self::assertSame([], $correction->run($this->plan, true));
        self::assertSame($events, DB::table('sync_events')->count());
        self::assertSame(37, DB::table('ingredients')->where('automatic', true)->count());
        self::assertCount(37, $correction->run($this->plan, true, false, true));
        self::assertSame(0, DB::table('ingredients')->where('automatic', true)->count());
        self::assertSame([], $correction->run($this->plan, true, false, true));
    }

    public function test_late_mapping_conflict_rolls_back_categories_ingredients_and_journal(): void
    {
        $last = $this->plan['products'][7];
        DB::table('product_ingredient_mappings')->where('product_id', $last['id'])->delete();
        try {
            (new Correction)->run($this->plan, true);
            self::fail('Konflikt muss abbrechen');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('Produktzuordnung verändert', $exception->getMessage());
        }
        $this->assertDatabaseCount('sync_events', 0);
        $this->assertDatabaseCount('audit_entries', 0);
        $this->assertDatabaseMissing('ingredient_categories', ['id' => 'brandy']);
        $this->assertDatabaseMissing('ingredients', ['name' => 'Limoncello']);
    }

    public function test_pi_can_only_prepare_categories_without_sync_events(): void
    {
        config(['privatebar.mode' => 'pi']);
        (new Correction)->run($this->plan, true, true);
        $this->assertDatabaseHas('ingredient_categories', ['id' => 'brandy']);
        $this->assertDatabaseCount('sync_events', 0);
        $this->expectException(RuntimeException::class);
        (new Correction)->run($this->plan, true);
    }

    public function test_rename_does_not_steal_existing_synonym(): void
    {
        DB::table('ingredient_synonyms')->insert(['name' => 'grappa', 'ingredient_id' => $this->plan['ingredients'][0]['id']]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Synonymkonflikt');
        (new Correction)->run($this->plan, true);
    }
}
