<?php

namespace App\Models\Shop;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopGiftCardTransaction extends Model
{
    use HasUuids;

    protected $table = 'shop_gift_card_transactions';

    protected $fillable = [
        'gift_card_id',
        'type',
        'amount_cents',
        'balance_after_cents',
        'order_id',
        'description',
    ];

    protected $casts = [
        'amount_cents' => 'integer',
        'balance_after_cents' => 'integer',
    ];

    // Relationships

    public function giftCard(): BelongsTo
    {
        return $this->belongsTo(ShopGiftCard::class, 'gift_card_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(ShopOrder::class, 'order_id');
    }

    // Accessors

    public function getAmountAttribute(): float
    {
        return $this->amount_cents / 100;
    }

    public function getBalanceAfterAttribute(): float
    {
        return $this->balance_after_cents / 100;
    }

    public function isDebit(): bool
    {
        return $this->type === 'debit';
    }

    public function isCredit(): bool
    {
        return $this->type === 'credit';
    }

    public function isRefund(): bool
    {
        return $this->type === 'refund';
    }

    public function getSignedAmount(): float
    {
        return $this->isDebit() ? -$this->amount : $this->amount;
    }
}
