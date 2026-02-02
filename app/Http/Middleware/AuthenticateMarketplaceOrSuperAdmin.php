<?php

namespace App\Http\Middleware;

use App\Models\MarketplaceAdmin;
use App\Models\MarketplaceClient;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Allows access to marketplace panel for:
 * 1. Authenticated marketplace admins (marketplace_admin guard)
 * 2. Super-admins from core admin panel (web guard) - auto-logs them in
 */
class AuthenticateMarketplaceOrSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        // If already authenticated via marketplace_admin guard, continue
        if (Auth::guard('marketplace_admin')->check()) {
            return $next($request);
        }

        // Check if user is authenticated via web guard and is super-admin
        if (Auth::guard('web')->check()) {
            $user = Auth::guard('web')->user();

            if ($user->isSuperAdmin()) {
                // Get selected marketplace client from session, or use first one
                $clientId = session('super_admin_marketplace_client_id');

                if (!$clientId) {
                    // If no client selected, get the first active one
                    $client = MarketplaceClient::where('status', 'active')->first();
                    if ($client) {
                        $clientId = $client->id;
                        session(['super_admin_marketplace_client_id' => $clientId]);
                    }
                }

                if ($clientId) {
                    // Find or create a system admin for this marketplace
                    $marketplaceAdmin = $this->getOrCreateSystemAdmin($clientId, $user);

                    if ($marketplaceAdmin) {
                        // Log in as this marketplace admin
                        Auth::guard('marketplace_admin')->login($marketplaceAdmin);

                        // Mark this as super-admin session
                        session(['marketplace_is_super_admin' => true]);
                        session(['marketplace_super_admin_user_id' => $user->id]);

                        return $next($request);
                    }
                }
            }
        }

        // Not authenticated - let Filament's auth middleware handle the redirect
        return $next($request);
    }

    /**
     * Get or create a system admin for the given marketplace client
     */
    protected function getOrCreateSystemAdmin(int $clientId, $coreUser): ?MarketplaceAdmin
    {
        // Look for existing system admin or admin with super_admin role
        $admin = MarketplaceAdmin::where('marketplace_client_id', $clientId)
            ->where(function ($q) use ($coreUser) {
                $q->where('email', $coreUser->email)
                  ->orWhere('role', 'super_admin');
            })
            ->first();

        if ($admin) {
            return $admin;
        }

        // Create a system admin entry for this super-admin
        return MarketplaceAdmin::create([
            'marketplace_client_id' => $clientId,
            'email' => $coreUser->email,
            'password' => bcrypt(uniqid('system_', true)), // Random password - won't be used
            'name' => $coreUser->name . ' (System)',
            'role' => 'super_admin',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
    }
}
