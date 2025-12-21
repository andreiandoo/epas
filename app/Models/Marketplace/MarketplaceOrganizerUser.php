<?php

namespace App\Models\Marketplace;

use App\Models\Tenant;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * MarketplaceOrganizerUser Model
 *
 * Represents a user who can access the organizer dashboard.
 * Has their own authentication separate from main platform users.
 */
class MarketplaceOrganizerUser extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * The authentication guard for this model.
     */
    protected string $guard = 'organizer';

    protected $fillable = [
        'organizer_id',
        'name',
        'email',
        'password',
        'phone',
        'role',
        'avatar',
        'position',
        'is_active',
        'last_login_at',
        'last_login_ip',
        'email_verified_at',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_login_at' => 'datetime',
        'email_verified_at' => 'datetime',
        'two_factor_confirmed_at' => 'datetime',
        'password' => 'hashed',
    ];

    /**
     * Role constants
     */
    public const ROLE_ADMIN = 'admin';
    public const ROLE_EDITOR = 'editor';
    public const ROLE_VIEWER = 'viewer';

    /**
     * Get available roles.
     */
    public static function getRoles(): array
    {
        return [
            self::ROLE_ADMIN => 'Administrator',
            self::ROLE_EDITOR => 'Editor',
            self::ROLE_VIEWER => 'Viewer',
        ];
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    /**
     * Get the organizer this user belongs to.
     */
    public function organizer(): BelongsTo
    {
        return $this->belongsTo(MarketplaceOrganizer::class, 'organizer_id');
    }

    /**
     * Get the marketplace (tenant) through the organizer.
     */
    public function getMarketplaceAttribute(): ?Tenant
    {
        return $this->organizer?->marketplace;
    }

    /**
     * Get the tenant (alias for marketplace).
     */
    public function getTenantAttribute(): ?Tenant
    {
        return $this->marketplace;
    }

    // =========================================================================
    // FILAMENT INTEGRATION
    // =========================================================================

    /**
     * Determine if the user can access a Filament panel.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        // Can only access the organizer panel
        if ($panel->getId() !== 'organizer') {
            return false;
        }

        // Must be active and organizer must be active
        return $this->is_active
            && $this->organizer
            && $this->organizer->isActive();
    }

    /**
     * Get the name for Filament.
     */
    public function getFilamentName(): string
    {
        return $this->name;
    }

    /**
     * Get the avatar URL for Filament.
     */
    public function getFilamentAvatarUrl(): ?string
    {
        return $this->avatar;
    }

    // =========================================================================
    // ROLE HELPERS
    // =========================================================================

    /**
     * Check if user is an admin.
     */
    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    /**
     * Check if user is an editor.
     */
    public function isEditor(): bool
    {
        return $this->role === self::ROLE_EDITOR;
    }

    /**
     * Check if user is a viewer.
     */
    public function isViewer(): bool
    {
        return $this->role === self::ROLE_VIEWER;
    }

    // =========================================================================
    // PERMISSION HELPERS
    // =========================================================================

    /**
     * Check if user can manage events.
     */
    public function canManageEvents(): bool
    {
        return in_array($this->role, [self::ROLE_ADMIN, self::ROLE_EDITOR]);
    }

    /**
     * Check if user can manage venues.
     */
    public function canManageVenues(): bool
    {
        return in_array($this->role, [self::ROLE_ADMIN, self::ROLE_EDITOR]);
    }

    /**
     * Check if user can view orders.
     */
    public function canViewOrders(): bool
    {
        return true; // All roles can view orders
    }

    /**
     * Check if user can view reports/analytics.
     */
    public function canViewReports(): bool
    {
        return true; // All roles can view reports
    }

    /**
     * Check if user can manage organizer settings.
     */
    public function canManageSettings(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    /**
     * Check if user can manage team members.
     */
    public function canManageTeam(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    /**
     * Check if user can manage payout settings.
     */
    public function canManagePayouts(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    // =========================================================================
    // LOGIN TRACKING
    // =========================================================================

    /**
     * Record a login.
     */
    public function recordLogin(?string $ip = null): void
    {
        $this->last_login_at = now();
        $this->last_login_ip = $ip;
        $this->save();
    }

    // =========================================================================
    // BOOT & OBSERVERS
    // =========================================================================

    protected static function booted(): void
    {
        // No special boot logic needed
    }
}
