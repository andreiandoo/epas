<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Secure, expiring, single-purpose payment link.
 *
 * Powers installment 3DS/manual payment, BNPL pay-by-link, and the
 * "someone else pays" delegated-payment flow.
 */
class PaymentLink extends Model
{
    public const PURPOSE_INSTALLMENT = 'installment';
    public const PURPOSE_BNPL = 'bnpl';
    public const PURPOSE_DELEGATED = 'delegated_pay';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAID = 'paid';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'token',
        'purpose',
        'marketplace_client_id',
        'tenant_id',
        'order_id',
        'installment_payment_id',
        'amount_cents',
        'currency',
        'status',
        'expires_at',
        'payer_email',
        'payer_name',
        'created_by_customer_id',
        'paid_at',
        'payment_reference',
        'metadata',
    ];

    protected $casts = [
        'amount_cents' => 'integer',
        'expires_at' => 'datetime',
        'paid_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (PaymentLink $link) {
            if (empty($link->token)) {
                $link->token = self::generateToken();
            }
        });
    }

    public static function generateToken(): string
    {
        do {
            $token = Str::random(48);
        } while (self::where('token', $token)->exists());

        return $token;
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function installmentPayment(): BelongsTo
    {
        return $this->belongsTo(InstallmentPayment::class);
    }

    public function marketplaceClient(): BelongsTo
    {
        return $this->belongsTo(MarketplaceClient::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE && ! $this->isExpired();
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function getAmount(): float
    {
        return $this->amount_cents / 100;
    }

    public function markPaid(?string $reference = null): void
    {
        $this->update([
            'status' => self::STATUS_PAID,
            'paid_at' => now(),
            'payment_reference' => $reference,
        ]);
    }

    public function markExpired(): void
    {
        if ($this->status === self::STATUS_ACTIVE) {
            $this->update(['status' => self::STATUS_EXPIRED]);
        }
    }
}
