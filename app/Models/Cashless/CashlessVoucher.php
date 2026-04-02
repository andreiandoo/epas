<?php

namespace App\Models\Cashless;

use App\Models\FestivalEdition;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashlessVoucher extends Model
{
    protected $fillable = [
        'tenant_id',
        'festival_edition_id',
        'code',
        'name',
        'voucher_type',
        'amount_cents',
        'bonus_percentage',
        'min_topup_cents',
        'max_bonus_cents',
        'sponsor_name',
        'total_budget_cents',
        'used_budget_cents',
        'max_redemptions',
        'current_redemptions',
        'max_per_customer',
        'valid_from',
        'valid_until',
        'is_active',
        'meta',
    ];

    protected $casts = [
        'amount_cents'        => 'integer',
        'bonus_percentage'    => 'decimal:2',
        'min_topup_cents'     => 'integer',
        'max_bonus_cents'     => 'integer',
        'total_budget_cents'  => 'integer',
        'used_budget_cents'   => 'integer',
        'max_redemptions'     => 'integer',
        'current_redemptions' => 'integer',
        'max_per_customer'    => 'integer',
        'valid_from'          => 'datetime',
        'valid_until'         => 'datetime',
        'is_active'           => 'boolean',
        'meta'                => 'array',
    ];

    // ── Relationships ──

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function edition(): BelongsTo
    {
        return $this->belongsTo(FestivalEdition::class, 'festival_edition_id');
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(CashlessVoucherRedemption::class);
    }

    // ── Validation ──

    public function isRedeemable(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->valid_from && now()->lt($this->valid_from)) {
            return false;
        }

        if ($this->valid_until && now()->gt($this->valid_until)) {
            return false;
        }

        if ($this->max_redemptions !== null && $this->current_redemptions >= $this->max_redemptions) {
            return false;
        }

        if ($this->total_budget_cents !== null && $this->used_budget_cents >= $this->total_budget_cents) {
            return false;
        }

        return true;
    }

    public function canBeRedeemedBy(int $customerId): bool
    {
        if (! $this->isRedeemable()) {
            return false;
        }

        $customerRedemptions = $this->redemptions()
            ->where('customer_id', $customerId)
            ->count();

        return $customerRedemptions < $this->max_per_customer;
    }

    public function calculateBonusAmount(int $topupAmountCents = 0): int
    {
        return match ($this->voucher_type) {
            'fixed_credit' => $this->amount_cents ?? 0,
            'percentage_bonus', 'topup_bonus' => $this->calculatePercentageBonus($topupAmountCents),
            default => 0,
        };
    }

    private function calculatePercentageBonus(int $topupAmountCents): int
    {
        if ($this->min_topup_cents !== null && $topupAmountCents < $this->min_topup_cents) {
            return 0;
        }

        $bonus = (int) round($topupAmountCents * ($this->bonus_percentage ?? 0) / 100);

        if ($this->max_bonus_cents !== null) {
            $bonus = min($bonus, $this->max_bonus_cents);
        }

        return $bonus;
    }

    // ── Scopes ──

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeValid($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('valid_from')->orWhere('valid_from', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('valid_until')->orWhere('valid_until', '>=', now());
            });
    }
}
