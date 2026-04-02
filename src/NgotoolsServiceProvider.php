<?php

namespace NgoTools\LaravelStarter;

use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentColor;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use NgoTools\LaravelStarter\Commands\DevCommand;
use NgoTools\LaravelStarter\Commands\InstallCommand;
use NgoTools\LaravelStarter\Http\Middleware\AllowIframeEmbedding;

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
        }

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'ngotools');

        // Ensure CORS paths cover all routes, not just api/* (needed for iFrame embedding).
        if (config('cors.paths') === ['api/*', 'sanctum/csrf-cookie']) {
            config(['cors.paths' => ['*']]);
        }

        FilamentColor::register([
            'primary' => Color::hex('#134865'),
            'danger' => Color::Red,
        ]);

        // Register HandleCors globally so all responses (including Livewire XHR)
        // get CORS headers — required for iFrame embedding via cross-origin tunnels.
        $kernel = $this->app->make(\Illuminate\Contracts\Http\Kernel::class);
        $kernel->prependMiddleware(HandleCors::class);

        // Trust all proxies (cloudflared tunnel) so Laravel uses X-Forwarded-*
        // headers for URL generation (correct asset URLs in iFrame).
        \Illuminate\Http\Middleware\TrustProxies::at('*');

        Route::middleware([AllowIframeEmbedding::class])
            ->group(function () {
                $this->loadRoutesFrom(__DIR__ . '/../routes/ngotools.php');

                if (file_exists($uiRoutes = base_path('routes/ngotools-ui.php'))) {
                    $this->loadRoutesFrom($uiRoutes);
                }
            });

        if (file_exists($webhookRoutes = base_path('routes/ngotools-webhooks.php'))) {
            $this->loadRoutesFrom($webhookRoutes);
        }
    }
}
