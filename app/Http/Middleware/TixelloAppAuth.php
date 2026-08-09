<?php

namespace App\Http\Middleware;

use App\Models\TixelloAccount;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autentificarea aplicației Tixello.
 *
 * Spre deosebire de `marketplace.auth`, NU cere o cheie de API: aplicatia
 * mobila e distribuita public, iar o cheie compilata in APK se poate extrage
 * din fisier. Cheile partenerilor raman pe server — aplicatia nu le vede
 * niciodata, iar serverul e cel care vorbeste cu lumile lor.
 */
class TixelloAppAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! $token) {
            return response()->json(['success' => false, 'error' => 'Unauthenticated'], 401);
        }

        $account = TixelloAccount::findByToken($token);

        if (! $account) {
            return response()->json(['success' => false, 'error' => 'Unauthenticated'], 401);
        }

        // Emailul neverificat poate citi, dar nu poate lega conturi si nu poate
        // cumpara: verificarea e ce impiedica atasarea la contul altcuiva.
        $request->attributes->set('tixello_account', $account);

        return $next($request);
    }
}
