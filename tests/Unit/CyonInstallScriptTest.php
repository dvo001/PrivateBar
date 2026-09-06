<?php

namespace Tests\Unit;

use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class CyonInstallScriptTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/privatebar-install-'.bin2hex(random_bytes(8));
        mkdir($this->root.'/storage/app/private/cyon-install', 0700, true);
        mkdir($this->root.'/tools');
        copy(dirname(__DIR__, 2).'/tools/cyon-install.php', $this->root.'/tools/cyon-install.php');
        symlink(dirname(__DIR__, 2).'/vendor', $this->root.'/vendor');
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->root);
    }

    public function test_completed_installation_does_not_require_credentials_or_restart(): void
    {
        file_put_contents($this->root.'/storage/app/private/cyon-install/complete', 'done');
        $process = new Process([PHP_BINARY, $this->root.'/tools/cyon-install.php']);
        $process->run();
        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        self::assertStringContainsString('bereits abgeschlossen', $process->getOutput());
        self::assertFileDoesNotExist($this->root.'/.env');
    }

    public function test_parallel_invocation_does_not_start_installation(): void
    {
        $lock = fopen($this->root.'/storage/app/private/cyon-install/lock', 'c');
        flock($lock, LOCK_EX);
        try {
            $process = new Process([PHP_BINARY, $this->root.'/tools/cyon-install.php']);
            $process->run();
            self::assertSame(0, $process->getExitCode());
            self::assertStringContainsString('läuft bereits', $process->getOutput());
        } finally {
            fclose($lock);
        }
    }

    public function test_configuration_error_does_not_disclose_secret(): void
    {
        file_put_contents($this->root.'/.env', 'DB_PASSWORD=super-secret-value');
        file_put_contents($this->root.'/storage/app/private/cyon-install/input.json', '{"password":"super-secret-value", INVALID JSON');
        $process = new Process([PHP_BINARY, $this->root.'/tools/cyon-install.php']);
        $process->run();
        self::assertSame(1, $process->getExitCode());
        self::assertStringContainsString('Konfiguration', $process->getErrorOutput());
        self::assertStringNotContainsString('super-secret-value', $process->getOutput().$process->getErrorOutput());
    }
}
