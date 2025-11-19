<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class DebugFilamentAuth extends Middleware
{
    public function handle($request, Closure $next, ...$guards)
    {
        // Check if user is authenticated
        $this->authenticate($request, $guards);

        // Get the current Filament panel
        $panel = Filament::getCurrentPanel();

        // Explicitly check if user can access panel
        if ($panel && auth()->check()) {
            $user = auth()->user();

            if (!$user->canAccessPanel($panel)) {
                abort(403);
            }
        }

        return $next($request);
    }

    protected function redirectTo($request): ?string
    {
        return Filament::getLoginUrl();
    }
}
