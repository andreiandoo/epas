<?php

namespace App\Models;

use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Affiliate extends Model
{
    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'contact_email',
        'status',
        'commission_rate',
        'commission_type',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'commission_rate' => 'decimal:2',
    ];

    /**
     * Boot the model
     */
    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope());

        static::creating(function ($affiliate) {
            if (!$affiliate->tenant_id && auth()->check() && isset(auth()->user()->tenant_id)) {
                $affiliate->tenant_id = auth()->user()->tenant_id;
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function links(): HasMany
    {
        return $this->hasMany(AffiliateLink::class);
    }

    public function coupons(): HasMany
    {
        return $this->hasMany(AffiliateCoupon::class);
    }

    public function conversions(): HasMany
    {
        return $this->hasMany(AffiliateConversion::class);
    }

    public function clicks(): HasMany
    {
        return $this->hasMany(AffiliateClick::class);
    }

    /**
     * Scope for active affiliates
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope for tenant
     */
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Calculate commission for a given order amount
     */
    public function calculateCommission(float $amount): float
    {
        if ($this->commission_type === 'fixed') {
            return (float) $this->commission_rate;
        }

        return round(($amount * $this->commission_rate) / 100, 2);
    }

    /**
     * Check if email matches affiliate's contact email (self-purchase check)
     */
    public function isSelfPurchase(string $buyerEmail): bool
    {
        return strtolower($this->contact_email) === strtolower($buyerEmail);
    }

    /**
     * Get total approved commission
     */
    public function getTotalCommissionAttribute(): float
    {
        return (float) $this->conversions()
            ->where('status', 'approved')
            ->sum('commission_value');
    }

    /**
     * Get total pending commission
     */
    public function getPendingCommissionAttribute(): float
    {
        return (float) $this->conversions()
            ->where('status', 'pending')
            ->sum('commission_value');
    }

    /**
     * Get total sales generated
     */
    public function getTotalSalesAttribute(): float
    {
        return (float) $this->conversions()
            ->whereIn('status', ['approved', 'pending'])
            ->sum('amount');
    }
}
