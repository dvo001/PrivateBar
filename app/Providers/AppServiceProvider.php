<?php

namespace App\Providers;

use App\Domain\Updates\PhpReleaseRunner;
use App\Domain\Updates\ReleaseRunner;
use App\Infrastructure\Providers\AzureTranslator;
use App\Infrastructure\Providers\OpenFoodFacts;
use App\Infrastructure\Providers\ProductProvider;
use App\Infrastructure\Providers\TranslationProvider;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ReleaseRunner::class, PhpReleaseRunner::class);
        $this->app->bind(ProductProvider::class, OpenFoodFacts::class);
        $this->app->bind(TranslationProvider::class, AzureTranslator::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
