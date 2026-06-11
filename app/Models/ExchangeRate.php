<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class ExchangeRate extends Model
{
    protected $fillable = [
        'date',
        'base_currency',
        'target_currency',
        'rate',
        'source',
    ];

    protected $casts = [
        'date' => 'date',
        'rate' => 'decimal:6',
    ];

    /**
     * Get the exchange rate for a specific date and currency pair
     */
    public static function getRate(string $from, string $to, ?Carbon $date = null): ?float
    {
        $date = $date ?? now();

        // Same currency = 1:1
        if (strtoupper($from) === strtoupper($to)) {
            return 1.0;
        }

        // Try to find direct rate
        $rate = static::where('date', '<=', $date->toDateString())
            ->where('base_currency', strtoupper($from))
            ->where('target_currency', strtoupper($to))
            ->orderBy('date', 'desc')
            ->first();

        if ($rate) {
            return (float) $rate->rate;
        }

        // Try inverse rate
        $inverseRate = static::where('date', '<=', $date->toDateString())
            ->where('base_currency', strtoupper($to))
            ->where('target_currency', strtoupper($from))
            ->orderBy('date', 'desc')
            ->first();

        if ($inverseRate) {
            return 1 / (float) $inverseRate->rate;
        }

        return null;
    }

    /**
     * Convert amount between currencies
     */
    public static function convert(float $amount, string $from, string $to, ?Carbon $date = null): ?float
    {
        $rate = static::getRate($from, $to, $date);

        if ($rate === null) {
            return null;
        }

        return round($amount * $rate, 2);
    }

    /**
     * Get latest rate for a currency pair
     */
    public static function getLatestRate(string $from, string $to): ?float
    {
        return static::getRate($from, $to, now());
    }

    /**
     * Scope for specific date
     */
    public function scopeForDate($query, Carbon $date)
    {
        return $query->where('date', $date->toDateString());
    }

    /**
     * Scope for currency pair
     */
    public function scopeForPair($query, string $from, string $to)
    {
        return $query
            ->where('base_currency', strtoupper($from))
            ->where('target_currency', strtoupper($to));
    }
}
