<?php

namespace App\Services\Leisure;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Weather forecast pentru locatii leisure (ex: Lacul Sf. Ana).
 *
 * Provider: Open-Meteo (free, no API key, no rate limit rezonabil).
 * Cache: 1h per (lat, lng) — vremea nu se schimba drastic in 1h + suntem sub
 * limita gratuita ~10k req/zi cu marja larga.
 *
 * Fail-safe: return null la orice eroare (network, timeout, JSON parse) → widget
 * UI se ascunde grațios, restul dashboard-ului functioneaza.
 */
class WeatherService
{
    private const CACHE_TTL = 3600; // 1h
    private const HTTP_TIMEOUT = 5;
    private const API_BASE = 'https://api.open-meteo.com/v1/forecast';

    /**
     * Prognoza pentru 7 zile (azi + urmatoarele 6).
     *
     * @return array|null [
     *   'location' => ['lat','lng'],
     *   'timezone' => string,
     *   'days' => [
     *     ['date','temp_max','temp_min','precip_mm','uv_max','weathercode','icon','label']
     *   ]
     * ]
     */
    public function getForecast(float $lat, float $lng, int $days = 7): ?array
    {
        $days = max(1, min(14, $days)); // Open-Meteo suporta max 16, cap safety 14
        // Rotund coord la 3 decimale pentru cache-hit rate mai bun (~110m rezolutie, ok pt vreme)
        $latKey = round($lat, 3);
        $lngKey = round($lng, 3);
        $cacheKey = "leisure_weather_v1_{$latKey}_{$lngKey}_{$days}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($lat, $lng, $days) {
            try {
                $response = Http::timeout(self::HTTP_TIMEOUT)
                    ->retry(1, 200)
                    ->get(self::API_BASE, [
                        'latitude' => $lat,
                        'longitude' => $lng,
                        'daily' => 'temperature_2m_max,temperature_2m_min,precipitation_sum,weathercode,uv_index_max',
                        'timezone' => 'Europe/Bucharest',
                        'forecast_days' => $days,
                    ]);

                if (!$response->ok()) {
                    Log::warning('[WeatherService] Open-Meteo non-200', ['status' => $response->status(), 'body' => $response->body()]);
                    return null;
                }

                $data = $response->json();
                $daily = $data['daily'] ?? null;
                if (!$daily || empty($daily['time'])) {
                    return null;
                }

                $days = [];
                foreach ($daily['time'] as $i => $date) {
                    $code = (int) ($daily['weathercode'][$i] ?? 0);
                    $meta = self::describeCode($code);
                    $days[] = [
                        'date' => $date,
                        'temp_max' => isset($daily['temperature_2m_max'][$i]) ? round((float) $daily['temperature_2m_max'][$i], 1) : null,
                        'temp_min' => isset($daily['temperature_2m_min'][$i]) ? round((float) $daily['temperature_2m_min'][$i], 1) : null,
                        'precip_mm' => isset($daily['precipitation_sum'][$i]) ? round((float) $daily['precipitation_sum'][$i], 1) : null,
                        'uv_max' => isset($daily['uv_index_max'][$i]) ? round((float) $daily['uv_index_max'][$i], 1) : null,
                        'weathercode' => $code,
                        'icon' => $meta['icon'],
                        'label' => $meta['label'],
                    ];
                }

                return [
                    'location' => ['lat' => $lat, 'lng' => $lng],
                    'timezone' => $data['timezone'] ?? 'Europe/Bucharest',
                    'days' => $days,
                ];
            } catch (\Throwable $e) {
                Log::warning('[WeatherService] fetch failed', ['error' => $e->getMessage(), 'lat' => $lat, 'lng' => $lng]);
                return null;
            }
        });
    }

    /**
     * WMO Weather codes → emoji + label RO.
     * Ref: https://open-meteo.com/en/docs (Weather variable documentation)
     */
    private static function describeCode(int $code): array
    {
        $map = [
            0 => ['☀️', 'Senin'],
            1 => ['🌤️', 'Aproape senin'],
            2 => ['⛅', 'Parțial înnorat'],
            3 => ['☁️', 'Înnorat'],
            45 => ['🌫️', 'Ceață'],
            48 => ['🌫️', 'Ceață cu chiciură'],
            51 => ['🌦️', 'Burniță slabă'],
            53 => ['🌦️', 'Burniță moderată'],
            55 => ['🌧️', 'Burniță densă'],
            56 => ['🌨️', 'Burniță înghețată slabă'],
            57 => ['🌨️', 'Burniță înghețată densă'],
            61 => ['🌦️', 'Ploaie slabă'],
            63 => ['🌧️', 'Ploaie moderată'],
            65 => ['🌧️', 'Ploaie puternică'],
            66 => ['🌨️', 'Ploaie înghețată slabă'],
            67 => ['🌨️', 'Ploaie înghețată puternică'],
            71 => ['🌨️', 'Ninsoare slabă'],
            73 => ['❄️', 'Ninsoare moderată'],
            75 => ['❄️', 'Ninsoare puternică'],
            77 => ['🌨️', 'Măzăriche'],
            80 => ['🌦️', 'Averse slabe'],
            81 => ['🌧️', 'Averse moderate'],
            82 => ['⛈️', 'Averse puternice'],
            85 => ['🌨️', 'Averse de ninsoare slabe'],
            86 => ['❄️', 'Averse de ninsoare puternice'],
            95 => ['⛈️', 'Furtună'],
            96 => ['⛈️', 'Furtună cu grindină slabă'],
            99 => ['⛈️', 'Furtună cu grindină puternică'],
        ];
        return ['icon' => $map[$code][0] ?? '❓', 'label' => $map[$code][1] ?? 'Necunoscut'];
    }
}
