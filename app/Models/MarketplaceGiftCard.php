<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class MarketplaceGiftCard extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'marketplace_client_id',
        'code',
        'pin',
        'initial_amount',
        'balance',
        'currency',
        'purchaser_id',
        'purchaser_email',
        'purchaser_name',
        'purchase_order_id',
        'recipient_email',
        'recipient_name',
        'personal_message',
        'occasion',
        'delivery_method',
        'scheduled_delivery_at',
        'delivered_at',
        'is_delivered',
        'status',
        'activated_at',
        'expires_at',
        'first_used_at',
        'last_used_at',
        'recipient_customer_id',
        'claimed_at',
        'design_template',
        'design_options',
        'usage_count',
        'metadata',
    ];

    protected $casts = [
        'initial_amount' => 'decimal:2',
        'balance' => 'decimal:2',
        'design_options' => 'array',
        'metadata' => 'array',
        'is_delivered' => 'boolean',
        'scheduled_delivery_at' => 'datetime',
        'delivered_at' => 'datetime',
        'activated_at' => 'datetime',
        'expires_at' => 'datetime',
        'first_used_at' => 'datetime',
        'last_used_at' => 'datetime',
        'claimed_at' => 'datetime',
    ];

    /**
     * Status constants
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_USED = 'used';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_REVOKED = 'revoked';

    /**
     * Delivery methods
     */
    public const DELIVERY_EMAIL = 'email';
    public const DELIVERY_PRINT = 'print';

    /**
     * Occasion types
     */
    public const OCCASIONS = [
        'birthday' => 'Birthday',
        'anniversary' => 'Anniversary',
        'thank_you' => 'Thank You',
        'congratulations' => 'Congratulations',
        'christmas' => 'Christmas',
        'easter' => 'Easter',
        'valentine' => 'Valentine\'s Day',
        'mothers_day' => 'Mother\'s Day',
        'fathers_day' => 'Father\'s Day',
        'wedding' => 'Wedding',
        'graduation' => 'Graduation',
        'other' => 'Other',
    ];

    /**
     * Preset amounts
     */
    public const PRESET_AMOUNTS = [50, 100, 150, 200, 250, 300, 500];

    /**
     * Boot method
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->code)) {
                $model->code = static::generateUniqueCode($model->marketplace_client_id);
            }
            if (empty($model->pin)) {
                $model->pin = static::generatePin();
            }
            if (empty($model->balance)) {
                $model->balance = $model->initial_amount;
            }
        });
    }

    /**
     * Generate unique gift card code
     */
    public static function generateUniqueCode(int $marketplaceClientId): string
    {
        do {
            $code = 'GC-' . strtoupper(Str::random(4)) . '-' . strtoupper(Str::random(4)) . '-' . strtoupper(Str::random(4));
        } while (static::where('code', $code)->exists());

        return $code;
    }

    /**
     * Generate PIN
     */
    public static function generatePin(): string
    {
        return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    // =========================================
    // Relationships
    // =========================================

    public function marketplaceClient(): BelongsTo
    {
        return $this->belongsTo(MarketplaceClient::class);
    }

    public function purchaser(): BelongsTo
    {
        return $this->belongsTo(MarketplaceCustomer::class, 'purchaser_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(MarketplaceCustomer::class, 'recipient_customer_id');
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'purchase_order_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(MarketplaceGiftCardTransaction::class, 'marketplace_gift_card_id');
    }

    public function design(): BelongsTo
    {
        return $this->belongsTo(MarketplaceGiftCardDesign::class, 'design_template', 'slug');
    }

    // =========================================
    // Status Checks
    // =========================================

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isUsed(): bool
    {
        return $this->status === self::STATUS_USED;
    }

    public function isExpired(): bool
    {
        return $this->status === self::STATUS_EXPIRED || $this->expires_at->isPast();
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isRevoked(): bool
    {
        return $this->status === self::STATUS_REVOKED;
    }

    public function isUsable(): bool
    {
        return $this->isActive() && !$this->isExpired() && $this->balance > 0;
    }

    public function hasBalance(): bool
    {
        return $this->balance > 0;
    }

    // =========================================
    // Actions
    // =========================================

    /**
     * Activate the gift card
     */
    public function activate(): void
    {
        $this->update([
            'status' => self::STATUS_ACTIVE,
            'activated_at' => now(),
        ]);

        $this->recordTransaction(
            'activation',
            0,
            'Gift card activated'
        );
    }

    /**
     * Redeem amount from gift card
     */
    public function redeem(float $amount, ?Order $order = null, ?MarketplaceCustomer $customer = null): bool
    {
        if (!$this->isUsable()) {
            return false;
        }

        if ($amount > $this->balance) {
            return false;
        }

        $balanceBefore = $this->balance;
        $balanceAfter = $this->balance - $amount;

        $this->update([
            'balance' => $balanceAfter,
            'usage_count' => $this->usage_count + 1,
            'first_used_at' => $this->first_used_at ?? now(),
            'last_used_at' => now(),
            'status' => $balanceAfter <= 0 ? self::STATUS_USED : self::STATUS_ACTIVE,
        ]);

        $this->transactions()->create([
            'marketplace_client_id' => $this->marketplace_client_id,
            'type' => 'redemption',
            'amount' => -$amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'currency' => $this->currency,
            'order_id' => $order?->id,
            'performed_by_customer_id' => $customer?->id,
            'description' => $order ? "Redeemed for order #{$order->order_number}" : 'Redeemed',
        ]);

        return true;
    }

    /**
     * Refund amount back to gift card
     */
    public function refund(float $amount, ?Order $order = null, ?int $adminId = null, string $reason = null): bool
    {
        $balanceBefore = $this->balance;
        $balanceAfter = min($this->balance + $amount, $this->initial_amount);

        $this->update([
            'balance' => $balanceAfter,
            'status' => self::STATUS_ACTIVE,
        ]);

        $this->transactions()->create([
            'marketplace_client_id' => $this->marketplace_client_id,
            'type' => 'refund',
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'currency' => $this->currency,
            'order_id' => $order?->id,
            'performed_by_admin_id' => $adminId,
            'description' => $reason ?? 'Refund processed',
        ]);

        return true;
    }

    /**
     * Cancel the gift card
     */
    public function cancel(string $reason = null, ?int $adminId = null): void
    {
        $this->update([
            'status' => self::STATUS_CANCELLED,
        ]);

        $this->recordTransaction(
            'cancellation',
            0,
            $reason ?? 'Gift card cancelled',
            null,
            null,
            $adminId
        );
    }

    /**
     * Revoke the gift card (admin action)
     */
    public function revoke(string $reason, ?int $adminId = null): void
    {
        $this->update([
            'status' => self::STATUS_REVOKED,
        ]);

        $this->recordTransaction(
            'revocation',
            0,
            $reason,
            null,
            null,
            $adminId
        );
    }

    /**
     * Claim gift card (link to recipient account)
     */
    public function claim(MarketplaceCustomer $customer): bool
    {
        if ($this->recipient_customer_id !== null) {
            return false; // Already claimed
        }

        $this->update([
            'recipient_customer_id' => $customer->id,
            'claimed_at' => now(),
        ]);

        return true;
    }

    /**
     * Mark as delivered
     */
    public function markDelivered(): void
    {
        $this->update([
            'is_delivered' => true,
            'delivered_at' => now(),
            'status' => self::STATUS_ACTIVE,
        ]);
    }

    /**
     * Check and update expiry status
     */
    public function checkExpiry(): void
    {
        if ($this->isActive() && $this->expires_at->isPast()) {
            $this->update(['status' => self::STATUS_EXPIRED]);

            $this->recordTransaction(
                'expiry',
                0,
                'Gift card expired'
            );
        }
    }

    /**
     * Record a transaction
     */
    protected function recordTransaction(
        string $type,
        float $amount,
        string $description = null,
        ?Order $order = null,
        ?int $customerId = null,
        ?int $adminId = null
    ): void {
        $this->transactions()->create([
            'marketplace_client_id' => $this->marketplace_client_id,
            'type' => $type,
            'amount' => $amount,
            'balance_before' => $this->balance,
            'balance_after' => $this->balance,
            'currency' => $this->currency,
            'order_id' => $order?->id,
            'performed_by_customer_id' => $customerId,
            'performed_by_admin_id' => $adminId,
            'description' => $description,
        ]);
    }

    // =========================================
    // Scopes
    // =========================================

    public function scopeForMarketplace($query, int $marketplaceClientId)
    {
        return $query->where('marketplace_client_id', $marketplaceClientId);
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->where('expires_at', '>', now());
    }

    public function scopeUsable($query)
    {
        return $query->active()->where('balance', '>', 0);
    }

    public function scopeExpired($query)
    {
        return $query->where(function ($q) {
            $q->where('status', self::STATUS_EXPIRED)
                ->orWhere('expires_at', '<=', now());
        });
    }

    public function scopePendingDelivery($query)
    {
        return $query->where('is_delivered', false)
            ->where('delivery_method', 'email')
            ->where(function ($q) {
                $q->whereNull('scheduled_delivery_at')
                    ->orWhere('scheduled_delivery_at', '<=', now());
            });
    }

    public function scopeForRecipient($query, string $email)
    {
        return $query->where('recipient_email', $email);
    }

    // =========================================
    // Helpers
    // =========================================

    public function getFormattedCodeAttribute(): string
    {
        return $this->code;
    }

    public function getMaskedCodeAttribute(): string
    {
        $parts = explode('-', $this->code);
        if (count($parts) >= 4) {
            return $parts[0] . '-****-****-' . $parts[3];
        }
        return '****-****-****';
    }

    public function getFormattedBalanceAttribute(): string
    {
        return number_format($this->balance, 2) . ' ' . $this->currency;
    }

    public function getUsagePercentageAttribute(): float
    {
        if ($this->initial_amount <= 0) {
            return 0;
        }
        return round((($this->initial_amount - $this->balance) / $this->initial_amount) * 100, 1);
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'warning',
            self::STATUS_ACTIVE => 'success',
            self::STATUS_USED => 'info',
            self::STATUS_EXPIRED => 'gray',
            self::STATUS_CANCELLED => 'danger',
            self::STATUS_REVOKED => 'danger',
            default => 'gray',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Pending',
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_USED => 'Fully Used',
            self::STATUS_EXPIRED => 'Expired',
            self::STATUS_CANCELLED => 'Cancelled',
            self::STATUS_REVOKED => 'Revoked',
            default => ucfirst($this->status),
        };
    }

    public function getOccasionLabelAttribute(): ?string
    {
        return self::OCCASIONS[$this->occasion] ?? $this->occasion;
    }

    public function getDaysUntilExpiryAttribute(): int
    {
        return max(0, now()->diffInDays($this->expires_at, false));
    }

    /**
     * Check if card is expiring soon (within 30 days)
     */
    public function isExpiringSoon(): bool
    {
        return $this->days_until_expiry <= 30 && $this->days_until_expiry > 0;
    }
}
