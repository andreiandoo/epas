<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

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

        // Register observers
        \App\Models\Invoice::observe(\App\Observers\InvoiceObserver::class);

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
