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
        'is_independent_stock',
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
        // Entry ticket flag (for mobile POS filtering)
        'is_entry_ticket',
        // Admin internal notes
        'admin_notes',
        // Declarable flag - included in cerere avizare documents
        'is_declarable',
        // Subscription flag - true if this ticket type is an abonament
        'is_subscription',
        // Multiplier for quantity increment/decrement step
        'multiplier',
        // Series fields for ticket numbering
        'series_start',
        'series_end',
        'event_series',
        // Sort order for drag & drop reordering
        'sort_order',
        // Color for seating map visualization
        'color',
        // Single-day ticket: valid only on this date (for range events)
        'valid_date',
        // Ticket grouping (e.g. "Bilete Acces", "Camping", "Parcari")
        'ticket_group',
        // Perks/conditions list (JSON array of strings)
        'perks',
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
        'is_entry_ticket' => 'boolean',
        'is_declarable' => 'boolean',
        'is_subscription' => 'boolean',
        'is_independent_stock' => 'boolean',
        'valid_date'     => 'date',
        'min_per_order'    => 'integer',
        'max_per_order'    => 'integer',
        'commission_rate'  => 'decimal:2',
        'commission_fixed' => 'decimal:2',
        'perks' => 'array',
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
     * Check if this ticket type has seating assigned (rows or sections)
     */
    public function hasSeatingAssigned(): bool
    {
        return $this->seatingRows()->exists() || $this->seatingSections()->exists();
    }

    /**
     * Boot the model and add event listeners
     */
    protected static function boot()
    {
        parent::boot();

        // Auto-generate SKU if empty
        static::saving(function ($model) {
            if (empty($model->sku) && !empty($model->name)) {
                $model->sku = \Illuminate\Support\Str::upper(\Illuminate\Support\Str::slug($model->name, '-'));
            }
        });

        // Auto-generate series_start/series_end after create (needs ID)
        static::created(function ($model) {
            $needsUpdate = false;
            $event = $model->event;
            $eventSeries = $event?->event_series;
            $capacity = $model->quota_total;
            $identifier = $model->id;

            if ($eventSeries && $identifier) {
                if (empty($model->series_start)) {
                    $model->series_start = $eventSeries . '-' . $identifier . '-00001';
                    $needsUpdate = true;
                }
                if (empty($model->series_end) && $capacity && (int) $capacity > 0) {
                    $model->series_end = $eventSeries . '-' . $identifier . '-' . str_pad((int) $capacity, 5, '0', STR_PAD_LEFT);
                    $needsUpdate = true;
                }
            }

            if ($needsUpdate) {
                $model->saveQuietly();
            }
        });
    }

    /**
     * Scope: only ticket types that are currently active AND not past their active_until date
     */
    public function scopeEffectivelyActive($query)
    {
        return $query->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('active_until')
                  ->orWhere('active_until', '>', now('Europe/Bucharest'));
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
     * Accessor: intercept sale_price_cents reads to enforce sales_start_at / sales_end_at.
     * If the sale window hasn't opened yet or has closed, returns null.
     * All code that reads $tt->sale_price_cents will automatically respect sale dates.
     */
    public function getSalePriceCentsAttribute($value): ?int
    {
        if (! $value || $value <= 0) {
            return null;
        }

        $now = now('Europe/Bucharest');

        if ($this->attributes['sales_start_at'] ?? null) {
            $start = \Carbon\Carbon::parse($this->attributes['sales_start_at']);
            if ($now->lt($start)) {
                return null;
            }
        }

        if ($this->attributes['sales_end_at'] ?? null) {
            $end = \Carbon\Carbon::parse($this->attributes['sales_end_at']);
            if ($now->gt($end)) {
                return null;
            }
        }

        return (int) $value;
    }

    /**
     * Get raw sale_price_cents from DB without date enforcement (for admin forms).
     */
    public function getRawSalePriceCents(): ?int
    {
        return $this->attributes['sale_price_cents'] ?? null;
    }

    /**
     * Get the effective sale price in cents (alias for accessor, explicit call).
     * Returns null if no active sale.
     */
    public function getEffectiveSalePriceCents(): ?int
    {
        if (! $this->sale_price_cents || $this->sale_price_cents <= 0) {
            return null;
        }

        $now = now('Europe/Bucharest');

        if ($this->sales_start_at && $now->lt($this->sales_start_at)) {
            return null;
        }

        if ($this->sales_end_at && $now->gt($this->sales_end_at)) {
            return null;
        }

        return (int) $this->sale_price_cents;
    }

    /**
     * Get the effective display price (sale price if active, otherwise gross price)
     * This is the price that should be shown to customers
     */
    public function getDisplayPriceAttribute()
    {
        $effectiveSale = $this->getEffectiveSalePriceCents();
        if ($effectiveSale) {
            return $effectiveSale / 100;
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
        $this->attributes['quota_total'] = ($value === null || $value === '') ? -1 : (int) $value;
    }

    public function setIsActiveAttribute($value)
    {
        $this->attributes['status'] = $value ? 'active' : 'hidden';
    }

    // Accessor for available_quantity (computed from quota_total - quota_sold)
    // quota_total = -1 means unlimited
    protected function availableQuantity(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->quota_total < 0 ? PHP_INT_MAX : max(0, $this->quota_total - ($this->quota_sold ?? 0))
        );
    }

    /**
     * Check if this ticket type has unlimited stock (-1 = unlimited)
     */
    public function isUnlimited(): bool
    {
        return $this->quota_total < 0;
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
