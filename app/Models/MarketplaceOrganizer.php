<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class MarketplaceOrganizer extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'marketplace_client_id',
        'email',
        'password',
        'name',
        'slug',
        'contact_name',
        'phone',
        'company_name',
        'company_tax_id',
        'company_registration',
        'company_address',
        'logo',
        'description',
        'website',
        'social_links',
        'status',
        'verified_at',
        'email_verified_at',
        'commission_rate',
        'settings',
        'payout_details',
        'total_events',
        'total_tickets_sold',
        'total_revenue',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'payout_details',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'verified_at' => 'datetime',
        'password' => 'hashed',
        'social_links' => 'array',
        'settings' => 'array',
        'payout_details' => 'encrypted:array',
        'commission_rate' => 'decimal:2',
        'total_revenue' => 'decimal:2',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($organizer) {
            if (empty($organizer->slug)) {
                $organizer->slug = Str::slug($organizer->name);
            }
        });
    }

    // =========================================
    // Relationships
    // =========================================

    public function marketplaceClient(): BelongsTo
    {
        return $this->belongsTo(MarketplaceClient::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(MarketplaceEvent::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'marketplace_organizer_id');
    }

    // =========================================
    // Status Checks
    // =========================================

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    // =========================================
    // Commission
    // =========================================

    /**
     * Get the effective commission rate for this organizer
     */
    public function getEffectiveCommissionRate(): float
    {
        // Organizer-specific rate takes priority
        if ($this->commission_rate !== null) {
            return (float) $this->commission_rate;
        }

        // Fall back to marketplace client's rate
        return (float) $this->marketplaceClient->commission_rate;
    }

    // =========================================
    // Stats
    // =========================================

    /**
     * Update cached stats
     */
    public function updateStats(): void
    {
        $this->update([
            'total_events' => $this->events()->count(),
            'total_tickets_sold' => $this->orders()
                ->where('status', 'completed')
                ->withCount('tickets')
                ->get()
                ->sum('tickets_count'),
            'total_revenue' => $this->orders()
                ->where('status', 'completed')
                ->sum('total'),
        ]);
    }

    // =========================================
    // Helpers
    // =========================================

    public function getFullNameAttribute(): string
    {
        return $this->contact_name ?? $this->name;
    }

    public function getLogoUrlAttribute(): ?string
    {
        if (!$this->logo) {
            return null;
        }

        return str_starts_with($this->logo, 'http')
            ? $this->logo
            : asset('storage/' . $this->logo);
    }
}
