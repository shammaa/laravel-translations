<?php

declare(strict_types=1);

namespace Shammaa\LaravelTranslations;

use Illuminate\Support\ServiceProvider;
use Shammaa\LaravelTranslations\Services\TranslationManager;

class LaravelTranslationsServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/translations.php', 'translations');

        $this->app->singleton(TranslationManager::class, function ($app) {
            return new TranslationManager();
        });

        $this->app->alias(TranslationManager::class, 'translations');
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish config
        $this->publishes([
            __DIR__ . '/../config/translations.php' => config_path('translations.php'),
        ], 'translations-config');

        // Publish migrations
        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'translations-migrations');

        // Register console commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\BenchmarkCommand::class,
            ]);
        }
    }
}

