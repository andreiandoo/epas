<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class MarketplaceAdmin extends Authenticatable implements FilamentUser
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'marketplace_client_id',
        'email',
        'password',
        'name',
        'phone',
        'role',
        'permissions',
        'status',
        'email_verified_at',
        'last_login_at',
        'last_login_ip',
        'settings',
        'locale',
        'timezone',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'permissions' => 'array',
        'settings' => 'array',
        'password' => 'hashed',
    ];

    /**
     * Get the marketplace client this admin belongs to
     */
    public function marketplaceClient(): BelongsTo
    {
        return $this->belongsTo(MarketplaceClient::class);
    }

    /**
     * Check if user can access the Filament panel
     */
    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() === 'marketplace') {
            return $this->isActive() && $this->marketplaceClient?->status === 'active';
        }

        return false;
    }

    /**
     * Check if admin is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if admin has a specific role
     */
    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    /**
     * Check if admin is super admin
     */
    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    /**
     * Check if admin has a specific permission
     */
    public function hasPermission(string $permission): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $permissions = $this->permissions ?? [];
        return in_array($permission, $permissions) || in_array('*', $permissions);
    }

    /**
     * Check if email is verified
     */
    public function hasVerifiedEmail(): bool
    {
        return $this->email_verified_at !== null;
    }

    /**
     * Mark email as verified
     */
    public function markEmailAsVerified(): bool
    {
        return $this->update(['email_verified_at' => now()]);
    }

    /**
     * Record login
     */
    public function recordLogin(?string $ip = null): void
    {
        $this->update([
            'last_login_at' => now(),
            'last_login_ip' => $ip,
        ]);
    }

    /**
     * Scope for active admins
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope by marketplace client
     */
    public function scopeForClient($query, int $clientId)
    {
        return $query->where('marketplace_client_id', $clientId);
    }

    /**
     * Available roles
     */
    public static function roles(): array
    {
        return [
            'super_admin' => 'Super Administrator',
            'admin' => 'Administrator',
            'moderator' => 'Moderator',
        ];
    }

    /**
     * Available permissions
     */
    public static function availablePermissions(): array
    {
        return [
            'events.view' => 'View events',
            'events.approve' => 'Approve/reject events',
            'organizers.view' => 'View organizers',
            'organizers.manage' => 'Manage organizers',
            'payouts.view' => 'View payouts',
            'payouts.process' => 'Process payouts',
            'customers.view' => 'View customers',
            'customers.manage' => 'Manage customers',
            'orders.view' => 'View orders',
            'orders.refund' => 'Process refunds',
            'settings.view' => 'View settings',
            'settings.manage' => 'Manage settings',
            'admins.view' => 'View other admins',
            'admins.manage' => 'Manage other admins',
        ];
    }
}
