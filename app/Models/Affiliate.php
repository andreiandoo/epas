<?php

namespace App\Models;

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
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

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
}
