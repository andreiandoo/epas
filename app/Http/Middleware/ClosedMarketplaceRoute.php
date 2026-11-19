<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Closes marketplace-client routes that nothing legitimate calls, but that the
 * marketplace API key alone could use: list every order, cancel, refund, read
 * tickets by numeric id, validate a barcode. The key has been public (repo
 * history, mobile app bundles), so these routes must not answer to it.
 *
 * Every attempt is logged, so an integrator we did not know about shows up in
 * the logs instead of failing silently.
 */
class ClosedMarketplaceRoute
{
    public function handle(Request $request, Closure $next): Response
    {
        $client = $request->attributes->get('marketplace_client');

        Log::warning('Closed marketplace route called', [
            'client_id' => $client?->id,
            'ip' => $request->ip(),
            'endpoint' => $request->path(),
            'method' => $request->method(),
        ]);

        return response()->json([
            'success' => false,
            'message' => 'This endpoint is not available',
        ], 403);
    }
}
