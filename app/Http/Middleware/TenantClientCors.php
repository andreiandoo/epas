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

        // Allow localhost for development
        if (in_array($originHost, ['localhost', '127.0.0.1'])) {
            return $this->addCorsHeaders($next($request), $origin);
        }

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
            return $this->addCorsHeaders($next($request), $origin);
        }

        // Origin not allowed
        return $next($request);
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
