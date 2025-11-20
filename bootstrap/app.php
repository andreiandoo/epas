<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // Configure rate limiters for seating API
            RateLimiter::for('seating_query', fn () => Limit::perMinute(config('seating.rate_limits.query_per_minute')));
            RateLimiter::for('seating_hold', fn () => Limit::perMinute(config('seating.rate_limits.hold_per_minute')));
            RateLimiter::for('seating_release', fn () => Limit::perMinute(config('seating.rate_limits.release_per_minute')));
            RateLimiter::for('seating_confirm', fn () => Limit::perMinute(config('seating.rate_limits.confirm_per_minute')));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Register middleware aliases
        $middleware->alias([
            'seating.session' => \App\Http\Middleware\SeatingSessionMiddleware::class,
            'set.locale' => \App\Http\Middleware\SetLocale::class,
            'tenant.auth' => \App\Http\Middleware\TenantAuthentication::class,
            'api.tenant' => \App\Http\Middleware\AuthenticateTenantApi::class,
            'admin.auth' => \App\Http\Middleware\AuthenticateAdmin::class,
            'webhook.verify' => \App\Http\Middleware\VerifyWebhookSignature::class,
            'api.key' => \App\Http\Middleware\VerifyApiKey::class,
        ]);

        // Add global middleware for API routes
        $middleware->api(append: [
            \App\Http\Middleware\AddRateLimitHeaders::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
