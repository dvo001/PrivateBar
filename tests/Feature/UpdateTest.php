<?php

namespace Tests\Feature;

use App\Domain\Settings\Settings;
use App\Domain\Updates\Installer;
use App\Domain\Updates\ReleaseRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class UpdateTest extends TestCase
{
    use RefreshDatabase;

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        config(['privatebar.version' => '0.1.0']);
        $this->root = sys_get_temp_dir().'/privatebar-release-test-'.bin2hex(random_bytes(5));
        mkdir($this->root.'/releases/old', 0755, true);
        mkdir($this->root.'/shared/storage', 0755, true);
        file_put_contents($this->root.'/shared/.env', 'APP_NAME=PrivateBar');
        symlink($this->root.'/releases/old', $this->root.'/current');
        $tar = new \PharData($this->root.'/release.tar');
        $tar->addFromString('artisan', '<?php');
        $tar->addFromString('vendor/autoload.php', '<?php');
        $tar->addFromString('public/assets/manifest.json', '{}');
        unset($tar);
        $bytes = file_get_contents($this->root.'/release.tar');
        $key = openssl_pkey_new(['private_key_bits' => 2048]);
        $public = openssl_pkey_get_details($key)['key'];
        $payload = json_encode(['version' => '1.0.0', 'url' => 'https://releases.example.test/release.tar', 'sha256' => hash('sha256', $bytes), 'bytes' => strlen($bytes), 'php' => '8.3', 'api_min' => 1, 'api_max' => 1, 'backward_compatible_migrations' => true, 'checks_passed' => true]);
        openssl_sign($payload, $signature, $key, OPENSSL_ALGO_SHA256);
        config(['privatebar.mode' => 'pi', 'privatebar.release_root' => $this->root, 'privatebar.release_manifest' => 'https://releases.example.test/manifest.json', 'privatebar.release_public_key' => $public]);
        Http::fake(['releases.example.test/manifest.json' => Http::response(['payload' => base64_encode($payload), 'signature' => base64_encode($signature)]), 'releases.example.test/release.tar' => Http::response($bytes)]);
    }

    protected function tearDown(): void
    {
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $file) {
            if ($file->isDir() && ! $file->isLink()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }
        rmdir($this->root);
        parent::tearDown();
    }

    public function test_failed_migration_keeps_previous_program_and_maintenance(): void
    {
        $this->app->instance(ReleaseRunner::class, new class implements ReleaseRunner
        {
            public function run(string $directory, array $arguments): void
            {
                throw new \RuntimeException('Simulierter Migrationsfehler');
            }
        });
        try {
            app(Installer::class)->install();
            self::fail();
        } catch (\RuntimeException) {
        }
        self::assertSame($this->root.'/releases/old', realpath($this->root.'/current'));
        self::assertTrue(app(Settings::class)->maintenance());
    }

    public function test_verified_release_switches_only_after_three_successful_checks(): void
    {
        $runner = new class implements ReleaseRunner
        {
            public array $calls = [];

            public function run(string $directory, array $arguments): void
            {
                $this->calls[] = $arguments;
            }
        };
        $this->app->instance(ReleaseRunner::class, $runner);
        app(Installer::class)->install();
        clearstatcache(true);
        self::assertSame($this->root.'/releases/1.0.0', realpath($this->root.'/current'));
        self::assertCount(3, $runner->calls);
        self::assertFalse(app(Settings::class)->maintenance());
        self::assertSame($this->root.'/shared/.env', readlink($this->root.'/releases/1.0.0/.env'));
    }
}
