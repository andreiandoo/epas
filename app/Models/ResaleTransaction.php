<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ResaleTransaction extends Model
{
    use HasFactory;

    protected $table = 'resale_transactions';

    protected $fillable = [
        'listing_id', 'order_id', 'buyer_customer_id', 'seller_customer_id',
        'sale_price', 'platform_fee', 'seller_payout', 'payout_status', 'completed_at',
    ];

    protected $casts = [
        'sale_price' => 'decimal:2',
        'platform_fee' => 'decimal:2',
        'seller_payout' => 'decimal:2',
        'completed_at' => 'datetime',
    ];

    const PAYOUT_PENDING = 'pending';
    const PAYOUT_PROCESSING = 'processing';
    const PAYOUT_COMPLETED = 'completed';
    const PAYOUT_FAILED = 'failed';

    public function listing(): BelongsTo { return $this->belongsTo(ResaleListing::class, 'listing_id'); }
    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function buyer(): BelongsTo { return $this->belongsTo(Customer::class, 'buyer_customer_id'); }
    public function seller(): BelongsTo { return $this->belongsTo(Customer::class, 'seller_customer_id'); }

    public function scopePending($query) { return $query->where('payout_status', self::PAYOUT_PENDING); }
    public function isPaid(): bool { return $this->payout_status === self::PAYOUT_COMPLETED; }
}
