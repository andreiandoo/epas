<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\MarketplaceClient;
use App\Models\MarketplaceOrganizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Login-as-organizer flow: generates a short-lived Sanctum token for the
 * target MarketplaceOrganizer and redirects the admin to that marketplace's
 * organizer panel with ?_admin_token=… — the public-site auth.js stores it
 * in localStorage and treats the session as that organizer.
 *
 * Allowed only for authenticated `marketplace_admin` guard (Super Admin or
 * Marketplace Admin). Token expires after 30 minutes — enough for a quick
 * support session, short enough to avoid lingering admin tokens.
 */
class OrganizerImpersonationController extends Controller
{
    public function loginAs(int $organizerId): RedirectResponse
    {
        $admin = Auth::guard('marketplace_admin')->user() ?? Auth::guard('web')->user();
        if (!$admin) {
            abort(403, 'Unauthorized');
        }

        $organizer = MarketplaceOrganizer::find($organizerId);
        if (!$organizer) {
            abort(404, 'Organizer not found');
        }

        // If the admin is a marketplace_admin (not super-admin), they can only
        // impersonate organizers within their own marketplace_client.
        $isSuperAdmin = method_exists($admin, 'isSuperAdmin') ? $admin->isSuperAdmin() : false;
        if (!$isSuperAdmin) {
            $adminClientId = $admin->marketplace_client_id ?? null;
            if (!$adminClientId || $adminClientId !== $organizer->marketplace_client_id) {
                abort(403, 'Cannot impersonate an organizer from a different marketplace');
            }
        }

        $client = MarketplaceClient::find($organizer->marketplace_client_id);
        if (!$client || empty($client->domain)) {
            abort(422, 'Marketplace domain not configured');
        }

        // Short-lived Sanctum token (30 min). Name is descriptive so we can
        // audit / revoke admin-issued tokens separately from regular ones.
        $tokenName = 'admin-impersonation:' . ($admin->id ?? 'unknown');
        $token = $organizer->createToken($tokenName, ['*'], now()->addMinutes(30))->plainTextToken;

        // Choose landing page based on organizer type. Leisure organizers land
        // on the leisure dashboard (their main editor); others on the regular
        // events panel.
        $landing = ($organizer->organizer_type ?? null) === 'leisure'
            ? '/organizator/leisure'
            : '/organizator/events';

        // Build absolute URL using the marketplace's configured domain. Force
        // https — every production marketplace serves the organizer panel
        // over TLS, and the impersonation token must never travel cleartext.
        $domain = ltrim(str_replace(['https://', 'http://'], '', $client->domain), '/');
        $url = 'https://' . $domain . $landing . '?_admin_token=' . urlencode($token);

        return redirect()->away($url);
    }
}
