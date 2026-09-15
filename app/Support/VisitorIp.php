<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * IP of the browser behind a marketplace frontend proxy (e.g. ambilet.ro api/proxy.php).
 *
 * Proxied API calls reach core from the frontend server, so $request->ip() is that
 * server for every visitor and all visits were geolocated to the server's city.
 * The proxy sends the visitor's address in X-Visitor-IP. It is trusted only on
 * requests authenticated as a marketplace client, and only for a public IP.
 */
class VisitorIp
{
    public static function from(Request $request): ?string
    {
        $forwarded = trim((string) $request->header('X-Visitor-IP', ''));

        if ($forwarded !== ''
            && $request->attributes->get('marketplace_client') !== null
            && filter_var($forwarded, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return $forwarded;
        }

        return $request->ip();
    }
}
