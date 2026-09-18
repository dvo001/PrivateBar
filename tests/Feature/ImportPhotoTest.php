<?php

namespace Tests\Feature;

use App\Domain\Photos\ImageProcessor;
use App\Domain\Photos\PhotoCache;
use App\Domain\Recipes\Catalog;
use App\Domain\Recipes\Importer;
use App\Domain\Recipes\MeasureRepair;
use App\Domain\Recipes\Measures;
use App\Domain\Recipes\RecipeWriter;
use App\Domain\Recipes\Translator;
use App\Domain\Settings\Settings;
use App\Infrastructure\Providers\OpenFoodFacts;
use App\Infrastructure\Providers\TranslationProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

final class ImportPhotoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake('local');
    }

    private function dto(): array
    {
        return ['provider' => 'opendrinks', 'external_id' => 'test.json', 'name' => 'Test drink', 'instructions' => 'Stir with ice.', 'language' => 'en', 'ingredients' => [['name' => 'gin', 'measure' => '2 oz', 'role' => 'required']], 'url' => 'https://github.com/alfg/opendrinks', 'license' => 'MIT', 'original' => ['v' => 1]];
    }

    public function test_import_deduplicates_retains_license_and_preserves_manual_translation(): void
    {
        config(['privatebar.mode' => 'cloud']);
        $i = app(Importer::class);
        $dto = $this->dto();
        $id = $i->ingest($dto);
        self::assertSame($id, $i->ingest($dto));
        self::assertSame(1, DB::table('recipe_sources')->count());
        self::assertSame('MIT', DB::table('recipe_sources')->value('license'));
        $other = $dto;
        $other['external_id'] = 'other.json';
        self::assertSame($id, $i->ingest($other));
        app(RecipeWriter::class)->translateManually($id, 'Von Hand übersetzt.');
        $dto['original']['v'] = 2;
        $dto['instructions'] = 'Shake with ice.';
        $i->ingest($dto);
        self::assertSame('Von Hand übersetzt.', DB::table('recipes')->where('id', $id)->value('instructions'));
        $mock = $this->mock(TranslationProvider::class);
        $mock->shouldNotReceive('translate');
        app(Translator::class)->one($id);
    }

    public function test_measure_repair_preserves_private_recipes_and_publishes_only_actual_changes(): void
    {
        config(['privatebar.mode' => 'cloud']);
        $dto = $this->dto();
        $dto['ingredients'][0]['measure'] = '1-1/2 oz';
        $id = app(Importer::class)->ingest($dto);
        $line = DB::table('recipe_ingredients')->where('recipe_id', $id)->first();
        DB::table('recipe_ingredients')->where('recipe_id', $id)->update(['amount' => 1, 'unit' => '-1/2 oz']);
        $copy = app(RecipeWriter::class)->save([
            'name' => 'Eigene Mengen', 'instructions' => 'Von Hand',
            'ingredients' => [['ingredient_id' => $line->ingredient_id, 'amount' => 6, 'unit' => 'cl', 'role' => 'required', 'original_measure' => '1-1/2 oz']],
        ], $id);
        app(RecipeWriter::class)->translateManually($id, 'Manuell übersetzt.');
        $events = DB::table('sync_events')->count();
        $repair = app(MeasureRepair::class);
        self::assertSame(1, $repair->run());
        self::assertSame('-1/2 oz', DB::table('recipe_ingredients')->where('recipe_id', $id)->value('unit'));
        self::assertSame($events, DB::table('sync_events')->count());
        self::assertSame(1, $repair->run(true));
        self::assertSame(4.436, (float) DB::table('recipe_ingredients')->where('recipe_id', $id)->value('amount'));
        self::assertSame('cl', DB::table('recipe_ingredients')->where('recipe_id', $id)->value('unit'));
        self::assertSame('1-1/2 oz', DB::table('recipe_ingredients')->where('recipe_id', $id)->value('original_measure'));
        self::assertSame(6.0, (float) DB::table('recipe_ingredients')->where('recipe_id', $copy)->value('amount'));
        self::assertSame('Manuell übersetzt.', DB::table('recipes')->where('id', $id)->value('instructions'));
        self::assertSame($events + 1, DB::table('sync_events')->count());
        $payload = json_decode(DB::table('sync_events')->orderByDesc('sequence')->value('payload'), true);
        self::assertSame(4.436, (float) $payload['ingredients'][0]['amount']);
        self::assertSame(0, $repair->run(true));
        self::assertSame($events + 1, DB::table('sync_events')->count());
        self::assertSame($id, app(Importer::class)->ingest($dto));
        $dto['external_id'] = 'second-source.json';
        self::assertSame($id, app(Importer::class)->ingest($dto));
    }

    public function test_fuzzy_asshole_halves_are_not_pieces_and_existing_import_is_repaired(): void
    {
        config(['privatebar.mode' => 'cloud']);
        $dto = $this->dto();
        $dto['name'] = 'Fuzzy Asshole';
        $dto['ingredients'] = [
            ['name' => 'Coffee', 'measure' => '1/2 '],
            ['name' => 'Peach schnapps', 'measure' => '1/2 '],
        ];
        $id = app(Importer::class)->ingest($dto);
        DB::table('recipe_ingredients')->where('recipe_id', $id)->update(['amount' => 0.5, 'unit' => 'Stück']);
        self::assertSame(1, app(MeasureRepair::class)->run(true));
        foreach (DB::table('recipe_ingredients')->where('recipe_id', $id)->get() as $line) {
            self::assertNull($line->amount);
            self::assertNull($line->unit);
            self::assertSame('½', (new Measures)->display((array) $line));
        }
        self::assertSame(0, app(MeasureRepair::class)->run(true));
        $html = view('recipes.show', [
            'recipe' => app(Catalog::class)->find($id),
            'names' => [], 'favorite' => false, 'sources' => [], 'errors' => new ViewErrorBag,
        ])->render();
        self::assertStringNotContainsString('0.5 Stück', $html);
        self::assertSame(2, substr_count($html, '<strong>½</strong>'));
    }

    public function test_irish_curdling_cow_displays_practical_volumes_and_a_metric_range(): void
    {
        config(['privatebar.mode' => 'cloud']);
        $dto = $this->dto();
        $dto['name'] = 'Irish Curdling Cow';
        $dto['ingredients'] = [
            ['name' => 'Irish Cream', 'measure' => '3/4 oz'],
            ['name' => 'Bourbon', 'measure' => '3/4 oz'],
            ['name' => 'Vodka', 'measure' => '3/4 oz'],
            ['name' => 'Orange juice', 'measure' => '2 -3 oz'],
        ];
        $id = app(Importer::class)->ingest($dto);
        self::assertSame(2.218, (float) DB::table('recipe_ingredients')->where('recipe_id', $id)->orderBy('position')->value('amount'));
        $html = view('recipes.show', [
            'recipe' => app(Catalog::class)->find($id),
            'names' => [], 'favorite' => false, 'sources' => [], 'errors' => new ViewErrorBag,
        ])->render();
        self::assertSame(3, substr_count($html, '<strong>2.2 cl</strong>'));
        self::assertStringContainsString('<strong>5.9–8.9 cl</strong>', $html);
        self::assertStringNotContainsString('2.218 cl', $html);
    }

    public function test_measure_repair_rejects_pi_and_maintenance(): void
    {
        config(['privatebar.mode' => 'pi']);
        try {
            app(MeasureRepair::class)->run(true);
            self::fail('Pi must reject import repairs.');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('Cyon', $e->getMessage());
        }
        config(['privatebar.mode' => 'cloud']);
        app(Settings::class)->set('maintenance', true);
        $this->expectException(RuntimeException::class);
        app(MeasureRepair::class)->run(true);
    }

    public function test_failed_import_does_not_damage_existing_recipe(): void
    {
        config(['privatebar.mode' => 'cloud']);
        $dto = $this->dto();
        $id = app(Importer::class)->ingest($dto);
        $before = DB::table('recipes')->where('id', $id)->first();
        $dto['name'] = '';
        try {
            app(Importer::class)->ingest($dto);
            self::fail();
        } catch (ValidationException) {
        }
        self::assertEquals($before, DB::table('recipes')->where('id', $id)->first());
    }

    public function test_translation_is_cached_and_protects_a_concurrent_manual_edit(): void
    {
        config(['privatebar.mode' => 'cloud']);
        $id = app(Importer::class)->ingest($this->dto());
        $this->mock(TranslationProvider::class)->shouldReceive('translate')->once()->andReturnUsing(function () use ($id) {
            app(RecipeWriter::class)->translateManually($id, 'Manuell gewinnt.');

            return 'Automatisch übersetzt.';
        });
        app(Translator::class)->one($id);
        self::assertSame('Manuell gewinnt.', DB::table('recipes')->where('id', $id)->value('instructions'));
        self::assertSame(1, DB::table('translation_cache')->count());
    }

    public function test_off_cache_and_identifying_user_agent(): void
    {
        config(['privatebar.providers_enabled' => true]);
        Http::fake(['world.openfoodfacts.org/*' => Http::response(['product' => ['product_name' => 'Gin', 'brands' => 'Test', 'nutriments' => ['alcohol_100g' => 40]]])]);
        $p = app(OpenFoodFacts::class);
        self::assertSame('Gin', $p->lookup('7612345678901')['name']);
        $p->lookup('7612345678901');
        Http::assertSentCount(1);
        Http::assertSent(fn ($r) => str_contains($r->header('User-Agent')[0], 'PrivateBar'));
    }

    public function test_photo_cache_skips_corrupt_files_enforces_lru_and_survives_smb_failure(): void
    {
        config(['privatebar.mode' => 'pi']);
        $root = sys_get_temp_dir().'/privatebar-photo-test-'.bin2hex(random_bytes(4));
        mkdir($root);
        mkdir($root.'/nested');
        try {
            $image = imagecreatetruecolor(120, 80);
            imagefill($image, 0, 0, imagecolorallocate($image, 200, 100, 20));
            imagejpeg($image, $root.'/nested/one.jpg');
            imagedestroy($image);
            file_put_contents($root.'/bad.jpg', 'not-an-image');
            file_put_contents($root.'/bad.heic', 'unsupported');
            config(['privatebar.photo_mount' => $root]);
            app(PhotoCache::class)->refresh();
            self::assertSame(1, DB::table('photo_cache')->count());
            $row = DB::table('photo_cache')->first();
            self::assertSame(0, DB::table('sync_events')->count());
            config(['privatebar.photo_mount' => $root.'/missing']);
            try {
                app(PhotoCache::class)->refresh();
                self::fail();
            } catch (RuntimeException) {
            }
            self::assertNotNull(app(PhotoCache::class)->next(null));
            app(Settings::class)->set('photo_cache_mb', 0);
            app(PhotoCache::class)->evict();
            self::assertSame(0, DB::table('photo_cache')->count());
            Storage::disk('local')->assertMissing($row->cache_path);
            self::assertFileExists($root.'/nested/one.jpg');
        } finally {
            @unlink($root.'/nested/one.jpg');
            @unlink($root.'/bad.jpg');
            @unlink($root.'/bad.heic');
            @rmdir($root.'/nested');
            @rmdir($root);
        }
    }

    public function test_image_rejects_heic_and_does_not_retain_original(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pb-image-');
        file_put_contents($path, 'not a supported photo');
        try {
            app(ImageProcessor::class)->compress($path);
            self::fail();
        } catch (RuntimeException $e) {
            self::assertStringContainsString('HEIC', $e->getMessage());
        } finally {
            unlink($path);
        }
    }
}
