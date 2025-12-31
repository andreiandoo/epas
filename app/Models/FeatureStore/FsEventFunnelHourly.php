<?php

namespace App\Models\FeatureStore;

use App\Models\Event;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FsEventFunnelHourly extends Model
{
    protected $table = 'fs_event_funnel_hourly';

    protected $fillable = [
        'tenant_id',
        'event_entity_id',
        'hour',
        'page_views',
        'ticket_selections',
        'add_to_carts',
        'checkout_starts',
        'payment_attempts',
        'orders_completed',
        'revenue_gross',
        'avg_time_to_cart_ms',
        'avg_time_to_checkout_ms',
        'avg_checkout_duration_ms',
    ];

    protected $casts = [
        'hour' => 'datetime',
        'page_views' => 'integer',
        'ticket_selections' => 'integer',
        'add_to_carts' => 'integer',
        'checkout_starts' => 'integer',
        'payment_attempts' => 'integer',
        'orders_completed' => 'integer',
        'revenue_gross' => 'decimal:2',
        'avg_time_to_cart_ms' => 'integer',
        'avg_time_to_checkout_ms' => 'integer',
        'avg_checkout_duration_ms' => 'integer',
    ];

    // Relationships

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function eventEntity(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_entity_id');
    }

    // Scopes

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeForEvent($query, int $eventEntityId)
    {
        return $query->where('event_entity_id', $eventEntityId);
    }

    public function scopeForHour($query, $hour)
    {
        return $query->where('hour', $hour);
    }

    public function scopeForHourRange($query, $start, $end)
    {
        return $query->whereBetween('hour', [$start, $end]);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('hour', today());
    }

    public function scopeLast24Hours($query)
    {
        return $query->where('hour', '>=', now()->subHours(24));
    }

    public function scopeLastDays($query, int $days)
    {
        return $query->where('hour', '>=', now()->subDays($days));
    }

    // Calculated metrics

    public function getViewToCartRateAttribute(): float
    {
        if ($this->page_views === 0) {
            return 0;
        }
        return $this->add_to_carts / $this->page_views;
    }

    public function getCartToCheckoutRateAttribute(): float
    {
        if ($this->add_to_carts === 0) {
            return 0;
        }
        return $this->checkout_starts / $this->add_to_carts;
    }

    public function getCheckoutConversionRateAttribute(): float
    {
        if ($this->checkout_starts === 0) {
            return 0;
        }
        return $this->orders_completed / $this->checkout_starts;
    }

    public function getOverallConversionRateAttribute(): float
    {
        if ($this->page_views === 0) {
            return 0;
        }
        return $this->orders_completed / $this->page_views;
    }

    public function getAvgOrderValueAttribute(): float
    {
        if ($this->orders_completed === 0) {
            return 0;
        }
        return $this->revenue_gross / $this->orders_completed;
    }

    // Static aggregations

    /**
     * Get aggregated funnel metrics for an event.
     */
    public static function aggregateForEvent(int $tenantId, int $eventEntityId): array
    {
        $data = static::forTenant($tenantId)
            ->forEvent($eventEntityId)
            ->get();

        return [
            'total_page_views' => $data->sum('page_views'),
            'total_ticket_selections' => $data->sum('ticket_selections'),
            'total_add_to_carts' => $data->sum('add_to_carts'),
            'total_checkout_starts' => $data->sum('checkout_starts'),
            'total_payment_attempts' => $data->sum('payment_attempts'),
            'total_orders_completed' => $data->sum('orders_completed'),
            'total_revenue_gross' => $data->sum('revenue_gross'),
            'avg_time_to_cart_ms' => $data->avg('avg_time_to_cart_ms'),
            'avg_time_to_checkout_ms' => $data->avg('avg_time_to_checkout_ms'),
            'avg_checkout_duration_ms' => $data->avg('avg_checkout_duration_ms'),
            'view_to_cart_rate' => $data->sum('page_views') > 0
                ? $data->sum('add_to_carts') / $data->sum('page_views')
                : 0,
            'overall_conversion_rate' => $data->sum('page_views') > 0
                ? $data->sum('orders_completed') / $data->sum('page_views')
                : 0,
        ];
    }

    /**
     * Get hourly data for charting.
     */
    public static function getHourlyData(int $tenantId, int $eventEntityId, int $hours = 24): array
    {
        return static::forTenant($tenantId)
            ->forEvent($eventEntityId)
            ->where('hour', '>=', now()->subHours($hours))
            ->orderBy('hour')
            ->get()
            ->map(fn($row) => [
                'hour' => $row->hour->format('Y-m-d H:00'),
                'page_views' => $row->page_views,
                'add_to_carts' => $row->add_to_carts,
                'orders_completed' => $row->orders_completed,
                'revenue_gross' => $row->revenue_gross,
            ])
            ->toArray();
    }
}
