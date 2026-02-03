<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        // SECURITY FIX: Only accept API key via header, not query string
        // Query string parameters are logged in access logs, server logs, and browser history
        $key = $request->header('X-API-Key');

        if (!$key) {
            return response()->json([
                'error' => 'API key required',
                'message' => 'Please provide an API key via the X-API-Key header',
            ], 401);
        }

        // Use secure hash-based lookup
        $apiKey = ApiKey::findByKey($key);

        if (!$apiKey) {
            Log::warning('Invalid API key attempt', [
                'ip' => $request->ip(),
                'path' => $request->path(),
                'user_agent' => $request->userAgent(),
            ]);

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
                Log::warning('Invalid API signature attempt', [
                    'api_key_id' => $apiKey->id,
                    'ip' => $request->ip(),
                    'path' => $request->path(),
                ]);

                return response()->json([
                    'error' => 'Invalid signature',
                    'message' => 'HMAC signature verification failed. Check your secret key and timestamp.',
                ], 401);
            }
        }

        // Record usage with IP tracking
        $apiKey->recordUsage($request->ip());

        // Store API key and tenant_id in request attributes for downstream use
        $request->attributes->set('api_key', $apiKey);
        $request->attributes->set('tenant_id', $apiKey->tenant_id);

        return $next($request);
    }
}
