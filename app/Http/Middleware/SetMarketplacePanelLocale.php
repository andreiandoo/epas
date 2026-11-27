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
    private const SUPPORTED = ['en', 'ro', 'de', 'fr', 'es'];

    public function handle(Request $request, Closure $next)
    {
        $admin = auth('marketplace_admin')->user();
        $asked = $request->query('locale');

        if (is_string($asked) && in_array($asked, self::SUPPORTED, true)) {
            $request->session()->put('marketplace_locale', $asked);
            if ($admin instanceof MarketplaceAdmin && $admin->locale !== $asked) {
                $admin->forceFill(['locale' => $asked])->saveQuietly();
            }
        }

        $locale = $request->session()->get('marketplace_locale')
            ?: ($admin instanceof MarketplaceAdmin ? $admin->locale : null);

        if ($locale && in_array($locale, self::SUPPORTED, true)) {
            App::setLocale($locale);
        }

        return $next($request);
    }
}
