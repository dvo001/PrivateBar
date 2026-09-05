<?php

namespace Tests\Feature;

use App\Domain\Photos\ImageProcessor;
use App\Domain\Photos\PhotoCache;
use App\Domain\Recipes\Importer;
use App\Domain\Recipes\RecipeWriter;
use App\Domain\Recipes\Translator;
use App\Domain\Settings\Settings;
use App\Infrastructure\Providers\OpenFoodFacts;
use App\Infrastructure\Providers\TranslationProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
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
            } catch (\RuntimeException) {
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
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('HEIC', $e->getMessage());
        } finally {
            unlink($path);
        }
    }
}
