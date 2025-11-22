<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('X-API-Key') ?? $request->query('api_key');

        if (!$key) {
            return response()->json([
                'error' => 'API key required',
                'message' => 'Please provide an API key via X-API-Key header or api_key query parameter',
            ], 401);
        }

        $apiKey = ApiKey::where('key', $key)->first();

        if (!$apiKey) {
            return response()->json([
                'error' => 'Invalid API key',
            ], 401);
        }

        if (!$apiKey->isValid()) {
            return response()->json([
                'error' => 'API key is inactive or expired',
            ], 403);
        }

        // Verify HMAC signature if required
        if ($apiKey->require_signature) {
            $timestamp = $request->header('X-Timestamp');
            $signature = $request->header('X-Signature');

            if (!$timestamp || !$signature) {
                return response()->json([
                    'error' => 'Signature required',
                    'message' => 'This API key requires HMAC signature. Provide X-Timestamp and X-Signature headers.',
                ], 401);
            }

            if (!$apiKey->verifySignature($signature, (int) $timestamp, $request->path())) {
                return response()->json([
                    'error' => 'Invalid signature',
                    'message' => 'HMAC signature verification failed. Check your secret key and timestamp.',
                ], 401);
            }
        }

        $apiKey->recordUsage();

        $request->attributes->set('api_key', $apiKey);

        return $next($request);
    }
}
