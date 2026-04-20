<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class VenueStaffMember extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $fillable = [
        'tenant_id',
        'name',
        'email',
        'password',
        'phone',
        'role',
        'permissions',
        'status',
        'invite_token',
        'invite_expires_at',
        'invite_sent_at',
        'accepted_at',
    ];

    protected $hidden = [
        'password',
        'invite_token',
        'remember_token',
    ];

    protected $casts = [
        'permissions' => 'array',
        'invite_expires_at' => 'datetime',
        'invite_sent_at' => 'datetime',
        'accepted_at' => 'datetime',
    ];

    // =========================================
    // Relationships
    // =========================================

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    // =========================================
    // Status Checks
    // =========================================

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isInactive(): bool
    {
        return $this->status === 'inactive';
    }

    public function isInviteExpired(): bool
    {
        return $this->invite_expires_at && $this->invite_expires_at->isPast();
    }

    // =========================================
    // Permissions
    // =========================================

    public function hasPermission(string $permission): bool
    {
        if ($this->role === 'admin') {
            return true;
        }

        return in_array($permission, $this->permissions ?? []);
    }

    public function getEffectivePermissions(): array
    {
        return match ($this->role) {
            'admin' => ['checkin', 'door_sales', 'reports', 'team'],
            'manager' => $this->permissions ?? ['checkin', 'door_sales', 'reports'],
            'staff' => $this->permissions ?? ['checkin', 'door_sales'],
            default => $this->permissions ?? [],
        };
    }

    // =========================================
    // Invite Management
    // =========================================

    public function generateInviteToken(): string
    {
        $token = Str::random(64);

        $this->update([
            'invite_token' => hash('sha256', $token),
            'invite_expires_at' => now()->addDays(7),
            'invite_sent_at' => now(),
        ]);

        return $token;
    }

    public function verifyInviteToken(string $token): bool
    {
        if (!$this->invite_token || $this->isInviteExpired()) {
            return false;
        }

        return hash_equals($this->invite_token, hash('sha256', $token));
    }

    public function acceptInvite(string $password): void
    {
        $this->update([
            'password' => bcrypt($password),
            'status' => 'active',
            'invite_token' => null,
            'invite_expires_at' => null,
            'accepted_at' => now(),
        ]);
    }

    public function canResendInvite(): bool
    {
        if (!$this->isPending()) {
            return false;
        }

        if (!$this->invite_sent_at) {
            return true;
        }

        return $this->invite_sent_at->diffInMinutes(now()) >= 5;
    }

    // =========================================
    // Helpers
    // =========================================

    public function getRoleLabelAttribute(): string
    {
        return match ($this->role) {
            'admin' => 'Administrator',
            'manager' => 'Manager',
            'staff' => 'Staff',
            default => ucfirst($this->role),
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'Invitație trimisă',
            'active' => 'Activ',
            'inactive' => 'Inactiv',
            default => ucfirst($this->status),
        };
    }
}
