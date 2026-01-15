<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'bulk_discounts',
        'meta',
        // Real database columns
        'sales_start_at',
        'sales_end_at',
        'scheduled_at',
        'autostart_when_previous_sold_out',
        // Virtual fields (handled by mutators)
        'price_max',
        'price',
        'capacity',
        'is_active',
        // Series fields for ticket numbering
        'series_start',
        'series_end',
        'event_series',
    ];

    protected $casts = [
        'meta'           => 'array',
        'bulk_discounts' => 'array',
        'sales_start_at' => 'datetime',
        'sales_end_at'   => 'datetime',
        'scheduled_at'   => 'datetime',
        'autostart_when_previous_sold_out' => 'boolean',
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
