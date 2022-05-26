<?php

namespace HulkApps\AppManager;

use HulkApps\AppManager\app\Http\Middleware\VerifyAPIRequest;
use HulkApps\AppManager\Console\SyncWithAppManager;
use Illuminate\Console\Scheduling\Schedule;
use HulkApps\AppManager\Console\MigratePlans;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

class AppManagerServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        /*
         * Optional methods to load your package assets
         */
        // $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'app-manager');
        // $this->loadViewsFrom(__DIR__.'/../resources/views', 'app-manager');
        // $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__ . '/../routes/app-manager.php');

        $router = $this->app->make(Router::class);

        $router->aliasMiddleware('app-manager-api', VerifyAPIRequest::class);


        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/config.php' => config_path('app-manager.php'),
            ], 'config');

            $this->publishes([
                __DIR__.'/../config/plan_features.php' => config_path('plan_features.php'),
            ], 'config');

            $this->commands([
                MigratePlans::class,
                SyncWithAppManager::class
            ]);

            $this->app->booted(function () {
                $schedule = $this->app->make(Schedule::class);
                $schedule->command('sync:app-manager')->everyFiveMinutes();
            });
        }
    }

    /**
     * Register the application services.
     */
    public function register()
    {
        // Automatically apply the package configuration
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'app-manager');

        // Register the main class to use with the facade
        $this->app->singleton('app-manager', function () {
            return new AppManager(config('app-manager.api'), config('app-manager.secret'));
        });
    }
}
