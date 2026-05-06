<?php

namespace App\Services\ExtendedArtist;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Road routing pentru Tour Optimizer.
 *
 * Foloseste OSRM (Open Source Routing Machine, bazat pe OpenStreetMap) prin demo-ul public.
 * Returneaza distanta REALA pe rute + timp estimat de condus.
 *
 * Cache: TTL 30 zile (cheia bazata pe coordonate rotunjite la 4 zecimale → un (lat,lng,lat,lng)
 *  unic per ruta intre orase. Acoperă majoritatea cazurilor cu un cache-hit ratio mare.).
 *
 * Fallback: daca OSRM e indisponibil sau timeout-uieste, returnam un calcul aproximativ
 * (Haversine × 1.35 pentru distanta, 75 km/h pentru timp).
 */
class RoadRoutingService
{
    public const CACHE_TTL = 2592000; // 30 zile
    public const TIMEOUT_S = 3;        // 3 secunde max per request
    public const ROAD_FACTOR_FALLBACK = 1.35;
    public const SPEED_FALLBACK_KMH = 75;
    public const OSRM_BASE = 'https://router.project-osrm.org/route/v1/driving';

    /**
     * Returneaza ['distance_km' => float, 'duration_min' => int, 'source' => 'osrm'|'fallback'].
     * Coordonatele identice → 0/0/'osrm'.
     */
    public function routeBetween(float $lat1, float $lng1, float $lat2, float $lng2): array
    {
        if ($this->isSamePoint($lat1, $lng1, $lat2, $lng2)) {
            return ['distance_km' => 0.0, 'duration_min' => 0, 'source' => 'osrm'];
        }

        $key = $this->cacheKey($lat1, $lng1, $lat2, $lng2);

        $cached = Cache::get($key);
        if (is_array($cached) && isset($cached['distance_km'], $cached['duration_min'])) {
            return $cached + ['source' => $cached['source'] ?? 'cache'];
        }

        // Try OSRM
        try {
            $url = self::OSRM_BASE . '/' .
                $this->fmt($lng1) . ',' . $this->fmt($lat1) . ';' .
                $this->fmt($lng2) . ',' . $this->fmt($lat2) .
                '?overview=false&alternatives=false&steps=false';

            $response = Http::timeout(self::TIMEOUT_S)
                ->retry(1, 100)
                ->get($url);

            if ($response->successful()) {
                $data = $response->json();
                if (($data['code'] ?? '') === 'Ok' && !empty($data['routes'][0])) {
                    $route = $data['routes'][0];
                    $result = [
                        'distance_km' => round((float) $route['distance'] / 1000, 1),
                        'duration_min' => (int) round((float) $route['duration'] / 60),
                        'source' => 'osrm',
                    ];
                    Cache::put($key, $result, self::CACHE_TTL);
                    return $result;
                }
            }
        } catch (\Throwable $e) {
            Log::debug('OSRM routing failed, using fallback', [
                'from' => "$lat1,$lng1",
                'to' => "$lat2,$lng2",
                'error' => $e->getMessage(),
            ]);
        }

        // Fallback: Haversine × 1.35
        $haversine = $this->haversineKm($lat1, $lng1, $lat2, $lng2);
        $distance = round($haversine * self::ROAD_FACTOR_FALLBACK, 1);
        $duration = (int) round(($distance / self::SPEED_FALLBACK_KMH) * 60);

        $result = [
            'distance_km' => $distance,
            'duration_min' => $duration,
            'source' => 'fallback',
        ];

        // Cache si fallback-ul, dar pe TTL mai scurt (1 zi) ca sa retry-eze OSRM curand
        Cache::put($key, $result, 86400);

        return $result;
    }

    protected function cacheKey(float $lat1, float $lng1, float $lat2, float $lng2): string
    {
        // Rotund la 4 zecimale → ~11m precizie, suficient pentru orase/venues.
        // Ordonam coordonatele canonical (cea mai mica intai) ca A→B si B→A sa hit-uie aceeasi cheie.
        $a = $this->fmt($lat1) . ',' . $this->fmt($lng1);
        $b = $this->fmt($lat2) . ',' . $this->fmt($lng2);
        [$first, $second] = strcmp($a, $b) <= 0 ? [$a, $b] : [$b, $a];
        return "tour:road:v1:{$first}:{$second}";
    }

    protected function fmt(float $coord): string
    {
        return number_format($coord, 4, '.', '');
    }

    protected function isSamePoint(float $lat1, float $lng1, float $lat2, float $lng2): bool
    {
        return abs($lat1 - $lat2) < 0.0001 && abs($lng1 - $lng2) < 0.0001;
    }

    public function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthR = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earthR * $c;
    }
}
