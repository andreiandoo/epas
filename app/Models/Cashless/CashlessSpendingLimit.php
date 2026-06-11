<?php

namespace App\Models\Cashless;

use App\Models\FestivalEdition;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashlessSpendingLimit extends Model
{
    protected $fillable = [
        'tenant_id', 'festival_edition_id', 'parent_account_id', 'child_account_id',
        'daily_limit_cents', 'total_limit_cents', 'per_transaction_limit_cents',
        'daily_spent_cents', 'total_spent_cents', 'blocked_categories',
        'require_approval_above_cents', 'is_active',
    ];

    protected $casts = [
        'daily_limit_cents'           => 'integer',
        'total_limit_cents'           => 'integer',
        'per_transaction_limit_cents' => 'integer',
        'daily_spent_cents'           => 'integer',
        'total_spent_cents'           => 'integer',
        'blocked_categories'          => 'array',
        'require_approval_above_cents' => 'integer',
        'is_active'                   => 'boolean',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function edition(): BelongsTo { return $this->belongsTo(FestivalEdition::class, 'festival_edition_id'); }
    public function parentAccount(): BelongsTo { return $this->belongsTo(CashlessAccount::class, 'parent_account_id'); }
    public function childAccount(): BelongsTo { return $this->belongsTo(CashlessAccount::class, 'child_account_id'); }

    /**
     * Check if a charge is allowed under this spending limit.
     */
    public function isChargeAllowed(int $amountCents, ?string $category = null): array
    {
        if (! $this->is_active) {
            return ['allowed' => true];
        }

        // Check blocked categories
        if ($category && ! empty($this->blocked_categories) && in_array($category, $this->blocked_categories)) {
            return ['allowed' => false, 'reason' => "Category '{$category}' is blocked."];
        }

        // Check per-transaction limit
        if ($this->per_transaction_limit_cents && $amountCents > $this->per_transaction_limit_cents) {
            return ['allowed' => false, 'reason' => 'Exceeds per-transaction limit.'];
        }

        // Check daily limit
        if ($this->daily_limit_cents && ($this->daily_spent_cents + $amountCents) > $this->daily_limit_cents) {
            return ['allowed' => false, 'reason' => 'Daily spending limit reached.'];
        }

        // Check total limit
        if ($this->total_limit_cents && ($this->total_spent_cents + $amountCents) > $this->total_limit_cents) {
            return ['allowed' => false, 'reason' => 'Total spending limit reached.'];
        }

        return ['allowed' => true];
    }

    /**
     * Record a charge against this limit.
     */
    public function recordCharge(int $amountCents): void
    {
        $this->increment('daily_spent_cents', $amountCents);
        $this->increment('total_spent_cents', $amountCents);
    }

    public function resetDaily(): void
    {
        $this->update(['daily_spent_cents' => 0]);
    }

    public function scopeActive($query) { return $query->where('is_active', true); }
}
