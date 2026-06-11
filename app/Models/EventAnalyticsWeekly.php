<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class EventAnalyticsWeekly extends Model
{
    protected $table = 'event_analytics_weekly';

    protected $fillable = [
        'event_id',
        'week_start',
        'year',
        'week_number',
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
        'revenue_change_pct',
        'visitors_change_pct',
        'conversion_change_pct',
    ];

    protected $casts = [
        'week_start' => 'date',
        'year' => 'integer',
        'week_number' => 'integer',
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
     * Get week end date
     */
    public function getWeekEndAttribute(): Carbon
    {
        return $this->week_start->copy()->addDays(6);
    }

    /**
     * Get formatted week range
     */
    public function getWeekRangeAttribute(): string
    {
        return $this->week_start->format('M d') . ' - ' . $this->week_end->format('M d, Y');
    }

    /**
     * Scope for specific year
     */
    public function scopeForYear($query, int $year)
    {
        return $query->where('year', $year);
    }

    /**
     * Scope for last N weeks
     */
    public function scopeLastWeeks($query, int $weeks)
    {
        $startDate = now()->startOfWeek()->subWeeks($weeks - 1);
        return $query->where('week_start', '>=', $startDate);
    }

    /**
     * Get week-over-week comparison
     */
    public function getPreviousWeek(): ?self
    {
        return static::where('event_id', $this->event_id)
            ->where('week_start', $this->week_start->copy()->subWeek())
            ->first();
    }

    /**
     * Calculate and set change percentages
     */
    public function calculateChanges(): void
    {
        $previous = $this->getPreviousWeek();

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
     * Get chart data for weekly trend
     */
    public static function getWeeklyTrendData(int $eventId, int $weeks = 12): array
    {
        return static::where('event_id', $eventId)
            ->lastWeeks($weeks)
            ->orderBy('week_start')
            ->get()
            ->map(fn ($week) => [
                'week' => $week->week_range,
                'week_start' => $week->week_start->format('Y-m-d'),
                'page_views' => $week->page_views,
                'unique_visitors' => $week->unique_visitors,
                'purchases' => $week->purchases,
                'revenue' => $week->revenue,
                'conversion_rate' => $week->conversion_rate,
            ])
            ->toArray();
    }
}
