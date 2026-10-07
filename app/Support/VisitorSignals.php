<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Browser signals of the visitor behind a marketplace frontend proxy, for server-side
 * conversion APIs (Meta CAPI, TikTok Events API).
 *
 * Proxied calls reach core from the frontend server with the proxy's own IP and
 * User-Agent, so an order created at checkout carried the same server IP and
 * "Ambilet Marketplace/1.0" for every buyer, and never the Meta click/browser ids.
 * The proxy forwards the real values in X-Visitor-* headers; like VisitorIp they are
 * trusted only on requests authenticated as a marketplace client.
 */
class VisitorSignals
{
    /**
     * Keys ready to merge into orders.meta: client_ip, client_user_agent, fbp, fbc, visitor_id.
     * Missing values are left out.
     */
    public static function from(Request $request): array
    {
        if ($request->attributes->get('marketplace_client') === null) {
            return [];
        }

        $userAgent = trim((string) $request->header('X-Visitor-UA', ''));
        $visitorId = trim((string) $request->header('X-Visitor-ID', ''));

        return array_filter([
            'client_ip' => VisitorIp::from($request),
            'client_user_agent' => $userAgent !== '' ? mb_substr($userAgent, 0, 500) : null,
            'fbp' => self::metaCookie($request->header('X-Visitor-Fbp')),
            'fbc' => self::metaCookie($request->header('X-Visitor-Fbc')),
            'visitor_id' => preg_match('/^[A-Za-z0-9._:-]{8,64}$/', $visitorId) ? $visitorId : null,
        ], fn ($v) => $v !== null && $v !== '');
    }

    /**
     * A _fbp/_fbc cookie value, only when it has Meta's `fb.{n}.{timestamp}.{value}` shape.
     * Meta rejects the whole event for a malformed fbc, so anything else is dropped.
     */
    protected static function metaCookie(mixed $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '' || strlen($value) > 512 || !preg_match('/^fb\.\d+\.\d+\.[A-Za-z0-9_.\-]+$/', $value)) {
            return null;
        }

        return $value;
    }
}
