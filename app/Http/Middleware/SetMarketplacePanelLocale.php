<?php

namespace App\Http\Middleware;

use App\Models\MarketplaceAdmin;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

/**
 * Limba panoului marketplace. Selectorul din bara de sus trimite ?locale=xx: o salvăm pe
 * operator (marketplace_admins.locale) și în sesiune, apoi o aplicăm la fiecare cerere,
 * inclusiv la cererile Livewire (middleware persistent).
 */
class SetMarketplacePanelLocale
{
    public function handle(Request $request, Closure $next)
    {
        $supported = (array) config('marketplace_locales.enabled', ['ro', 'en']);
        $admin = auth('marketplace_admin')->user();
        $asked = $request->query('locale');

        if (is_string($asked) && in_array($asked, $supported, true)) {
            $request->session()->put('marketplace_locale', $asked);
            if ($admin instanceof MarketplaceAdmin && $admin->locale !== $asked) {
                $admin->forceFill(['locale' => $asked])->saveQuietly();
            }
        }

        $locale = $request->session()->get('marketplace_locale')
            ?: ($admin instanceof MarketplaceAdmin ? $admin->locale : null);

        if ($locale && in_array($locale, $supported, true)) {
            App::setLocale($locale);
        }

        return $next($request);
    }
}
