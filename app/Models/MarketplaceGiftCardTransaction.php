<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceGiftCardTransaction extends Model
{
    protected $fillable = [
        'marketplace_gift_card_id',
        'marketplace_client_id',
        'type',
        'amount',
        'balance_before',
        'balance_after',
        'currency',
        'order_id',
        'performed_by_customer_id',
        'performed_by_admin_id',
        'description',
        'reference',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'metadata' => 'array',
    ];

    /**
     * Transaction types
     */
    public const TYPE_PURCHASE = 'purchase';
    public const TYPE_ACTIVATION = 'activation';
    public const TYPE_REDEMPTION = 'redemption';
    public const TYPE_REFUND = 'refund';
    public const TYPE_ADJUSTMENT = 'adjustment';
    public const TYPE_EXPIRY = 'expiry';
    public const TYPE_CANCELLATION = 'cancellation';
    public const TYPE_REVOCATION = 'revocation';

    // =========================================
    // Relationships
    // =========================================

    public function giftCard(): BelongsTo
    {
        return $this->belongsTo(MarketplaceGiftCard::class, 'marketplace_gift_card_id');
    }

    public function marketplaceClient(): BelongsTo
    {
        return $this->belongsTo(MarketplaceClient::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function performedByCustomer(): BelongsTo
    {
        return $this->belongsTo(MarketplaceCustomer::class, 'performed_by_customer_id');
    }

    public function performedByAdmin(): BelongsTo
    {
        return $this->belongsTo(MarketplaceAdmin::class, 'performed_by_admin_id');
    }

    // =========================================
    // Helpers
    // =========================================

    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_PURCHASE => 'Purchase',
            self::TYPE_ACTIVATION => 'Activation',
            self::TYPE_REDEMPTION => 'Redemption',
            self::TYPE_REFUND => 'Refund',
            self::TYPE_ADJUSTMENT => 'Adjustment',
            self::TYPE_EXPIRY => 'Expiry',
            self::TYPE_CANCELLATION => 'Cancellation',
            self::TYPE_REVOCATION => 'Revocation',
            default => ucfirst($this->type),
        };
    }

    public function getTypeColorAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_PURCHASE => 'success',
            self::TYPE_ACTIVATION => 'info',
            self::TYPE_REDEMPTION => 'warning',
            self::TYPE_REFUND => 'success',
            self::TYPE_ADJUSTMENT => 'info',
            self::TYPE_EXPIRY => 'gray',
            self::TYPE_CANCELLATION => 'danger',
            self::TYPE_REVOCATION => 'danger',
            default => 'gray',
        };
    }

    public function getFormattedAmountAttribute(): string
    {
        $prefix = $this->amount >= 0 ? '+' : '';
        return $prefix . number_format($this->amount, 2) . ' ' . $this->currency;
    }

    public function getPerformedByNameAttribute(): ?string
    {
        if ($this->performedByAdmin) {
            return $this->performedByAdmin->name . ' (Admin)';
        }
        if ($this->performedByCustomer) {
            return $this->performedByCustomer->full_name;
        }
        return 'System';
    }

    public function isCredit(): bool
    {
        return $this->amount > 0;
    }

    public function isDebit(): bool
    {
        return $this->amount < 0;
    }

    // =========================================
    // Scopes
    // =========================================

    public function scopeForGiftCard($query, int $giftCardId)
    {
        return $query->where('marketplace_gift_card_id', $giftCardId);
    }

    public function scopeRedemptions($query)
    {
        return $query->where('type', self::TYPE_REDEMPTION);
    }

    public function scopeRefunds($query)
    {
        return $query->where('type', self::TYPE_REFUND);
    }
}
