<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\Seating\Pricing\Contracts\DynamicPricingEngine;
use App\Services\Seating\Pricing\DefaultPricingEngine;

/**
 * SeatingServiceProvider
 *
 * Registers bindings and services for the Seating module
 */
class SeatingServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind DynamicPricingEngine interface to default implementation
        // Can be overridden in config/seating.php for custom engines
        $this->app->bind(
            DynamicPricingEngine::class,
            function ($app) {
                $engineClass = config('seating.dynamic_pricing.engine', DefaultPricingEngine::class);

                return $app->make($engineClass);
            }
        );

        // Register singleton services for performance
        $this->app->singleton(\App\Services\Seating\GeometryStorage::class);
        $this->app->singleton(\App\Services\Seating\SeatHoldService::class);
        $this->app->singleton(\App\Repositories\SeatInventoryRepository::class);
        $this->app->singleton(\App\Services\FeatureFlag::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register console commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                \App\Console\Commands\ReleaseExpiredHolds::class,
            ]);
        }

        // Publish config file for users who want to override defaults
        $this->publishes([
            __DIR__.'/../../config/seating.php' => config_path('seating.php'),
        ], 'seating-config');
    }
}
