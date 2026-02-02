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

        // 2) try authenticated user's saved locale preference
        if (! $locale) {
            $locale = $this->getUserLocale();
        }

        // 3) try session stored locale
        if (! $locale) {
            $locale = session('locale');
        }

        // 4) try tenant default (if present on domain)
        if (! $locale && function_exists('tenant')) {
            $t = tenant();
            $locale = $t?->settings['default_locale'] ?? null;
        }

        // 5) try browser
        if (! $locale) {
            $locale = $request->getPreferredLanguage($available);
        }

        $locale = in_array($locale, $available, true) ? $locale : $fallback;

        app()->setLocale($locale);
        View::share('currentLocale', $locale);
        View::share('availableLocales', $available);

        return $next($request);
    }

    /**
     * Get the authenticated user's locale preference from their account.
     */
    protected function getUserLocale(): ?string
    {
        // Check Filament's auth first (works in panel context)
        if (function_exists('filament') && filament()->auth()->check()) {
            $user = filament()->auth()->user();
            if ($user && isset($user->locale) && $user->locale) {
                return $user->locale;
            }
        }

        // Check marketplace_admin guard
        if (auth('marketplace_admin')->check()) {
            $user = auth('marketplace_admin')->user();
            if ($user && isset($user->locale) && $user->locale) {
                return $user->locale;
            }
        }

        // Check web guard
        if (auth('web')->check()) {
            $user = auth('web')->user();
            if ($user && isset($user->locale) && $user->locale) {
                return $user->locale;
            }
        }

        return null;
    }
}
