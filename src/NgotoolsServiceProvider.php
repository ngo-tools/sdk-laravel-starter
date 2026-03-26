<?php

namespace NgoTools\LaravelStarter;

use Illuminate\Support\ServiceProvider;
use NgoTools\LaravelStarter\Commands\DevCommand;
use NgoTools\LaravelStarter\Commands\InstallCommand;

class NgotoolsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/ngotools.php', 'ngotools');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                DevCommand::class,
            ]);

            $this->publishes([
                __DIR__ . '/../config/ngotools.php' => config_path('ngotools.php'),
            ], 'ngotools-config');

            $this->publishes([
                __DIR__ . '/../routes/ngotools.php' => base_path('routes/ngotools.php'),
            ], 'ngotools-routes');

            $this->publishes([
                __DIR__ . '/../resources/views' => resource_path('views/vendor/ngotools'),
            ], 'ngotools-views');
        }

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'ngotools');
        $this->loadRoutesFrom(__DIR__ . '/../routes/ngotools.php');

        if (file_exists($uiRoutes = base_path('routes/ngotools-ui.php'))) {
            $this->loadRoutesFrom($uiRoutes);
        }

        if (file_exists($webhookRoutes = base_path('routes/ngotools-webhooks.php'))) {
            $this->loadRoutesFrom($webhookRoutes);
        }
    }
}
