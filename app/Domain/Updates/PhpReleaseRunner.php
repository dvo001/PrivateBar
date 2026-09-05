<?php

namespace App\Domain\Updates;

use Symfony\Component\Process\Process;

final class PhpReleaseRunner implements ReleaseRunner
{
    public function run(string $directory, array $arguments): void
    {
        $process = new Process([PHP_BINARY, $directory.'/artisan', ...$arguments], $directory);
        $process->setTimeout(120);
        $process->mustRun();
    }
}
