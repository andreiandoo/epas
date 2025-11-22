<?php

namespace App\Http\Middleware;

use App\Models\Domain;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TenantClientCors
{
    public function handle(Request $request, Closure $next): Response
    {
        $origin = $request->header('Origin');

        if (!$origin) {
            return $next($request);
        }

        $originHost = parse_url($origin, PHP_URL_HOST);
        $allowCors = false;

        // Allow localhost for development
        if (in_array($originHost, ['localhost', '127.0.0.1'])) {
            $allowCors = true;
        } else {
            // Check if origin is a verified tenant domain
            $domain = Domain::where('domain', $originHost)
                ->where('is_active', true)
                ->first();

            if (!$domain) {
                // Check for subdomain match
                $parts = explode('.', $originHost);
                if (count($parts) > 2) {
                    $baseDomain = implode('.', array_slice($parts, -2));
                    $domain = Domain::where('domain', $baseDomain)
                        ->where('is_active', true)
                        ->first();
                }
            }

            if ($domain) {
                $allowCors = true;
            }
        }

        // Handle preflight OPTIONS requests immediately
        if ($request->isMethod('OPTIONS') && $allowCors) {
            $response = response('', 200);
            return $this->addCorsHeaders($response, $origin);
        }

        // Execute request and get response
        try {
            $response = $next($request);
        } catch (\Exception $e) {
            $response = response()->json(['error' => 'Server error'], 500);
        }

        // Add CORS headers even on error responses
        if ($allowCors) {
            return $this->addCorsHeaders($response, $origin);
        }

        return $response;
    }

    protected function addCorsHeaders(Response $response, string $origin): Response
    {
        $response->headers->set('Access-Control-Allow-Origin', $origin);
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Tenant-ID, X-Domain-ID, X-Package-Hash, X-Timestamp, X-Nonce, X-Signature, X-Session-ID');
        $response->headers->set('Access-Control-Allow-Credentials', 'true');
        $response->headers->set('Access-Control-Max-Age', '86400');

        return $response;
    }
}
