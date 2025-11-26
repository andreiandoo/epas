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
        // Virtual fields (handled by mutators)
        'price_max',
        'capacity',
        'is_active',
        'sale_starts_at',
        'sale_ends_at',
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
        'capacity',
        'is_active',
        'sale_starts_at',
        'sale_ends_at',
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
        return $this->price_cents ? $this->price_cents / 100 : 0;
    }

    public function getCapacityAttribute()
    {
        return $this->quota_total;
    }

    public function getSaleStartsAtAttribute()
    {
        return $this->sales_start_at;
    }

    public function getSaleEndsAtAttribute()
    {
        return $this->sales_end_at;
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
        // Ignore for now - we use price_max
    }

    public function setCapacityAttribute($value)
    {
        $this->attributes['quota_total'] = $value ?? 0;
    }

    public function setSaleStartsAtAttribute($value)
    {
        $this->attributes['sales_start_at'] = $value;
    }

    public function setSaleEndsAtAttribute($value)
    {
        $this->attributes['sales_end_at'] = $value;
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
