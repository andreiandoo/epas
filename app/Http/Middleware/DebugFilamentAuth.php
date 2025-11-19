<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DebugFilamentAuth extends Middleware
{
    public function handle($request, Closure $next, ...$guards)
    {
        Log::info('=== DebugFilamentAuth::handle() START ===', [
            'url' => $request->url(),
            'guards' => $guards,
            'is_authenticated' => auth()->check(),
            'user_id' => auth()->id(),
            'session_id' => session()->getId(),
        ]);

        try {
            // Check if user is authenticated
            $this->authenticate($request, $guards);

            Log::info('=== DebugFilamentAuth - After authenticate() ===', [
                'is_authenticated' => auth()->check(),
                'user_id' => auth()->id(),
                'user_email' => auth()->user()?->email,
            ]);

            // Get the panel
            $panel = Filament::getCurrentPanel();

            Log::info('=== DebugFilamentAuth - Got panel ===', [
                'panel_id' => $panel?->getId(),
                'panel_path' => $panel?->getPath(),
            ]);

            // Check if user can access panel
            if ($panel && auth()->check()) {
                $user = auth()->user();

                Log::info('=== DebugFilamentAuth - About to check canAccessPanel ===', [
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                ]);

                $canAccess = $user->canAccessPanel($panel);

                Log::info('=== DebugFilamentAuth - canAccessPanel result ===', [
                    'can_access' => $canAccess,
                ]);

                if (!$canAccess) {
                    Log::warning('=== DebugFilamentAuth - User CANNOT access panel, aborting 403 ===');
                    abort(403);
                }
            }

            Log::info('=== DebugFilamentAuth - All checks passed, continuing ===');

            return $next($request);

        } catch (\Throwable $e) {
            Log::error('=== DebugFilamentAuth - EXCEPTION ===', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    protected function redirectTo($request): ?string
    {
        Log::info('=== DebugFilamentAuth::redirectTo() called ===');
        return Filament::getLoginUrl();
    }
}
