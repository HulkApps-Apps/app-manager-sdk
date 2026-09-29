<?php

namespace HulkApps\AppManager;

use HulkApps\AppManager\app\Http\Middleware\HasPlan;
use HulkApps\AppManager\app\Http\Middleware\VerifyAPIRequest;
use HulkApps\AppManager\Console\InitDB;
use HulkApps\AppManager\Contracts\ShopifyTokenResolver;
use HulkApps\AppManager\Shopify\ColumnTokenResolver;
use HulkApps\AppManager\Console\SyncWithAppManager;
use Illuminate\Console\Scheduling\Schedule;
use HulkApps\AppManager\Console\MigratePlans;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Illuminate\Cookie\Middleware\EncryptCookies;

class AppManagerServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/app-manager.php');

        $router = $this->app->make(Router::class);

        $router->aliasMiddleware('app-manager-api', VerifyAPIRequest::class);
        $router->aliasMiddleware('app-manager:has-plan', HasPlan::class);

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/config.php' => config_path('app-manager.php'),
            ], 'config');

            $this->publishes([
                __DIR__.'/../config/plan_features.php' => config_path('plan_features.php'),
            ], 'config');

            $this->commands([
                InitDB::class,
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

        // Unset, the SDK reads the token column exactly as before. Bound per
        // use, never a singleton, so a long-lived worker holds nothing stale.
        $this->app->bind(ShopifyTokenResolver::class, function ($app) {
            $resolver = config('app-manager.shopify_token_resolver') ?: ColumnTokenResolver::class;

            $instance = $app->make($resolver);

            if (! $instance instanceof ShopifyTokenResolver) {
                throw new \InvalidArgumentException(sprintf(
                    'app-manager.shopify_token_resolver must implement %s; %s does not.',
                    ShopifyTokenResolver::class,
                    $resolver
                ));
            }

            return $instance;
        });
    }
}
