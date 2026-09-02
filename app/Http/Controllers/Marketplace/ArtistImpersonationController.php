<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\MarketplaceArtistAccount;
use App\Models\MarketplaceClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Login-as-artist flow: mirrors OrganizerImpersonationController. Generates a
 * short-lived Sanctum token for the target MarketplaceArtistAccount and
 * redirects the admin to the marketplace's artist portal with
 * ?_admin_artist_token=… — the public-site head.php inline handler stores it
 * as the artist session token in localStorage.
 *
 * Allowed only for the authenticated `marketplace_admin` guard (Super Admin or
 * Marketplace Admin). Marketplace admins may only impersonate accounts within
 * their own marketplace_client. Token expires after 30 minutes.
 */
class ArtistImpersonationController extends Controller
{
    public function loginAs(int $artistAccountId): RedirectResponse
    {
        $admin = Auth::guard('marketplace_admin')->user() ?? Auth::guard('web')->user();
        if (!$admin) {
            abort(403, 'Unauthorized');
        }

        $account = MarketplaceArtistAccount::find($artistAccountId);
        if (!$account) {
            abort(404, 'Artist account not found');
        }

        // Non-super-admins can only impersonate accounts on their own marketplace.
        $isSuperAdmin = method_exists($admin, 'isSuperAdmin') ? $admin->isSuperAdmin() : false;
        if (!$isSuperAdmin) {
            $adminClientId = $admin->marketplace_client_id ?? null;
            if (!$adminClientId || $adminClientId !== $account->marketplace_client_id) {
                abort(403, 'Cannot impersonate an artist from a different marketplace');
            }
        }

        $client = MarketplaceClient::find($account->marketplace_client_id);
        if (!$client || empty($client->domain)) {
            abort(422, 'Marketplace domain not configured');
        }

        // Short-lived Sanctum token (30 min), descriptively named so admin-issued
        // tokens can be audited / revoked separately from regular artist tokens.
        $tokenName = 'admin-impersonation:' . ($admin->id ?? 'unknown');
        $token = $account->createToken($tokenName, ['*'], now()->addMinutes(30))->plainTextToken;

        // Force https — the impersonation token must never travel cleartext.
        // Land directly on the dashboard (not /artist/cont, which 302-redirects
        // and would drop the token query string before the JS handler runs).
        $domain = ltrim(str_replace(['https://', 'http://'], '', $client->domain), '/');
        $url = 'https://' . $domain . '/artist/cont/dashboard?_admin_artist_token=' . urlencode($token);

        return redirect()->away($url);
    }
}
