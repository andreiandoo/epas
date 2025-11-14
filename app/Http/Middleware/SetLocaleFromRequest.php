<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\View;

class SetLocaleFromRequest
{
    public function handle($request, Closure $next)
    {
        $available = config('locales.available');
        $fallback  = config('locales.fallback');

        // 1) try url prefix /{locale?}
        $locale = $request->route('locale');

        // 2) try tenant default (if present on domain)
        if (! $locale && function_exists('tenant')) {
            $t = tenant();
            $locale = $t?->settings['default_locale'] ?? null;
        }

        // 3) try browser
        if (! $locale) {
            $locale = $request->getPreferredLanguage($available);
        }

        $locale = in_array($locale, $available, true) ? $locale : $fallback;

        app()->setLocale($locale);
        View::share('currentLocale', $locale);
        View::share('availableLocales', $available);

        return $next($request);
    }
}
