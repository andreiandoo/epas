<?php

namespace App\Models\Cashless;

use App\Enums\FeeType;
use App\Models\FestivalEdition;
use App\Models\Tenant;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinanceFeeRule extends Model
{
    protected $fillable = [
        'tenant_id', 'festival_edition_id', 'vendor_id', 'name', 'fee_type',
        'amount_cents', 'percentage', 'category_filter', 'period_start', 'period_end',
        'is_active', 'apply_on', 'billing_frequency', 'notes', 'meta',
    ];

    protected $casts = [
        'fee_type'        => FeeType::class,
        'amount_cents'    => 'integer',
        'percentage'      => 'decimal:4',
        'category_filter' => 'array',
        'period_start'    => 'date',
        'period_end'      => 'date',
        'is_active'       => 'boolean',
        'meta'            => 'array',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function edition(): BelongsTo { return $this->belongsTo(FestivalEdition::class, 'festival_edition_id'); }
    public function vendor(): BelongsTo { return $this->belongsTo(Vendor::class); }

    public function appliesToVendor(int $vendorId): bool
    {
        return $this->vendor_id === null || $this->vendor_id === $vendorId;
    }

    public function appliesToCategory(?string $category): bool
    {
        if ($this->fee_type !== FeeType::PercentagePerCategory) {
            return true;
        }
        if (empty($this->category_filter) || $category === null) {
            return false;
        }
        return in_array($category, $this->category_filter);
    }

    /**
     * Calculate fee for a given amount in cents.
     */
    public function calculateFee(int $amountCents, ?string $category = null): int
    {
        if (! $this->is_active) return 0;
        if (! $this->appliesToCategory($category)) return 0;

        return match ($this->fee_type) {
            FeeType::PercentageSales, FeeType::PercentagePerCategory
                => (int) round($amountCents * $this->percentage / 100),
            FeeType::FixedPerTransaction
                => $this->amount_cents ?? 0,
            default => 0, // daily/period fees are calculated differently
        };
    }

    public function scopeActive($query) { return $query->where('is_active', true); }
    public function scopeForEdition($query, int $id) { return $query->where('festival_edition_id', $id); }
}
