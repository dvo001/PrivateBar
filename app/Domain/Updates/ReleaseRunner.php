<?php

namespace App\Domain\Updates;

interface ReleaseRunner
{
    public function run(string $directory, array $arguments): void;
}
