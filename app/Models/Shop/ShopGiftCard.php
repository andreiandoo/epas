<?php

namespace App\Models\Shop;

use App\Models\Tenant;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class ShopGiftCard extends Model
{
    use HasUuids;

    protected $table = 'shop_gift_cards';

    protected $fillable = [
        'tenant_id',
        'code',
        'initial_balance_cents',
        'current_balance_cents',
        'currency',
        'status',
        'purchaser_customer_id',
        'purchase_order_id',
        'purchaser_email',
        'recipient_email',
        'recipient_name',
        'message',
        'is_sent',
        'sent_at',
        'valid_from',
        'expires_at',
    ];

    protected $casts = [
        'initial_balance_cents' => 'integer',
        'current_balance_cents' => 'integer',
        'is_sent' => 'boolean',
        'sent_at' => 'datetime',
        'valid_from' => 'datetime',
        'expires_at' => 'datetime',
    ];

    // Relationships

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function purchaser(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'purchaser_customer_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(ShopGiftCardTransaction::class, 'gift_card_id')->orderBy('created_at', 'desc');
    }

    // Scopes

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeUsable(Builder $query): Builder
    {
        return $query->where('status', 'active')
            ->where('current_balance_cents', '>', 0)
            ->where(function ($q) {
                $q->whereNull('valid_from')
                    ->orWhere('valid_from', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    // Boot

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($card) {
            if (!$card->code) {
                $card->code = static::generateCode();
            }
            if (!$card->current_balance_cents) {
                $card->current_balance_cents = $card->initial_balance_cents;
            }
        });
    }

    // Methods

    public static function generateCode(): string
    {
        do {
            $code = strtoupper(Str::random(4) . '-' . Str::random(4) . '-' . Str::random(4));
        } while (static::where('code', $code)->exists());

        return $code;
    }

    public function isUsable(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        if ($this->current_balance_cents <= 0) {
            return false;
        }

        if ($this->valid_from && $this->valid_from->isFuture()) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function getInitialBalanceAttribute(): float
    {
        return $this->initial_balance_cents / 100;
    }

    public function getCurrentBalanceAttribute(): float
    {
        return $this->current_balance_cents / 100;
    }

    public function debit(int $amountCents, ?string $orderId = null, ?string $description = null): ShopGiftCardTransaction
    {
        if ($amountCents > $this->current_balance_cents) {
            throw new \Exception('Insufficient gift card balance');
        }

        $this->decrement('current_balance_cents', $amountCents);

        if ($this->current_balance_cents <= 0) {
            $this->update(['status' => 'depleted']);
        }

        return $this->transactions()->create([
            'type' => 'debit',
            'amount_cents' => $amountCents,
            'balance_after_cents' => $this->current_balance_cents,
            'order_id' => $orderId,
            'description' => $description ?? 'Purchase',
        ]);
    }

    public function credit(int $amountCents, ?string $orderId = null, ?string $description = null): ShopGiftCardTransaction
    {
        $this->increment('current_balance_cents', $amountCents);

        if ($this->status === 'depleted') {
            $this->update(['status' => 'active']);
        }

        return $this->transactions()->create([
            'type' => 'credit',
            'amount_cents' => $amountCents,
            'balance_after_cents' => $this->current_balance_cents,
            'order_id' => $orderId,
            'description' => $description ?? 'Credit',
        ]);
    }

    public function refund(int $amountCents, string $orderId, ?string $description = null): ShopGiftCardTransaction
    {
        return $this->credit($amountCents, $orderId, $description ?? 'Refund');
    }

    public function markAsSent(): void
    {
        $this->update([
            'is_sent' => true,
            'sent_at' => now(),
        ]);
    }

    public function disable(): void
    {
        $this->update(['status' => 'disabled']);
    }

    // Static

    public static function findByCode(int $tenantId, string $code): ?self
    {
        return static::where('tenant_id', $tenantId)
            ->where('code', strtoupper($code))
            ->first();
    }
}
