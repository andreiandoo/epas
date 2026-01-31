<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class MarketplaceOrganizerTeamMember extends Model
{
    protected $fillable = [
        'marketplace_organizer_id',
        'name',
        'email',
        'password',
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

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(MarketplaceOrganizer::class, 'marketplace_organizer_id');
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
        // Admins have all permissions
        if ($this->role === 'admin') {
            return true;
        }

        $permissions = $this->permissions ?? [];
        return in_array($permission, $permissions);
    }

    public function getEffectivePermissions(): array
    {
        // Admins have all permissions
        if ($this->role === 'admin') {
            return ['events', 'orders', 'reports', 'team', 'checkin'];
        }

        return $this->permissions ?? [];
    }

    // =========================================
    // Invite Management
    // =========================================

    /**
     * Generate a new invite token
     */
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

    /**
     * Verify invite token
     */
    public function verifyInviteToken(string $token): bool
    {
        if (!$this->invite_token) {
            return false;
        }

        if ($this->isInviteExpired()) {
            return false;
        }

        return hash_equals($this->invite_token, hash('sha256', $token));
    }

    /**
     * Accept invite and activate membership
     */
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

    /**
     * Check if can resend invite (rate limiting)
     */
    public function canResendInvite(): bool
    {
        if (!$this->isPending()) {
            return false;
        }

        if (!$this->invite_sent_at) {
            return true;
        }

        // Allow resend if last invite was sent more than 5 minutes ago
        return $this->invite_sent_at->diffInMinutes(now()) >= 5;
    }

    // =========================================
    // Helpers
    // =========================================

    /**
     * Get role label in Romanian
     */
    public function getRoleLabelAttribute(): string
    {
        return match ($this->role) {
            'admin' => 'Administrator',
            'manager' => 'Manager',
            'staff' => 'Staff',
            default => ucfirst($this->role),
        };
    }

    /**
     * Get status label in Romanian
     */
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
