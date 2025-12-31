<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderReminder extends Model
{
    protected $fillable = [
        'order_id',
        'type',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Check if a reminder of this type was already sent for the order
     */
    public static function wasSent(int $orderId, string $type): bool
    {
        return static::where('order_id', $orderId)
            ->where('type', $type)
            ->exists();
    }

    /**
     * Record that a reminder was sent
     */
    public static function recordSent(int $orderId, string $type): static
    {
        return static::create([
            'order_id' => $orderId,
            'type' => $type,
            'sent_at' => now(),
        ]);
    }
}
