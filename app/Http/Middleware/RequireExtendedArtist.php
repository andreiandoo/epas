<?php

namespace App\Http\Middleware;

use App\Models\MarketplaceArtistAccount;
use App\Services\ExtendedArtist\ExtendedArtistAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate API-uri ale celor 4 module Extended Artist (Fan CRM, Booking, EPK, Tour).
 *
 * Aplicat în routes/api.php după middleware-ul auth:sanctum care injectează
 * MarketplaceArtistAccount drept user-ul autentificat. Returnează 403 cu
 * payload structurat pentru a permite frontend-ului să afișeze upsell.
 */
class RequireExtendedArtist
{
    public function __construct(private readonly ExtendedArtistAccess $access)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $account = $request->user();

        if (!$account instanceof MarketplaceArtistAccount) {
            return response()->json([
                'message' => 'Unauthorized',
                'error' => 'artist_account_required',
            ], 401);
        }

        if ($this->access->isEnabledFor($account)) {
            return $next($request);
        }

        $row = $account->extendedArtistActivation();
        $reason = match (true) {
            $row === null => 'not_activated',
            $row->status === 'expired' => 'expired',
            $row->status === 'cancelled' => 'cancelled',
            $row->status === 'suspended' => 'suspended',
            default => 'inactive',
        };

        return response()->json([
            'message' => 'Extended Artist subscription required',
            'error' => 'extended_artist_required',
            'reason' => $reason,
            'status' => $this->access->statusFor($account),
        ], 403);
    }
}
