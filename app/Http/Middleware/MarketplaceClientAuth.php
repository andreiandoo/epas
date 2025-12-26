<?php

namespace App\Http\Middleware;

use App\Models\MarketplaceClient;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MarketplaceClientAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get API key from header or query parameter
        $apiKey = $request->header('X-API-Key')
            ?? $request->header('Authorization')
            ?? $request->query('api_key');

        // Remove "Bearer " prefix if present
        if ($apiKey && str_starts_with($apiKey, 'Bearer ')) {
            $apiKey = substr($apiKey, 7);
        }

        if (empty($apiKey)) {
            return response()->json([
                'success' => false,
                'message' => 'API key is required',
            ], 401);
        }

        // Find marketplace client by API key
        $client = MarketplaceClient::where('api_key', $apiKey)->first();

        if (!$client) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid API key',
            ], 401);
        }

        if (!$client->isActive()) {
            return response()->json([
                'success' => false,
                'message' => 'Marketplace client account is not active',
            ], 403);
        }

        // Attach client to request for use in controllers
        $request->attributes->set('marketplace_client', $client);

        // Update last API call timestamp (async to not slow down request)
        dispatch(function () use ($client) {
            $client->touchApiCall();
        })->afterResponse();

        // Set CORS headers for marketplace client domains
        $response = $next($request);

        // Add CORS headers
        $origin = $request->header('Origin');
        if ($origin) {
            // Allow the client's registered domain or any origin (since they host their own site)
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
            $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-API-Key, X-Requested-With');
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
            $response->headers->set('Access-Control-Max-Age', '86400');
        }

        return $response;
    }
}
