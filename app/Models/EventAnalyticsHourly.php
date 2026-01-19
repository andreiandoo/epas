<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventAnalyticsHourly extends Model
{
    protected $table = 'event_analytics_hourly';

    protected $fillable = [
        'event_id',
        'date',
        'hour',
        'page_views',
        'unique_visitors',
        'ticket_views',
        'add_to_carts',
        'checkouts_started',
        'purchases',
        'tickets_sold',
        'revenue_cents',
        'lineup_views',
        'pricing_views',
        'faq_views',
        'gallery_views',
        'shares',
        'interests',
        'bounces',
        'total_time_on_page',
        'traffic_sources',
        'utm_campaigns',
        'devices',
        'locations',
    ];

    protected $casts = [
        'date' => 'date',
        'hour' => 'integer',
        'page_views' => 'integer',
        'unique_visitors' => 'integer',
        'ticket_views' => 'integer',
        'add_to_carts' => 'integer',
        'checkouts_started' => 'integer',
        'purchases' => 'integer',
        'tickets_sold' => 'integer',
        'revenue_cents' => 'integer',
        'lineup_views' => 'integer',
        'pricing_views' => 'integer',
        'faq_views' => 'integer',
        'gallery_views' => 'integer',
        'shares' => 'integer',
        'interests' => 'integer',
        'bounces' => 'integer',
        'total_time_on_page' => 'integer',
        'traffic_sources' => 'array',
        'utm_campaigns' => 'array',
        'devices' => 'array',
        'locations' => 'array',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Get revenue in main currency unit
     */
    public function getRevenueAttribute(): float
    {
        return $this->revenue_cents / 100;
    }

    /**
     * Get conversion rate for this hour
     */
    public function getConversionRateAttribute(): float
    {
        if ($this->unique_visitors === 0) {
            return 0;
        }
        return round(($this->purchases / $this->unique_visitors) * 100, 2);
    }

    /**
     * Get bounce rate for this hour
     */
    public function getBounceRateAttribute(): float
    {
        if ($this->page_views === 0) {
            return 0;
        }
        return round(($this->bounces / $this->page_views) * 100, 2);
    }

    /**
     * Scope to get data for a specific date range
     */
    public function scopeForDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    /**
     * Scope to get data for today
     */
    public function scopeToday($query)
    {
        return $query->where('date', now()->toDateString());
    }

    /**
     * Scope to get data for the last N hours
     */
    public function scopeLastHours($query, int $hours)
    {
        $now = now();
        $startDate = $now->copy()->subHours($hours)->toDateString();
        $startHour = $now->copy()->subHours($hours)->hour;

        return $query->where(function ($q) use ($now, $startDate, $startHour) {
            $q->where(function ($inner) use ($startDate, $startHour) {
                $inner->where('date', $startDate)->where('hour', '>=', $startHour);
            })->orWhere('date', '>', $startDate);
        })->where(function ($q) use ($now) {
            $q->where('date', '<', $now->toDateString())
                ->orWhere(function ($inner) use ($now) {
                    $inner->where('date', $now->toDateString())
                        ->where('hour', '<=', $now->hour);
                });
        });
    }

    /**
     * Aggregate hourly data for real-time dashboard
     */
    public static function aggregateForPeriod(int $eventId, string $startDate, string $endDate): array
    {
        $data = static::where('event_id', $eventId)
            ->forDateRange($startDate, $endDate)
            ->get();

        return [
            'page_views' => $data->sum('page_views'),
            'unique_visitors' => $data->sum('unique_visitors'),
            'ticket_views' => $data->sum('ticket_views'),
            'add_to_carts' => $data->sum('add_to_carts'),
            'checkouts_started' => $data->sum('checkouts_started'),
            'purchases' => $data->sum('purchases'),
            'tickets_sold' => $data->sum('tickets_sold'),
            'revenue' => $data->sum('revenue_cents') / 100,
            'traffic_sources' => static::mergeJsonArrays($data->pluck('traffic_sources')->filter()->toArray()),
            'utm_campaigns' => static::mergeJsonArrays($data->pluck('utm_campaigns')->filter()->toArray()),
            'devices' => static::mergeJsonArrays($data->pluck('devices')->filter()->toArray()),
            'locations' => static::mergeJsonArrays($data->pluck('locations')->filter()->toArray()),
        ];
    }

    /**
     * Merge multiple JSON arrays by summing values
     */
    protected static function mergeJsonArrays(array $arrays): array
    {
        $result = [];
        foreach ($arrays as $array) {
            if (!is_array($array)) continue;
            foreach ($array as $key => $value) {
                if (is_numeric($value)) {
                    $result[$key] = ($result[$key] ?? 0) + $value;
                } elseif (is_array($value)) {
                    $result[$key] = static::mergeJsonArrays([$result[$key] ?? [], $value]);
                }
            }
        }
        arsort($result);
        return $result;
    }

    /**
     * Get chart data points for hourly view
     */
    public static function getHourlyChartData(int $eventId, string $date): array
    {
        $data = static::where('event_id', $eventId)
            ->where('date', $date)
            ->orderBy('hour')
            ->get()
            ->keyBy('hour');

        $chartData = [];
        for ($hour = 0; $hour < 24; $hour++) {
            $hourData = $data->get($hour);
            $chartData[] = [
                'hour' => sprintf('%02d:00', $hour),
                'page_views' => $hourData?->page_views ?? 0,
                'unique_visitors' => $hourData?->unique_visitors ?? 0,
                'purchases' => $hourData?->purchases ?? 0,
                'revenue' => $hourData?->revenue ?? 0,
            ];
        }

        return $chartData;
    }
}
