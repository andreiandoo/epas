<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketType extends Model
{
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
        // Virtual fields (handled by mutators)
        'price_max',
        'price',
        'capacity',
        'is_active',
    ];

    protected $casts = [
        'meta'           => 'array',
        'bulk_discounts' => 'array',
        'sales_start_at' => 'datetime',
        'sales_end_at'   => 'datetime',
    ];

    protected $appends = [
        'available_quantity',
        'price_max',
        'price',
        'capacity',
        'is_active',
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
}
