<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyApiKey
{
    /**
     * Handle an incoming request.
     *
     * Backward compatibility: the middleware accepts an optional
     * $requiredScope argument as `api.key:<scope>`. Existing routes
     * declared with `api.key` (no scope) pass through unchanged;
     * legacy keys (scopes column NULL) are treated as unrestricted.
     * IP allowlist and per-key rate limit checks likewise skip when
     * the corresponding column on the key is NULL.
     */
    public function handle(Request $request, Closure $next, ?string $requiredScope = null): Response
    {
        $key = $request->header('X-API-Key') ?? $request->query('api_key');

        if (!$key) {
            return response()->json([
                'error' => 'API key required',
                'message' => 'Please provide an API key via X-API-Key header or api_key query parameter',
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

        // IP allowlist (no-op when the key's allowed_ips is NULL)
        if (!$apiKey->ipIsAllowed($request->ip())) {
            Log::warning('API key blocked by IP allowlist', [
                'api_key_id' => $apiKey->id,
                'ip' => $request->ip(),
                'path' => $request->path(),
            ]);

            return response()->json([
                'error' => 'IP not allowed for this API key',
            ], 403);
        }

        // Scope check (no-op unless the route was declared with a scope
        // AND the key has scopes populated — legacy keys pass through)
        if ($requiredScope !== null && !$apiKey->hasScope($requiredScope)) {
            Log::warning('API key lacks required scope', [
                'api_key_id' => $apiKey->id,
                'required_scope' => $requiredScope,
                'key_scopes' => $apiKey->scopes,
                'path' => $request->path(),
            ]);

            return response()->json([
                'error' => 'API key lacks required scope',
                'required_scope' => $requiredScope,
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
        $request->attributes->set('api_key_id', $apiKey->id);
        $request->attributes->set('tenant_id', $apiKey->tenant_id);

        return $next($request);
    }
}
