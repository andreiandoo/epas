<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

/**
 * Sets the application locale in the tenant panel from the tenant's configured
 * locale/language, so the UI + translatable field rendering match the tenant setting.
 */
class SetTenantPanelLocale
{
    private const SUPPORTED = ['en', 'ro', 'de', 'fr', 'es'];

    public function handle(Request $request, Closure $next)
    {
        $tenant = $request->user()?->tenant ?? null;
        $locale = $tenant?->locale ?? $tenant?->language ?? null;

        if ($locale && in_array($locale, self::SUPPORTED, true)) {
            App::setLocale($locale);
        }

        return $next($request);
    }
}
