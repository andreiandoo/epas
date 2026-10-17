<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Limba răspunsurilor /tenant-client: site-urile de tenant trimit ?locale=ro|en
 * (vezi resources/tenant-sites/app/i18n.php). Fără parametru nu se schimbă nimic,
 * așa că apelurile existente se comportă ca înainte.
 */
class TenantClientLocale
{
    public function handle(Request $request, Closure $next)
    {
        $locale = strtolower((string) $request->query('locale', ''));
        if (in_array($locale, ['ro', 'en'], true)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
