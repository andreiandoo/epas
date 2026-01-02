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
        'email_verification_token',
        'email_verification_expires_at',
        'commission_rate',
        'settings',
        'gamification_enabled',
        'invitations_enabled',
        'tax_settings',
        'payout_details',
        'bank_name',
        'iban',
        'total_events',
        'total_tickets_sold',
        'total_revenue',
        'available_balance',
        'pending_balance',
        'total_paid_out',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'payout_details',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'email_verification_expires_at' => 'datetime',
        'verified_at' => 'datetime',
        'password' => 'hashed',
        'social_links' => 'array',
        'settings' => 'array',
        'gamification_enabled' => 'boolean',
        'invitations_enabled' => 'boolean',
        'tax_settings' => 'array',
        'payout_details' => 'encrypted:array',
        'commission_rate' => 'decimal:2',
        'total_revenue' => 'decimal:2',
        'available_balance' => 'decimal:2',
        'pending_balance' => 'decimal:2',
        'total_paid_out' => 'decimal:2',
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

    public function payouts(): HasMany
    {
        return $this->hasMany(MarketplacePayout::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(MarketplaceTransaction::class);
    }

    public function promoCodes(): HasMany
    {
        return $this->hasMany(MarketplaceOrganizerPromoCode::class);
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

    // =========================================
    // Balance Management
    // =========================================

    /**
     * Get total balance (available + pending)
     */
    public function getTotalBalanceAttribute(): float
    {
        return (float) $this->available_balance + (float) $this->pending_balance;
    }

    /**
     * Check if organizer can request a payout for the given amount
     */
    public function canRequestPayout(float $amount): bool
    {
        return $amount > 0 && $amount <= (float) $this->available_balance;
    }

    /**
     * Get minimum payout amount (from client settings or default)
     */
    public function getMinimumPayoutAmount(): float
    {
        return (float) ($this->marketplaceClient->settings['min_payout_amount'] ?? 100);
    }

    /**
     * Check if organizer has sufficient balance for minimum payout
     */
    public function hasMinimumPayoutBalance(): bool
    {
        return (float) $this->available_balance >= $this->getMinimumPayoutAmount();
    }

    /**
     * Reserve balance for a payout request (move from available to pending)
     */
    public function reserveBalanceForPayout(float $amount): void
    {
        $this->decrement('available_balance', $amount);
        $this->increment('pending_balance', $amount);
    }

    /**
     * Return pending balance to available (when payout is rejected/cancelled)
     */
    public function returnPendingBalance(float $amount): void
    {
        $this->decrement('pending_balance', $amount);
        $this->increment('available_balance', $amount);
    }

    /**
     * Record completed payout
     */
    public function recordPayoutCompleted(float $amount): void
    {
        $this->decrement('pending_balance', $amount);
        $this->increment('total_paid_out', $amount);
    }

    /**
     * Get payout history
     */
    public function getPayoutHistory(int $limit = 10)
    {
        return $this->payouts()
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Get transaction history
     */
    public function getTransactionHistory(int $limit = 50)
    {
        return $this->transactions()
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Check if there's a pending payout request
     */
    public function hasPendingPayout(): bool
    {
        return $this->payouts()
            ->whereIn('status', ['pending', 'approved', 'processing'])
            ->exists();
    }

    /**
     * Get the current pending payout request
     */
    public function getPendingPayout(): ?MarketplacePayout
    {
        return $this->payouts()
            ->whereIn('status', ['pending', 'approved', 'processing'])
            ->first();
    }

    // =========================================
    // Email Verification
    // =========================================

    /**
     * Check if email is verified
     */
    public function isEmailVerified(): bool
    {
        return $this->email_verified_at !== null;
    }

    /**
     * Generate email verification token
     */
    public function generateEmailVerificationToken(): string
    {
        $token = Str::random(64);

        $this->update([
            'email_verification_token' => hash('sha256', $token),
            'email_verification_expires_at' => now()->addHours(24),
        ]);

        return $token;
    }

    /**
     * Verify email with token
     */
    public function verifyEmailWithToken(string $token): bool
    {
        if (!$this->email_verification_token) {
            return false;
        }

        if ($this->email_verification_expires_at && $this->email_verification_expires_at->isPast()) {
            return false;
        }

        if (!hash_equals($this->email_verification_token, hash('sha256', $token))) {
            return false;
        }

        $this->update([
            'email_verified_at' => now(),
            'email_verification_token' => null,
            'email_verification_expires_at' => null,
        ]);

        return true;
    }

    /**
     * Check if verification token is expired
     */
    public function isVerificationTokenExpired(): bool
    {
        return $this->email_verification_expires_at && $this->email_verification_expires_at->isPast();
    }

    /**
     * Check if organizer can resend verification email
     * (rate limiting: at least 1 minute between requests)
     */
    public function canResendVerification(): bool
    {
        if ($this->isEmailVerified()) {
            return false;
        }

        if (!$this->email_verification_expires_at) {
            return true;
        }

        // Allow resend if token was created more than 1 minute ago
        $tokenCreatedAt = $this->email_verification_expires_at->subHours(24);
        return $tokenCreatedAt->diffInMinutes(now()) >= 1;
    }
}
