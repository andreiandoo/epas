<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'marketplace_client_id',
        'marketplace_organizer_id',
        'type',
        'amount',
        'currency',
        'balance_after',
        'order_id',
        'marketplace_payout_id',
        'description',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'metadata' => 'array',
    ];

    // Transaction types
    const TYPE_SALE = 'sale';
    const TYPE_COMMISSION = 'commission';
    const TYPE_REFUND = 'refund';
    const TYPE_CHARGEBACK = 'chargeback';
    const TYPE_ADJUSTMENT = 'adjustment';
    const TYPE_PAYOUT = 'payout';
    const TYPE_PAYOUT_REVERSAL = 'payout_reversal';

    // =========================================
    // Relationships
    // =========================================

    public function marketplaceClient(): BelongsTo
    {
        return $this->belongsTo(MarketplaceClient::class);
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(MarketplaceOrganizer::class, 'marketplace_organizer_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function payout(): BelongsTo
    {
        return $this->belongsTo(MarketplacePayout::class, 'marketplace_payout_id');
    }

    // =========================================
    // Helpers
    // =========================================

    public function isCredit(): bool
    {
        return $this->amount > 0;
    }

    public function isDebit(): bool
    {
        return $this->amount < 0;
    }

    public function getTypeLabel(): string
    {
        return match ($this->type) {
            self::TYPE_SALE => 'Sale',
            self::TYPE_COMMISSION => 'Commission',
            self::TYPE_REFUND => 'Refund',
            self::TYPE_CHARGEBACK => 'Chargeback',
            self::TYPE_ADJUSTMENT => 'Adjustment',
            self::TYPE_PAYOUT => 'Payout',
            self::TYPE_PAYOUT_REVERSAL => 'Payout Reversal',
            default => ucfirst($this->type),
        };
    }

    public function getTypeColor(): string
    {
        return match ($this->type) {
            self::TYPE_SALE => 'success',
            self::TYPE_COMMISSION => 'warning',
            self::TYPE_REFUND => 'danger',
            self::TYPE_CHARGEBACK => 'danger',
            self::TYPE_ADJUSTMENT => 'info',
            self::TYPE_PAYOUT => 'primary',
            self::TYPE_PAYOUT_REVERSAL => 'warning',
            default => 'gray',
        };
    }

    // =========================================
    // Factory Methods
    // =========================================

    /**
     * Record a sale transaction
     */
    public static function recordSale(
        int $clientId,
        int $organizerId,
        float $grossAmount,
        float $commissionAmount,
        int $orderId,
        string $currency = 'RON'
    ): array {
        $organizer = MarketplaceOrganizer::findOrFail($organizerId);
        $netAmount = $grossAmount - $commissionAmount;

        // Record sale credit
        $saleTx = static::create([
            'marketplace_client_id' => $clientId,
            'marketplace_organizer_id' => $organizerId,
            'type' => self::TYPE_SALE,
            'amount' => $grossAmount,
            'currency' => $currency,
            'balance_after' => $organizer->available_balance + $grossAmount,
            'order_id' => $orderId,
            'description' => "Sale from order #{$orderId}",
        ]);

        // Update organizer balance
        $organizer->increment('available_balance', $grossAmount);

        // Record commission debit
        $commissionTx = static::create([
            'marketplace_client_id' => $clientId,
            'marketplace_organizer_id' => $organizerId,
            'type' => self::TYPE_COMMISSION,
            'amount' => -$commissionAmount,
            'currency' => $currency,
            'balance_after' => $organizer->available_balance - $commissionAmount,
            'order_id' => $orderId,
            'description' => "Commission deducted from order #{$orderId}",
        ]);

        // Update organizer balance
        $organizer->decrement('available_balance', $commissionAmount);

        return [$saleTx, $commissionTx];
    }

    /**
     * Record a refund transaction
     */
    public static function recordRefund(
        int $clientId,
        int $organizerId,
        float $refundAmount,
        float $commissionRefund,
        int $orderId,
        string $currency = 'RON'
    ): array {
        $organizer = MarketplaceOrganizer::findOrFail($organizerId);

        // Record refund debit
        $refundTx = static::create([
            'marketplace_client_id' => $clientId,
            'marketplace_organizer_id' => $organizerId,
            'type' => self::TYPE_REFUND,
            'amount' => -$refundAmount,
            'currency' => $currency,
            'balance_after' => $organizer->available_balance - $refundAmount,
            'order_id' => $orderId,
            'description' => "Refund for order #{$orderId}",
        ]);

        $organizer->decrement('available_balance', $refundAmount);

        // Record commission refund credit (if applicable)
        $commissionTx = null;
        if ($commissionRefund > 0) {
            $commissionTx = static::create([
                'marketplace_client_id' => $clientId,
                'marketplace_organizer_id' => $organizerId,
                'type' => self::TYPE_COMMISSION,
                'amount' => $commissionRefund,
                'currency' => $currency,
                'balance_after' => $organizer->available_balance + $commissionRefund,
                'order_id' => $orderId,
                'description' => "Commission refunded for order #{$orderId}",
            ]);

            $organizer->increment('available_balance', $commissionRefund);
        }

        return [$refundTx, $commissionTx];
    }

    /**
     * Record an adjustment transaction
     */
    public static function recordAdjustment(
        int $clientId,
        int $organizerId,
        float $amount,
        string $description,
        ?array $metadata = null,
        string $currency = 'RON'
    ): self {
        $organizer = MarketplaceOrganizer::findOrFail($organizerId);

        $tx = static::create([
            'marketplace_client_id' => $clientId,
            'marketplace_organizer_id' => $organizerId,
            'type' => self::TYPE_ADJUSTMENT,
            'amount' => $amount,
            'currency' => $currency,
            'balance_after' => $organizer->available_balance + $amount,
            'description' => $description,
            'metadata' => $metadata,
        ]);

        if ($amount > 0) {
            $organizer->increment('available_balance', $amount);
        } else {
            $organizer->decrement('available_balance', abs($amount));
        }

        return $tx;
    }
}
