<?php

namespace App\Models;

use App\Models\Seating\SeatingSection;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class TicketType extends Model
{
    use LogsActivity;
    protected $fillable = [
        'event_id',
        'name',
        'sku',
        'description',
        'currency',
        'quota_sold',
        // Order quantity limits
        'min_per_order',
        'max_per_order',
        // Per-ticket commission settings (override organizer/marketplace defaults)
        'commission_type',   // null (inherit), 'percentage', 'fixed', or 'both'
        'commission_rate',   // Percentage rate (0-100)
        'commission_fixed',  // Fixed amount per ticket
        'commission_mode',   // null (inherit), 'included', or 'added_on_top'
        'bulk_discounts',
        'meta',
        // Real database columns
        'sales_start_at',
        'sales_end_at',
        'scheduled_at',
        'autostart_when_previous_sold_out',
        // Sale stock - limit tickets sold at sale price
        'sale_stock',
        'sale_stock_sold',
        // Active until - auto mark as soldout when date is reached
        'active_until',
        // Virtual fields (handled by mutators)
        'price_max',
        'price',
        'capacity',
        'is_active',
        // Refund eligibility
        'is_refundable',
        // Series fields for ticket numbering
        'series_start',
        'series_end',
        'event_series',
        // Sort order for drag & drop reordering
        'sort_order',
        // Color for seating map visualization
        'color',
    ];

    protected $casts = [
        'meta'           => 'array',
        'bulk_discounts' => 'array',
        'sales_start_at' => 'datetime',
        'sales_end_at'   => 'datetime',
        'scheduled_at'   => 'datetime',
        'active_until'   => 'datetime',
        'autostart_when_previous_sold_out' => 'boolean',
        'is_refundable'  => 'boolean',
        'min_per_order'    => 'integer',
        'max_per_order'    => 'integer',
        'commission_rate'  => 'decimal:2',
        'commission_fixed' => 'decimal:2',
    ];

    protected $appends = [
        'available_quantity',
        'price_max',
        'price',
        'capacity',
        'is_active',
        'display_price',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /**
     * Seating sections assigned to this ticket type
     */
    public function seatingSections(): BelongsToMany
    {
        return $this->belongsToMany(
            SeatingSection::class,
            'ticket_type_seating_sections',
            'ticket_type_id',
            'seating_section_id'
        )->withTimestamps();
    }

    public function seatingRows(): BelongsToMany
    {
        return $this->belongsToMany(
            \App\Models\Seating\SeatingRow::class,
            'ticket_type_seating_rows',
            'ticket_type_id',
            'seating_row_id'
        )->withTimestamps();
    }

    /**
     * Get total capacity from assigned seating sections
     */
    public function getSeatingCapacityAttribute(): int
    {
        return $this->seatingSections->sum(function ($section) {
            return $section->total_seats;
        });
    }

    /**
     * Check if this ticket type has seating sections assigned
     */
    public function hasSeatingAssigned(): bool
    {
        return $this->seatingSections()->exists();
    }

    /**
     * Boot the model and add event listeners
     */
    protected static function boot()
    {
        parent::boot();

        // Transform data before saving
        static::saving(function ($model) {
            \Log::info('TicketType saving', [
                'id' => $model->id,
                'attributes' => $model->attributes,
                'original' => $model->getOriginal(),
            ]);
        });
    }

    // Getters
    public function getPriceMaxAttribute()
    {
        return $this->price_cents ? $this->price_cents / 100 : 0;
    }

    public function getPriceAttribute()
    {
        // Return sale_price if set, otherwise null
        return $this->sale_price_cents ? $this->sale_price_cents / 100 : null;
    }

    public function getCapacityAttribute()
    {
        return $this->quota_total;
    }

    public function getIsActiveAttribute()
    {
        return $this->status === 'active';
    }

    /**
     * Get the effective display price (sale price if available, otherwise gross price)
     * This is the price that should be shown to customers
     */
    public function getDisplayPriceAttribute()
    {
        // If sale price exists, use it; otherwise use gross price
        if ($this->sale_price_cents !== null && $this->sale_price_cents > 0) {
            return $this->sale_price_cents / 100;
        }
        return $this->price_cents ? $this->price_cents / 100 : 0;
    }

    /**
     * Get effective commission settings for this ticket type.
     * Falls back to event > organizer > marketplace defaults if not set.
     *
     * @param float|null $defaultRate Default percentage rate from organizer/marketplace
     * @param string|null $defaultMode Default commission mode from organizer/marketplace
     * @return array{type: string, rate: float, fixed: float, mode: string}
     */
    public function getEffectiveCommission(?float $defaultRate = 5.0, ?string $defaultMode = 'included'): array
    {
        // If ticket type has its own commission settings, use them
        if ($this->commission_type) {
            return [
                'type' => $this->commission_type,
                'rate' => (float) ($this->commission_rate ?? 0),
                'fixed' => (float) ($this->commission_fixed ?? 0),
                'mode' => $this->commission_mode ?? $defaultMode ?? 'included',
            ];
        }

        // Fall back to defaults (percentage type)
        return [
            'type' => 'percentage',
            'rate' => $defaultRate ?? 5.0,
            'fixed' => 0,
            'mode' => $defaultMode ?? 'included',
        ];
    }

    /**
     * Calculate commission amount for a given base price
     *
     * @param float $basePrice The ticket base price
     * @param float|null $defaultRate Default percentage rate
     * @param string|null $defaultMode Default commission mode
     * @return float Commission amount
     */
    public function calculateCommission(float $basePrice, ?float $defaultRate = 5.0, ?string $defaultMode = 'included'): float
    {
        $commission = $this->getEffectiveCommission($defaultRate, $defaultMode);
        $amount = 0;

        switch ($commission['type']) {
            case 'percentage':
                $amount = $basePrice * ($commission['rate'] / 100);
                break;
            case 'fixed':
                $amount = $commission['fixed'];
                break;
            case 'both':
                $amount = ($basePrice * ($commission['rate'] / 100)) + $commission['fixed'];
                break;
        }

        return round($amount, 2);
    }

    // Setters
    public function setPriceMaxAttribute($value)
    {
        $this->attributes['price_cents'] = $value ? (int)($value * 100) : 0;
    }

    public function setPriceAttribute($value)
    {
        // Save to sale_price_cents (can be null for no sale)
        $this->attributes['sale_price_cents'] = $value ? (int)($value * 100) : null;
    }

    public function setCapacityAttribute($value)
    {
        $this->attributes['quota_total'] = $value ?? 0;
    }

    public function setIsActiveAttribute($value)
    {
        $this->attributes['status'] = $value ? 'active' : 'hidden';
    }

    // Accessor for available_quantity (computed from quota_total - quota_sold)
    protected function availableQuantity(): Attribute
    {
        return Attribute::make(
            get: fn () => max(0, ($this->quota_total ?? 0) - ($this->quota_sold ?? 0))
        );
    }

    /**
     * Configure activity logging
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'price_cents', 'quota_total', 'status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "Ticket Type {$eventName}")
            ->useLogName('tenant');
    }

    /**
     * Add tenant_id to activity properties for scoping (via event relationship)
     */
    public function tapActivity(\Spatie\Activitylog\Contracts\Activity $activity, string $eventName)
    {
        $tenantId = $this->event?->tenant_id;
        if ($tenantId) {
            $activity->properties = $activity->properties->put('tenant_id', $tenantId);
        }
    }
}
