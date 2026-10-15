<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Doar adminii panoului core (super-admin, admin). Pentru rutele web folosite
 * din /admin care nu trec prin Filament (ex. administrarea domeniilor).
 */
class EnsureCoreAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || ! in_array($user->role, ['super-admin', 'admin'], true)) {
            abort(403);
        }

        return $next($request);
    }
}
