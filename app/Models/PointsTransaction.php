<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PointsTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'order_id',
        'points',
        'type',
        'description',
        'balance_after',
    ];

    protected $casts = [
        'points' => 'integer',
        'balance_after' => 'integer',
    ];

    // Transaction types
    public const TYPE_EARNED = 'earned';
    public const TYPE_SPENT = 'spent';
    public const TYPE_BONUS = 'bonus';
    public const TYPE_ADJUSTMENT = 'adjustment';
    public const TYPE_REFERRAL = 'referral';
    public const TYPE_EXPIRED = 'expired';

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    // Scope for positive transactions (earnings)
    public function scopeEarnings($query)
    {
        return $query->where('points', '>', 0);
    }

    // Scope for negative transactions (spending)
    public function scopeSpendings($query)
    {
        return $query->where('points', '<', 0);
    }

    // Scope by type
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }
}
