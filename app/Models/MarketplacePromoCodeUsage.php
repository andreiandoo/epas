<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplacePromoCodeUsage extends Model
{
    use HasFactory;

    protected $table = 'marketplace_promo_code_usage';

    protected $fillable = [
        'promo_code_id',
        'order_id',
        'marketplace_customer_id',
        'customer_email',
        'discount_applied',
        'order_total',
        'ip_address',
    ];

    protected $casts = [
        'discount_applied' => 'decimal:2',
        'order_total' => 'decimal:2',
    ];

    // =========================================
    // Relationships
    // =========================================

    public function promoCode(): BelongsTo
    {
        return $this->belongsTo(MarketplaceOrganizerPromoCode::class, 'promo_code_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(MarketplaceCustomer::class, 'marketplace_customer_id');
    }
}
