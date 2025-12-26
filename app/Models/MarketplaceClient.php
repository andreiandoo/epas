<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class MarketplaceClient extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'domain',
        'api_key',
        'api_secret',
        'contact_email',
        'contact_phone',
        'company_name',
        'status',
        'commission_rate',
        'allowed_tenants',
        'settings',
        'notes',
        'last_api_call_at',
    ];

    protected $casts = [
        'commission_rate' => 'decimal:2',
        'allowed_tenants' => 'array',
        'settings' => 'array',
        'last_api_call_at' => 'datetime',
    ];

    protected $hidden = [
        'api_secret',
    ];

    /**
     * Boot method to generate API keys
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($client) {
            if (empty($client->api_key)) {
                $client->api_key = 'mpc_' . Str::random(60);
            }
            if (empty($client->api_secret)) {
                $client->api_secret = hash('sha256', Str::random(64));
            }
            if (empty($client->slug)) {
                $client->slug = Str::slug($client->name);
            }
        });
    }

    /**
     * Tenants this marketplace client can sell tickets for
     */
    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'marketplace_client_tenants')
            ->withPivot(['is_active', 'commission_override'])
            ->withTimestamps();
    }

    /**
     * Get active tenants
     */
    public function activeTenants(): BelongsToMany
    {
        return $this->tenants()->wherePivot('is_active', true);
    }

    /**
     * Check if client can sell tickets for a tenant
     */
    public function canSellForTenant(int $tenantId): bool
    {
        // If allowed_tenants is null, all tenants are allowed
        if (is_null($this->allowed_tenants)) {
            return true;
        }

        // Check if tenant is in allowed list
        if (in_array($tenantId, $this->allowed_tenants)) {
            return true;
        }

        // Check pivot table
        return $this->activeTenants()->where('tenant_id', $tenantId)->exists();
    }

    /**
     * Get commission rate for a specific tenant
     */
    public function getCommissionForTenant(int $tenantId): float
    {
        $pivot = $this->tenants()->where('tenant_id', $tenantId)->first();

        if ($pivot && !is_null($pivot->pivot->commission_override)) {
            return (float) $pivot->pivot->commission_override;
        }

        return (float) $this->commission_rate;
    }

    /**
     * Check if client is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Update last API call timestamp
     */
    public function touchApiCall(): void
    {
        $this->update(['last_api_call_at' => now()]);
    }

    /**
     * Regenerate API secret
     */
    public function regenerateApiSecret(): string
    {
        $newSecret = hash('sha256', Str::random(64));
        $this->update(['api_secret' => $newSecret]);
        return $newSecret;
    }

    /**
     * Scope for active clients
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Get the website folder path
     */
    public function getWebsitePath(): string
    {
        return base_path("marketplace-clients/{$this->slug}");
    }
}
