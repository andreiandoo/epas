<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeoIpService
{
    protected const CACHE_TTL_HOURS = 24;

    /**
     * Get location data from IP address using multiple providers with fallback
     * Order: ipgeolocation.io -> ip-api.com -> ipwhois.io
     */
    public function getLocation(string $ip): array
    {
        // Skip for localhost/private IPs - use București as default for local dev
        if ($this->isLocalIp($ip)) {
            return $this->getDefaultLocation();
        }

        // Try to get from cache first
        $cacheKey = "geoip_{$ip}";
        $cached = Cache::get($cacheKey);
        if ($cached) {
            return $cached;
        }

        // Try providers in order
        $location = $this->tryIpGeolocationIo($ip)
            ?? $this->tryIpApi($ip)
            ?? $this->tryIpWhois($ip)
            ?? $this->getNullLocation();

        // Cache successful results for 24 hours
        if ($location['latitude'] !== null) {
            Cache::put($cacheKey, $location, now()->addHours(self::CACHE_TTL_HOURS));
        }

        return $location;
    }

    /**
     * Check if IP is local/private
     */
    protected function isLocalIp(string $ip): bool
    {
        return in_array($ip, ['127.0.0.1', '::1'])
            || str_starts_with($ip, '192.168.')
            || str_starts_with($ip, '10.')
            || str_starts_with($ip, '172.16.')
            || str_starts_with($ip, '172.17.')
            || str_starts_with($ip, '172.18.')
            || str_starts_with($ip, '172.19.')
            || str_starts_with($ip, '172.2')
            || str_starts_with($ip, '172.30.')
            || str_starts_with($ip, '172.31.');
    }

    /**
     * Default location for local development (București)
     */
    protected function getDefaultLocation(): array
    {
        return [
            'country_code' => 'RO',
            'region' => 'București',
            'city' => 'București',
            'latitude' => 44.4268,
            'longitude' => 26.1025,
            'provider' => 'local_default',
        ];
    }

    /**
     * Null location when all providers fail
     */
    protected function getNullLocation(): array
    {
        return [
            'country_code' => null,
            'region' => null,
            'city' => null,
            'latitude' => null,
            'longitude' => null,
            'provider' => null,
        ];
    }

    /**
     * Provider 1: ipgeolocation.io (30k requests/month, good accuracy)
     * No API key required for basic usage
     */
    protected function tryIpGeolocationIo(string $ip): ?array
    {
        try {
            $response = Http::timeout(2)
                ->get("https://api.ipgeolocation.io/ipgeo", [
                    'ip' => $ip,
                ]);

            if ($response->successful()) {
                $data = $response->json();

                // Check if we got valid data (no error message)
                if (!isset($data['message']) && isset($data['latitude'])) {
                    return [
                        'country_code' => $data['country_code2'] ?? null,
                        'region' => $data['state_prov'] ?? null,
                        'city' => $data['city'] ?? null,
                        'latitude' => isset($data['latitude']) ? (float) $data['latitude'] : null,
                        'longitude' => isset($data['longitude']) ? (float) $data['longitude'] : null,
                        'provider' => 'ipgeolocation.io',
                    ];
                }
            }
        } catch (\Exception $e) {
            Log::debug('ipgeolocation.io lookup failed', ['ip' => $ip, 'error' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * Provider 2: ip-api.com (45 requests/minute, free, no key needed)
     */
    protected function tryIpApi(string $ip): ?array
    {
        try {
            $response = Http::timeout(2)
                ->get("http://ip-api.com/json/{$ip}", [
                    'fields' => 'status,countryCode,regionName,city,lat,lon',
                ]);

            if ($response->successful()) {
                $data = $response->json();

                if (($data['status'] ?? '') === 'success') {
                    return [
                        'country_code' => $data['countryCode'] ?? null,
                        'region' => $data['regionName'] ?? null,
                        'city' => $data['city'] ?? null,
                        'latitude' => $data['lat'] ?? null,
                        'longitude' => $data['lon'] ?? null,
                        'provider' => 'ip-api.com',
                    ];
                }
            }
        } catch (\Exception $e) {
            Log::debug('ip-api.com lookup failed', ['ip' => $ip, 'error' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * Provider 3: ipwhois.io (10k requests/month, real-time updates)
     */
    protected function tryIpWhois(string $ip): ?array
    {
        try {
            $response = Http::timeout(2)
                ->get("https://ipwhois.app/json/{$ip}");

            if ($response->successful()) {
                $data = $response->json();

                if (($data['success'] ?? true) !== false) {
                    return [
                        'country_code' => $data['country_code'] ?? null,
                        'region' => $data['region'] ?? null,
                        'city' => $data['city'] ?? null,
                        'latitude' => isset($data['latitude']) ? (float) $data['latitude'] : null,
                        'longitude' => isset($data['longitude']) ? (float) $data['longitude'] : null,
                        'provider' => 'ipwhois.io',
                    ];
                }
            }
        } catch (\Exception $e) {
            Log::debug('ipwhois.io lookup failed', ['ip' => $ip, 'error' => $e->getMessage()]);
        }

        return null;
    }
}
