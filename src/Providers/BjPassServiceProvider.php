<?php

namespace BjPass\Providers;

use BjPass\BjPass;
use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Http\Kernel;
use BjPass\Http\Middleware\BjPassAuthMiddleware;

class BjPassServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/bjpass.php', 'bjpass');

        $this->app->singleton('bjpass', function ($app) {
            $config = config('bjpass', []);
            
            // Merge with environment variables
            $config = array_merge($config, [
                'client_id' => config('bjpass.client_id'),
                'client_secret' => config('bjpass.client_secret'),
                'redirect_uri' => config('bjpass.redirect_uri'),
                'base_url' => config('bjpass.base_url'),
                'auth_server' => config('bjpass.auth_server'),
                'scope' => config('bjpass.scope'),
                'issuer' => config('bjpass.issuer'),
            ]);

            return new BjPass($config);
        });

        $this->app->alias('bjpass', BjPass::class);
    }

    public function boot(): void
    {
        // Publish configuration
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../../config/bjpass.php' => config_path('bjpass.php'),
            ], 'bjpass-config');

            $this->publishes([
                __DIR__ . '/../../routes/bjpass.php' => base_path('routes/bjpass.php'),
            ], 'bjpass-routes');

            $this->publishes([
                __DIR__ . '/../../database/migrations/' => database_path('migrations'),
            ], 'bjpass-migrations');
        }

        // Load routes
        $this->loadRoutesFrom(__DIR__ . '/../../routes/bjpass.php');

        // Register middleware
        $this->registerMiddleware();

        // Load views if any
        if (is_dir(__DIR__ . '/../../resources/views')) {
            $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'bjpass');
        }
    }

    protected function registerMiddleware(): void
    {
        $router = $this->app['router'];
        
        $router->aliasMiddleware('bjpass.auth', BjPassAuthMiddleware::class);
        
        // Register global middleware if needed
        if (config('bjpass.global_middleware', false)) {
            $kernel = $this->app->make(Kernel::class);
            $kernel->pushMiddleware(BjPassAuthMiddleware::class);
        }
    }

    public function provides(): array
    {
        return ['bjpass'];
    }
}
