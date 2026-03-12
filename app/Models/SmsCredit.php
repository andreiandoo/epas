<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SmsCredit extends Model
{
    protected $fillable = [
        'creditable_type',
        'creditable_id',
        'credit_type',
        'credits_total',
        'credits_used',
        'price_per_sms',
        'currency',
        'amount_paid',
        'stripe_payment_id',
        'stripe_session_id',
        'purchased_at',
        'expires_at',
    ];

    protected $casts = [
        'credits_total' => 'integer',
        'credits_used' => 'integer',
        'price_per_sms' => 'decimal:4',
        'amount_paid' => 'decimal:2',
        'purchased_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function creditable(): MorphTo
    {
        return $this->morphTo();
    }

    public function getCreditsRemainingAttribute(): int
    {
        return max(0, $this->credits_total - $this->credits_used);
    }

    public static function getAvailableCredits($creditable, string $type): int
    {
        return static::where('creditable_type', get_class($creditable))
            ->where('creditable_id', $creditable->id)
            ->where('credit_type', $type)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->get()
            ->sum(fn ($credit) => $credit->credits_remaining);
    }

    public static function consumeCredit($creditable, string $type): bool
    {
        $credit = static::where('creditable_type', get_class($creditable))
            ->where('creditable_id', $creditable->id)
            ->where('credit_type', $type)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->whereRaw('credits_used < credits_total')
            ->orderBy('purchased_at')
            ->first();

        if (!$credit) {
            return false;
        }

        $credit->increment('credits_used');
        return true;
    }

    public static function purchaseCredits($creditable, string $type, int $quantity, float $pricePerSms, string $stripePaymentId = null, string $stripeSessionId = null): static
    {
        return static::create([
            'creditable_type' => get_class($creditable),
            'creditable_id' => $creditable->id,
            'credit_type' => $type,
            'credits_total' => $quantity,
            'credits_used' => 0,
            'price_per_sms' => $pricePerSms,
            'currency' => 'EUR',
            'amount_paid' => round($quantity * $pricePerSms, 2),
            'stripe_payment_id' => $stripePaymentId,
            'stripe_session_id' => $stripeSessionId,
            'purchased_at' => now(),
        ]);
    }
}
