<?php

namespace Tests\Feature;

use App\Domain\Recipes\Importer;
use App\Domain\Settings\BackgroundTasks;
use App\Domain\Settings\Settings;
use App\Infrastructure\Providers\ApiNinjas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class ApiNinjasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        config(['privatebar.mode' => 'cloud', 'privatebar.api_ninjas_key' => 'test-secret', 'privatebar.api_ninjas_queries' => 'bloody mary,martini']);
        app(Settings::class)->set('recipe_import_enabled', true);
        app(Settings::class)->set('translation_enabled', false);
        Http::preventStrayRequests();
    }

    private function row(): array
    {
        return ['name' => 'Bloody Mary', 'ingredients' => ['4.5 cl (3 parts) vodka', '9 cl (6 parts) Tomato juice', '1.5 cl (1 part) Lemon juice', '2 to 3 dashes of Worcestershire Sauce', 'Tabasco sauce'], 'instructions' => 'Stir gently.'];
    }

    public function test_batch_splits_measures_and_import_is_repeatable(): void
    {
        Http::fake(['api.api-ninjas.com/*' => Http::response([$this->row()])]);
        $batch = app(ApiNinjas::class)->batch('');
        self::assertSame('1', $batch['cursor']);
        self::assertFalse($batch['complete']);
        self::assertSame(['name' => 'vodka', 'measure' => '4.5 cl', 'role' => 'required'], $batch['recipes'][0]['ingredients'][0]);
        self::assertSame('2 to 3 dashes', $batch['recipes'][0]['ingredients'][3]['measure']);
        self::assertSame('Worcestershire Sauce', $batch['recipes'][0]['ingredients'][3]['name']);
        self::assertSame('', $batch['recipes'][0]['ingredients'][4]['measure']);
        Http::assertSent(fn ($request) => $request->hasHeader('X-Api-Key', 'test-secret') && $request['name'] === 'bloody mary');
        $id = app(Importer::class)->ingest($batch['recipes'][0]);
        $count = DB::table('sync_events')->count();
        self::assertSame($id, app(Importer::class)->ingest($batch['recipes'][0]));
        self::assertSame($count, DB::table('sync_events')->count());
        self::assertSame(4.5, (float) DB::table('recipe_ingredients')->where('recipe_id', $id)->where('position', 0)->value('amount'));
        self::assertTrue(app(ApiNinjas::class)->batch('1')['complete']);
    }

    public function test_failure_retains_cursor_and_hides_response_secrets(): void
    {
        $settings = app(Settings::class);
        $settings->set('import_pending', true);
        $settings->set('import_provider', 'api-ninjas');
        $settings->set('import_cursor', '1');
        Http::fake(['api.api-ninjas.com/*' => Http::response(['error' => 'test-secret'], 429)]);
        app(BackgroundTasks::class)->tick();
        self::assertSame('1', $settings->get('import_cursor'));
        self::assertTrue($settings->get('import_pending'));
        self::assertStringNotContainsString('test-secret', $settings->get('import_error'));
    }

    public function test_optional_source_completes_and_resets_cycle(): void
    {
        $settings = app(Settings::class);
        $settings->set('import_pending', true);
        $settings->set('import_provider', 'api-ninjas');
        $settings->set('import_cursor', '1');
        Http::fake(['api.api-ninjas.com/*' => Http::response([])]);
        app(BackgroundTasks::class)->tick();
        self::assertFalse($settings->get('import_pending'));
        self::assertSame('cocktaildb', $settings->get('import_provider'));
        self::assertNotNull($settings->get('import_complete'));
    }

    public function test_opendrinks_hands_over_to_optional_source(): void
    {
        $settings = app(Settings::class);
        $settings->set('import_pending', true);
        $settings->set('import_provider', 'opendrinks');
        Http::fake(['api.github.com/*' => Http::response([])]);
        app(BackgroundTasks::class)->tick();
        self::assertSame('api-ninjas', $settings->get('import_provider'));
        self::assertTrue($settings->get('import_pending'));
        Http::assertSentCount(1);
    }

    public function test_invalid_response_does_not_advance_cursor(): void
    {
        $settings = app(Settings::class);
        $settings->set('import_pending', true);
        $settings->set('import_provider', 'api-ninjas');
        $settings->set('import_cursor', '1');
        $count = DB::table('recipes')->count();
        Http::fake(['api.api-ninjas.com/*' => Http::response([['name' => 'Broken']])]);
        app(BackgroundTasks::class)->tick();
        self::assertSame('1', $settings->get('import_cursor'));
        self::assertSame($count, DB::table('recipes')->count());
        self::assertNotNull($settings->get('import_error'));
    }

    public function test_missing_key_skips_optional_source(): void
    {
        config(['privatebar.api_ninjas_key' => null]);
        $settings = app(Settings::class);
        $settings->set('import_pending', true);
        $settings->set('import_provider', 'api-ninjas');
        app(BackgroundTasks::class)->tick();
        self::assertFalse($settings->get('import_pending'));
        Http::assertNothingSent();
    }

    public function test_monthly_budget_counts_failures_and_resets_next_month(): void
    {
        config(['privatebar.api_ninjas_monthly_limit' => 1]);
        Http::fake(['api.api-ninjas.com/*' => Http::response([])]);
        app(ApiNinjas::class)->batch('');
        try {
            app(ApiNinjas::class)->batch('');
            self::fail('Kontingent muss weitere Anfragen verhindern.');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('Kontingent', $e->getMessage());
        }
        Http::assertSentCount(1);
        $this->travel(1)->months();
        app(ApiNinjas::class)->batch('');
        Http::assertSentCount(2);
    }

    public function test_pi_never_calls_provider(): void
    {
        config(['privatebar.mode' => 'pi']);
        $this->expectException(\RuntimeException::class);
        app(ApiNinjas::class)->batch('');
    }
}
