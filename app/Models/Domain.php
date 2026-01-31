<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Domain extends Model
{
    protected $fillable = [
        'tenant_id',
        'domain',
        'is_active',
        'is_suspended',
        'is_primary',
        'is_managed_subdomain',
        'subdomain',
        'base_domain',
        'cloudflare_record_id',
        'activated_at',
        'suspended_at',
        'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_suspended' => 'boolean',
        'is_primary' => 'boolean',
        'is_managed_subdomain' => 'boolean',
        'activated_at' => 'datetime',
        'suspended_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function verifications(): HasMany
    {
        return $this->hasMany(DomainVerification::class);
    }

    public function latestVerification(): HasOne
    {
        return $this->hasOne(DomainVerification::class)->latestOfMany();
    }

    public function packages(): HasMany
    {
        return $this->hasMany(TenantPackage::class);
    }

    public function latestPackage(): HasOne
    {
        return $this->hasOne(TenantPackage::class)->latestOfMany();
    }

    public function isVerified(): bool
    {
        return $this->latestVerification?->isVerified() ?? false;
    }

    public function hasReadyPackage(): bool
    {
        return $this->packages()->ready()->exists();
    }

    /**
     * Scope pentru domenii active
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->where('is_suspended', false);
    }

    /**
     * Scope pentru domeniul principal
     */
    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    /**
     * Scope pentru subdomenii gestionate
     */
    public function scopeManagedSubdomains($query)
    {
        return $query->where('is_managed_subdomain', true);
    }

    /**
     * Check if this is a managed subdomain
     */
    public function isManagedSubdomain(): bool
    {
        return $this->is_managed_subdomain === true;
    }

    /**
     * Get the full domain name
     */
    public function getFullDomain(): string
    {
        if ($this->is_managed_subdomain && $this->subdomain && $this->base_domain) {
            return "{$this->subdomain}.{$this->base_domain}";
        }

        return $this->domain;
    }
}
