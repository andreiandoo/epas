<?php

namespace App\Support;

use App\Models\MarketplaceAdmin;
use App\Models\User;
use Illuminate\Auth\SessionGuard;

class SuperAdminMarketplaceSwitcher
{
    /**
     * Switch the marketplace_admin guard to $admin without regenerating the
     * session ID. Updates the AuthenticateSession password-hash sentinel for
     * both the panel guard and the global default so the next request doesn't
     * get flushed by a hash mismatch (which would also drop us back to the
     * first active marketplace). Shared by the /marketplace/switch-client route
     * + the admin-side "Login to marketplace" Filament actions on
     * MarketplaceClientResource.
     *
     * Lives here (PSR-4 autoloaded) rather than as a global function in
     * routes/web.php: when routes are cached, route:cache skips the route
     * files, so a function defined there is never loaded — the Filament
     * actions then hit "Call to undefined function".
     */
    public static function switchTo(
        MarketplaceAdmin $admin,
        int $clientId,
        User $superAdmin
    ): void {
        $guard = auth('marketplace_admin');
        $guardSessionKey = $guard instanceof SessionGuard
            ? $guard->getName()
            : 'login_marketplace_admin_' . sha1(SessionGuard::class);

        session()->put($guardSessionKey, $admin->getAuthIdentifier());

        // AuthenticateSession flushes the session when the new user's password
        // hash doesn't match the value stored under password_hash_<guard>.
        // Pre-populate it for both the panel guard and the global default.
        $hashForCookie = $admin->getAuthPassword();
        try {
            if ($guard instanceof SessionGuard && method_exists($guard, 'hashPasswordForCookie')) {
                $hashForCookie = $guard->hashPasswordForCookie($admin->getAuthPassword());
            }
        } catch (\Throwable $e) {
            // older guards don't have hashPasswordForCookie — fall through to raw hash
        }
        session()->put('password_hash_marketplace_admin', $hashForCookie);
        session()->put('password_hash_web', $hashForCookie);

        session([
            'super_admin_marketplace_client_id' => $clientId,
            'marketplace_is_super_admin' => true,
            'marketplace_super_admin_user_id' => $superAdmin->id,
        ]);

        $guard->setUser($admin);
        session()->save();
    }
}
