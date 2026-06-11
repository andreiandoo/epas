<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ResaleListing extends Model
{
    use HasFactory;

    protected $table = 'resale_listings';

    protected $fillable = [
        'tenant_id', 'ticket_id', 'seller_customer_id',
        'original_price', 'asking_price', 'max_allowed_price',
        'status', 'listed_at', 'sold_at', 'buyer_customer_id',
        'platform_fee', 'seller_payout',
    ];

    protected $casts = [
        'original_price' => 'decimal:2',
        'asking_price' => 'decimal:2',
        'max_allowed_price' => 'decimal:2',
        'platform_fee' => 'decimal:2',
        'seller_payout' => 'decimal:2',
        'listed_at' => 'datetime',
        'sold_at' => 'datetime',
    ];

    const STATUS_ACTIVE = 'active';
    const STATUS_SOLD = 'sold';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_EXPIRED = 'expired';

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function ticket(): BelongsTo { return $this->belongsTo(Ticket::class); }
    public function seller(): BelongsTo { return $this->belongsTo(Customer::class, 'seller_customer_id'); }
    public function buyer(): BelongsTo { return $this->belongsTo(Customer::class, 'buyer_customer_id'); }
    public function transaction(): HasOne { return $this->hasOne(ResaleTransaction::class, 'listing_id'); }

    public function scopeForTenant($query, $tenantId) { return $query->where('tenant_id', $tenantId); }
    public function scopeActive($query) { return $query->where('status', self::STATUS_ACTIVE); }

    public function isActive(): bool { return $this->status === self::STATUS_ACTIVE; }
    public function isSold(): bool { return $this->status === self::STATUS_SOLD; }
}
