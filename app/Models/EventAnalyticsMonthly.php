<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class EventAnalyticsMonthly extends Model
{
    protected $table = 'event_analytics_monthly';

    protected $fillable = [
        'event_id',
        'year',
        'month',
        'month_start',
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
        'conversion_rate',
        'cart_abandonment_rate',
        'avg_order_value_cents',
        'avg_time_on_page',
        'bounce_rate',
        'traffic_sources',
        'utm_campaigns',
        'devices',
        'top_locations',
        'ticket_breakdown',
        'milestone_performance',
        'revenue_change_pct',
        'visitors_change_pct',
        'conversion_change_pct',
    ];

    protected $casts = [
        'month_start' => 'date',
        'year' => 'integer',
        'month' => 'integer',
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
        'conversion_rate' => 'float',
        'cart_abandonment_rate' => 'float',
        'avg_order_value_cents' => 'float',
        'avg_time_on_page' => 'float',
        'bounce_rate' => 'float',
        'traffic_sources' => 'array',
        'utm_campaigns' => 'array',
        'devices' => 'array',
        'top_locations' => 'array',
        'ticket_breakdown' => 'array',
        'milestone_performance' => 'array',
        'revenue_change_pct' => 'float',
        'visitors_change_pct' => 'float',
        'conversion_change_pct' => 'float',
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
     * Get average order value in main currency unit
     */
    public function getAvgOrderValueAttribute(): float
    {
        return $this->avg_order_value_cents / 100;
    }

    /**
     * Get month end date
     */
    public function getMonthEndAttribute(): Carbon
    {
        return $this->month_start->copy()->endOfMonth();
    }

    /**
     * Get formatted month name
     */
    public function getMonthNameAttribute(): string
    {
        return $this->month_start->format('F Y');
    }

    /**
     * Scope for specific year
     */
    public function scopeForYear($query, int $year)
    {
        return $query->where('year', $year);
    }

    /**
     * Scope for last N months
     */
    public function scopeLastMonths($query, int $months)
    {
        $startDate = now()->startOfMonth()->subMonths($months - 1);
        return $query->where('month_start', '>=', $startDate);
    }

    /**
     * Get previous month data
     */
    public function getPreviousMonth(): ?self
    {
        return static::where('event_id', $this->event_id)
            ->where('month_start', $this->month_start->copy()->subMonth()->startOfMonth())
            ->first();
    }

    /**
     * Calculate and set change percentages
     */
    public function calculateChanges(): void
    {
        $previous = $this->getPreviousMonth();

        if (!$previous) {
            $this->revenue_change_pct = null;
            $this->visitors_change_pct = null;
            $this->conversion_change_pct = null;
            return;
        }

        $this->revenue_change_pct = $previous->revenue_cents > 0
            ? round((($this->revenue_cents - $previous->revenue_cents) / $previous->revenue_cents) * 100, 2)
            : ($this->revenue_cents > 0 ? 100 : 0);

        $this->visitors_change_pct = $previous->unique_visitors > 0
            ? round((($this->unique_visitors - $previous->unique_visitors) / $previous->unique_visitors) * 100, 2)
            : ($this->unique_visitors > 0 ? 100 : 0);

        $this->conversion_change_pct = $previous->conversion_rate > 0
            ? round((($this->conversion_rate - $previous->conversion_rate) / $previous->conversion_rate) * 100, 2)
            : ($this->conversion_rate > 0 ? 100 : 0);
    }

    /**
     * Get chart data for monthly trend
     */
    public static function getMonthlyTrendData(int $eventId, int $months = 12): array
    {
        return static::where('event_id', $eventId)
            ->lastMonths($months)
            ->orderBy('month_start')
            ->get()
            ->map(fn ($month) => [
                'month' => $month->month_name,
                'month_start' => $month->month_start->format('Y-m-d'),
                'page_views' => $month->page_views,
                'unique_visitors' => $month->unique_visitors,
                'purchases' => $month->purchases,
                'revenue' => $month->revenue,
                'conversion_rate' => $month->conversion_rate,
            ])
            ->toArray();
    }

    /**
     * Get year-to-date summary
     */
    public static function getYTDSummary(int $eventId): array
    {
        $year = now()->year;
        $data = static::where('event_id', $eventId)
            ->forYear($year)
            ->get();

        return [
            'page_views' => $data->sum('page_views'),
            'unique_visitors' => $data->sum('unique_visitors'),
            'purchases' => $data->sum('purchases'),
            'tickets_sold' => $data->sum('tickets_sold'),
            'revenue' => $data->sum('revenue_cents') / 100,
            'avg_conversion_rate' => $data->avg('conversion_rate') ?? 0,
        ];
    }
}
