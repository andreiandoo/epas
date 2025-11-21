<?php

namespace App\Providers;

use App\Http\Middleware\TenantClientCors;
use App\Http\Middleware\VerifyTenantClientRequest;
use Illuminate\Support\ServiceProvider;
use Illuminate\Routing\Router;

class TenantClientServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $router = $this->app->make(Router::class);

        // Register middleware aliases
        $router->aliasMiddleware('tenant.client', VerifyTenantClientRequest::class);
        $router->aliasMiddleware('tenant.cors', TenantClientCors::class);

        // Add CORS middleware to tenant-client routes
        $router->pushMiddlewareToGroup('api', TenantClientCors::class);
    }
}
