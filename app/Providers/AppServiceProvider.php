<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;

class AppServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     */
    protected $policies = [
        \App\Models\User::class => \App\Policies\UserPolicy::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register Consent Service for tracking
        $this->app->singleton(
            \App\Services\Tracking\ConsentServiceInterface::class,
            \App\Services\Tracking\SessionConsentService::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $helpers = app_path('Support/helpers.php');
        if (file_exists($helpers)) {
            require_once $helpers;
        }

        // Define API rate limiter
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Register observers
        \App\Models\Invoice::observe(\App\Observers\InvoiceObserver::class);
        \App\Models\Artist::observe(\App\Observers\ArtistObserver::class);
        \App\Models\Order::observe(\App\Observers\OrderObserver::class);
        \App\Models\Event::observe(\App\Observers\EventObserver::class);
        \App\Models\Event::observe(\App\Observers\MarketplaceEventObserver::class);
        \App\Models\MarketplaceCustomer::observe(\App\Observers\MarketplaceCustomerObserver::class);
        \App\Models\MarketplaceOrganizer::observe(\App\Observers\MarketplaceOrganizerObserver::class);

        // Register microservices event listeners
        \Illuminate\Support\Facades\Event::listen(
            \App\Events\OrderConfirmed::class,
            \App\Listeners\SendOrderConfirmationListener::class,
        );

        \Illuminate\Support\Facades\Event::listen(
            \App\Events\PaymentCaptured::class,
            [
                \App\Listeners\SubmitEFacturaListener::class,
                \App\Listeners\IssueInvoiceListener::class,
            ]
        );
    }
}
