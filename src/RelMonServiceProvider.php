<?php

namespace FromDevelopersForDevelopers\RelMonLaravel;

use FromDevelopersForDevelopers\RelMonLaravel\Contracts\RelMonServiceContract;
use FromDevelopersForDevelopers\RelMonLaravel\Services\RelMonService;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

final class RelMonServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/relmon.php', 'relmon');

        $this->app->singleton(RelMonService::class, function (Application $app): RelMonService {
            return new RelMonService($app, $app['config']);
        });

        $this->app->alias(RelMonService::class, RelMonServiceContract::class);
        $this->app->alias(RelMonService::class, 'relmon');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/relmon.php' => $this->app->configPath('relmon.php'),
        ], 'relmon-config');
    }
}
