<?php

declare(strict_types=1);

namespace Shammaa\LaravelTranslations\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Shammaa\LaravelTranslations\LaravelTranslationsServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Load migrations
        $migrationFiles = glob(__DIR__ . '/../database/migrations/*.php');
        foreach ($migrationFiles as $file) {
            require_once $file;
        }
        
        // Run migrations
        $this->artisan('migrate', ['--database' => 'testbench'])->run();
    }

    /**
     * Get package providers.
     *
     * @param \Illuminate\Foundation\Application $app
     * @return array
     */
    protected function getPackageProviders($app): array
    {
        return [
            LaravelTranslationsServiceProvider::class,
        ];
    }

    /**
     * Define environment setup.
     *
     * @param \Illuminate\Foundation\Application $app
     * @return void
     */
    protected function defineEnvironment($app): void
    {
        // Setup default database to use sqlite :memory:
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Setup translations config
        $app['config']->set('translations.default_locale', 'ar');
        $app['config']->set('translations.supported_locales', ['ar', 'en', 'fr']);
        $app['config']->set('translations.cache.enabled', false); // Disable cache for tests
    }
}

