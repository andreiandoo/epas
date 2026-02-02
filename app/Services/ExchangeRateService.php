<?php

namespace App\Services;

use App\Models\ExchangeRate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExchangeRateService
{
    /**
     * Supported currencies
     */
    public const CURRENCIES = ['EUR', 'RON'];

    /**
     * Base currency (all rates stored relative to this)
     */
    public const BASE_CURRENCY = 'EUR';

    /**
     * Fetch and store exchange rates from ECB
     */
    public function fetchAndStoreRates(?Carbon $date = null): bool
    {
        $date = $date ?? now();

        try {
            // Use ECB Statistical Data Warehouse API
            $rate = $this->fetchECBRate($date);

            if ($rate) {
                $this->storeRate($date, 'EUR', 'RON', $rate, 'ecb');
                return true;
            }

            // Fallback: try BNR
            $rate = $this->fetchBNRRate($date);

            if ($rate) {
                $this->storeRate($date, 'EUR', 'RON', $rate, 'bnr');
                return true;
            }

            Log::warning('Failed to fetch exchange rates', ['date' => $date->toDateString()]);
            return false;

        } catch (\Exception $e) {
            Log::error('Exchange rate fetch error', [
                'date' => $date->toDateString(),
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Fetch EUR/RON rate from ECB
     */
    protected function fetchECBRate(Carbon $date): ?float
    {
        // ECB provides rates in XML format
        $url = 'https://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml';

        try {
            $response = Http::timeout(10)->get($url);

            if (!$response->successful()) {
                return null;
            }

            $xml = simplexml_load_string($response->body());

            if (!$xml) {
                return null;
            }

            // Navigate to the Cube elements
            foreach ($xml->Cube->Cube->Cube as $cube) {
                $currency = (string) $cube['currency'];
                if ($currency === 'RON') {
                    return (float) $cube['rate'];
                }
            }

            return null;

        } catch (\Exception $e) {
            Log::debug('ECB fetch failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Fetch EUR/RON rate from BNR (Banca Nationala a Romaniei)
     */
    protected function fetchBNRRate(Carbon $date): ?float
    {
        $url = 'https://www.bnr.ro/nbrfxrates.xml';

        try {
            $response = Http::timeout(10)->get($url);

            if (!$response->successful()) {
                return null;
            }

            $xml = simplexml_load_string($response->body());

            if (!$xml) {
                return null;
            }

            // BNR XML structure
            foreach ($xml->Body->Cube->Rate as $rate) {
                $currency = (string) $rate['currency'];
                if ($currency === 'EUR') {
                    // BNR gives RON per 1 EUR
                    return (float) $rate;
                }
            }

            return null;

        } catch (\Exception $e) {
            Log::debug('BNR fetch failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Store or update exchange rate
     */
    protected function storeRate(Carbon $date, string $base, string $target, float $rate, string $source): void
    {
        ExchangeRate::updateOrCreate(
            [
                'date' => $date->toDateString(),
                'base_currency' => $base,
                'target_currency' => $target,
            ],
            [
                'rate' => $rate,
                'source' => $source,
            ]
        );
    }

    /**
     * Manually set exchange rate
     */
    public function setManualRate(Carbon $date, string $base, string $target, float $rate): ExchangeRate
    {
        return ExchangeRate::updateOrCreate(
            [
                'date' => $date->toDateString(),
                'base_currency' => strtoupper($base),
                'target_currency' => strtoupper($target),
            ],
            [
                'rate' => $rate,
                'source' => 'manual',
            ]
        );
    }

    /**
     * Convert amount between currencies
     */
    public function convert(float $amount, string $from, string $to, ?Carbon $date = null): ?float
    {
        return ExchangeRate::convert($amount, $from, $to, $date);
    }

    /**
     * Get current EUR/RON rate
     */
    public function getCurrentEurToRon(): ?float
    {
        return ExchangeRate::getLatestRate('EUR', 'RON');
    }

    /**
     * Backfill missing rates for date range
     */
    public function backfillRates(Carbon $startDate, Carbon $endDate): int
    {
        $count = 0;
        $current = $startDate->copy();

        while ($current <= $endDate) {
            // Skip weekends (no rates published)
            if (!$current->isWeekend()) {
                $exists = ExchangeRate::where('date', $current->toDateString())
                    ->where('base_currency', 'EUR')
                    ->where('target_currency', 'RON')
                    ->exists();

                if (!$exists) {
                    if ($this->fetchAndStoreRates($current)) {
                        $count++;
                    }
                }
            }

            $current->addDay();
        }

        return $count;
    }
}
