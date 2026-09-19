<?php

namespace App\Http\Middleware;

use App\Models\MarketplaceClient;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets a request through only when the calling marketplace has every listed
 * microservice active. Runs after `marketplace.auth`, which puts the client
 * on the request.
 *
 *     ->middleware(['marketplace.auth', 'marketplace.microservice:activities-module'])
 *
 * This is the wall around marketplace-specific modules (the bilete.online
 * activities work): a marketplace without the microservice gets a 403 and
 * none of the module's code runs for it.
 */
class EnsureMarketplaceMicroservice
{
    public function handle(Request $request, Closure $next, string ...$slugs): Response
    {
        $client = $request->attributes->get('marketplace_client');

        if (!$client instanceof MarketplaceClient) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Marketplace client authentication required',
            ], 401);
        }

        foreach ($slugs as $slug) {
            if (!$client->hasMicroservice($slug)) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'This module is not enabled for this marketplace',
                ], 403);
            }
        }

        return $next($request);
    }
}
