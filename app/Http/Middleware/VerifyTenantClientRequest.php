<?php

namespace App\Http\Middleware;

use App\Models\Domain;
use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyTenantClientRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        // Get required headers
        $tenantId = $request->header('X-Tenant-ID');
        $domainId = $request->header('X-Domain-ID');
        $packageHash = $request->header('X-Package-Hash');
        $timestamp = $request->header('X-Timestamp');
        $nonce = $request->header('X-Nonce');
        $signature = $request->header('X-Signature');

        if (!$tenantId || !$domainId || !$packageHash) {
            return response()->json([
                'success' => false,
                'message' => 'Missing required headers',
            ], 400);
        }

        // Verify timestamp (prevent replay attacks - 5 minute window)
        if ($timestamp) {
            $requestTime = (int) $timestamp;
            $currentTime = now()->timestamp * 1000;
            $timeDiff = abs($currentTime - $requestTime);

            if ($timeDiff > 300000) { // 5 minutes in milliseconds
                return response()->json([
                    'success' => false,
                    'message' => 'Request expired',
                ], 401);
            }
        }

        // Find tenant and domain
        $tenant = Tenant::find($tenantId);
        if (!$tenant || !$tenant->isActive()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid tenant',
            ], 403);
        }

        $domain = Domain::where('id', $domainId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$domain || !$domain->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid domain',
            ], 403);
        }

        // Verify package hash
        $package = $domain->packages()
            ->where('package_hash', $packageHash)
            ->where('status', 'ready')
            ->first();

        if (!$package) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid package',
            ], 403);
        }

        // Verify request signature
        if ($signature && config('tenant-package.security.request_signing', true)) {
            $expectedSignature = $this->calculateSignature(
                $request->method(),
                $request->path(),
                $request->getContent(),
                $timestamp,
                $nonce,
                $packageHash
            );

            if (!hash_equals($expectedSignature, $signature)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid signature',
                ], 401);
            }
        }

        // Verify origin/referer matches domain
        $origin = $request->header('Origin') ?? $request->header('Referer');
        if ($origin) {
            $originHost = parse_url($origin, PHP_URL_HOST);
            if ($originHost !== $domain->domain && !str_ends_with($originHost, '.' . $domain->domain)) {
                // Allow localhost for development
                if ($originHost !== 'localhost' && $originHost !== '127.0.0.1') {
                    return response()->json([
                        'success' => false,
                        'message' => 'Origin mismatch',
                    ], 403);
                }
            }
        }

        // Store tenant and domain in request for controllers
        $request->attributes->set('tenant', $tenant);
        $request->attributes->set('domain', $domain);
        $request->attributes->set('package', $package);

        return $next($request);
    }

    protected function calculateSignature(
        string $method,
        string $path,
        string $body,
        ?string $timestamp,
        ?string $nonce,
        string $packageHash
    ): string {
        $payload = implode('|', [
            $method,
            '/' . ltrim($path, '/'),
            $body,
            $timestamp ?? '',
            $nonce ?? '',
            $packageHash,
        ]);

        return hash('sha256', $payload);
    }
}
