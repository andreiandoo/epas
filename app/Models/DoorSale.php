<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DoorSale extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id', 'event_id', 'user_id', 'order_id',
        'customer_email', 'customer_name',
        'subtotal', 'platform_fee', 'payment_processing_fee', 'total', 'currency',
        'payment_method', 'payment_gateway', 'gateway_transaction_id', 'gateway_payment_intent_id',
        'status', 'refunded_amount', 'failure_reason', 'device_id', 'meta',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'platform_fee' => 'decimal:2',
        'payment_processing_fee' => 'decimal:2',
        'total' => 'decimal:2',
        'refunded_amount' => 'decimal:2',
        'meta' => 'array',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_REFUNDED = 'refunded';

    const PAYMENT_CARD_TAP = 'card_tap';
    const PAYMENT_APPLE_PAY = 'apple_pay';
    const PAYMENT_GOOGLE_PAY = 'google_pay';

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function event(): BelongsTo { return $this->belongsTo(Event::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function items(): HasMany { return $this->hasMany(DoorSaleItem::class); }
    public function platformFee(): HasOne { return $this->hasOne(DoorSalePlatformFee::class); }

    public function scopeForTenant($query, $tenantId) { return $query->where('tenant_id', $tenantId); }
    public function scopeForEvent($query, $eventId) { return $query->where('event_id', $eventId); }
    public function scopeCompleted($query) { return $query->where('status', self::STATUS_COMPLETED); }
    public function scopeToday($query) { return $query->whereDate('created_at', today()); }

    public function getTotalTickets(): int
    {
        return $this->items->sum('quantity');
    }

    public function canRefund(): bool
    {
        return $this->status === self::STATUS_COMPLETED && $this->refunded_amount < $this->total;
    }
}
