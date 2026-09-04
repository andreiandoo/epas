<?php

namespace App\Models;

use App\Models\MarketplaceClient;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'marketplace_client_id',
        'name',
        'first_name',
        'last_name',
        'email',
        'password',
        'phone',
        'position',
        'avatar',
        'role',
        'tenant_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }


    public function isSuperAdmin(): bool { return $this->role === 'super-admin'; }
    public function isAdmin(): bool { return $this->role === 'admin'; }
    public function isEditor(): bool { return $this->role === 'editor'; }
    public function isTenant(): bool { return $this->role === 'tenant'; }

    /**
     * Determine if the user can access a Filament panel.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() === 'tenant') {
            // Allow admin/super-admin in demo mode
            if (session()->has('demo_tenant_id') && in_array($this->role, ['super-admin', 'admin'])) {
                return true;
            }
            // Super-admin impersonation via /admin/tenants Login-as: the
            // session is stamped with impersonated_from_user_id +
            // impersonated_tenant_id when a super admin logs in as a
            // tenant's owner. Older tenants have owner users whose role
            // isn't 'tenant' (some are 'admin', some legacy), so without
            // this branch every Login-as bounces to /tenant/login even
            // though Auth::login just succeeded. Only trust the flag when
            // BOTH keys are present (route sets them together) AND the
            // currently authenticated user is the impersonated tenant's
            // owner — a stray session key alone can't unlock the panel.
            if (session()->has('impersonated_from_user_id') && session()->has('impersonated_tenant_id')) {
                $tenantId = (int) session('impersonated_tenant_id');
                $tenant = \App\Models\Tenant::find($tenantId);
                if ($tenant && (int) $tenant->owner_id === (int) $this->id) {
                    return true;
                }
            }
            return $this->role === 'tenant';
        }

        // Admin panel
        return in_array($this->role, ['super-admin', 'admin', 'editor']);
    }

    /**
     * Get the tenant this user owns (if tenant role).
     */
    public function ownedTenant(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Tenant::class, 'owner_id');
    }

    /**
     * Get the tenant this user belongs to (for editors).
     */
    public function belongsToTenant(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the tenant for this user (works for both tenant owners and editors).
     */
    /**
     * Get the effective tenant ID, respecting demo mode.
     * Use this instead of $user->tenant_id for runtime tenant resolution.
     */
    public function currentTenantId(): ?int
    {
        if (session()->has('demo_tenant_id')) {
            return (int) session('demo_tenant_id');
        }
        return $this->attributes['tenant_id'] ?? $this->ownedTenant?->id ?? null;
    }

    /**
     * Override getAttribute to intercept tenant_id reads in demo mode.
     * This ensures ALL code that does $user->tenant_id gets the demo tenant.
     */
    public function getAttribute($key)
    {
        if ($key === 'tenant_id' && session()->has('demo_tenant_id')) {
            return (int) session('demo_tenant_id');
        }
        return parent::getAttribute($key);
    }

    public function getTenantAttribute(): ?Tenant
    {
        // Demo mode: return the shadow tenant
        if (session()->has('demo_tenant_id')) {
            return Tenant::find(session('demo_tenant_id'));
        }

        // First check if user belongs to a tenant via tenant_id
        $realTenantId = $this->attributes['tenant_id'] ?? null;
        if ($realTenantId) {
            return Tenant::find($realTenantId);
        }
        // Otherwise check if user owns a tenant (tenant role)
        return $this->ownedTenant;
    }
    /**
     * Get the marketplace client that owns this record
     */
    public function marketplaceClient()
    {
        return $this->belongsTo(MarketplaceClient::class);
    }

}
