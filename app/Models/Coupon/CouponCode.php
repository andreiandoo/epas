<?php

namespace App\Models\Coupon;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CouponCode extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'coupon_codes';

    protected $fillable = [
        'tenant_id',
        'campaign_id',
        'code',
        'code_type',
        'discount_type',
        'discount_value',
        'max_discount_amount',
        'min_purchase_amount',
        'min_quantity',
        'max_uses_total',
        'max_uses_per_user',
        'current_uses',
        'applicable_products',
        'excluded_products',
        'applicable_categories',
        'applicable_events',
        'allowed_user_segments',
        'first_purchase_only',
        'starts_at',
        'expires_at',
        'valid_days_of_week',
        'valid_hours',
        'timezone',
        'status',
        'is_public',
        'combinable',
        'exclude_combinations',
        'source',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'created_by',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'max_discount_amount' => 'decimal:2',
        'min_purchase_amount' => 'decimal:2',
        'applicable_products' => 'array',
        'excluded_products' => 'array',
        'applicable_categories' => 'array',
        'applicable_events' => 'array',
        'allowed_user_segments' => 'array',
        'first_purchase_only' => 'boolean',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'valid_days_of_week' => 'array',
        'valid_hours' => 'array',
        'is_public' => 'boolean',
        'combinable' => 'boolean',
        'exclude_combinations' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(CouponCampaign::class, 'campaign_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class, 'coupon_id');
    }

    public function isValid(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        if ($this->starts_at && $this->starts_at->isFuture()) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        if ($this->max_uses_total && $this->current_uses >= $this->max_uses_total) {
            return false;
        }

        return true;
    }

    public function isValidForUser(?int $userId): bool
    {
        if (!$this->isValid()) {
            return false;
        }

        if ($this->max_uses_per_user && $userId) {
            $userUsages = $this->redemptions()
                ->where('user_id', $userId)
                ->where('status', '!=', 'cancelled')
                ->count();

            if ($userUsages >= $this->max_uses_per_user) {
                return false;
            }
        }

        return true;
    }

    public function isValidForAmount(float $amount): bool
    {
        if ($this->min_purchase_amount && $amount < $this->min_purchase_amount) {
            return false;
        }

        return true;
    }

    public function isValidAtTime(?\DateTime $time = null): bool
    {
        $time = $time ?? now();
        $timezone = $this->timezone ?? 'UTC';

        // Check day of week
        if ($this->valid_days_of_week) {
            $dayOfWeek = $time->setTimezone(new \DateTimeZone($timezone))->format('w');
            if (!in_array((int) $dayOfWeek, $this->valid_days_of_week)) {
                return false;
            }
        }

        // Check valid hours
        if ($this->valid_hours && isset($this->valid_hours['start'], $this->valid_hours['end'])) {
            $currentTime = $time->setTimezone(new \DateTimeZone($timezone))->format('H:i');
            if ($currentTime < $this->valid_hours['start'] || $currentTime > $this->valid_hours['end']) {
                return false;
            }
        }

        return true;
    }

    public function calculateDiscount(float $amount): float
    {
        $discount = 0;

        switch ($this->discount_type) {
            case 'percentage':
                $discount = $amount * ($this->discount_value / 100);
                break;
            case 'fixed_amount':
                $discount = $this->discount_value;
                break;
            case 'free_shipping':
                // This would need to be handled at cart level
                $discount = 0;
                break;
        }

        // Apply max discount cap
        if ($this->max_discount_amount && $discount > $this->max_discount_amount) {
            $discount = $this->max_discount_amount;
        }

        // Don't allow discount greater than amount
        if ($discount > $amount) {
            $discount = $amount;
        }

        return round($discount, 2);
    }

    public function incrementUsage(): void
    {
        $this->increment('current_uses');

        // Check if exhausted
        if ($this->max_uses_total && $this->current_uses >= $this->max_uses_total) {
            $this->update(['status' => 'exhausted']);
        }
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('starts_at')
                  ->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>=', now());
            });
    }

    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeByCode($query, string $code)
    {
        return $query->where('code', strtoupper($code));
    }
}
