<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class MarketplaceCustomer extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'marketplace_client_id',
        'email',
        'password',
        'first_name',
        'last_name',
        'phone',
        'birth_date',
        'gender',
        'locale',
        'address',
        'city',
        'state',
        'postal_code',
        'country',
        'status',
        'email_verified_at',
        'email_verification_token',
        'email_verification_expires_at',
        'last_login_at',
        'accepts_marketing',
        'marketing_consent_at',
        'total_orders',
        'total_spent',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'email_verification_expires_at' => 'datetime',
        'last_login_at' => 'datetime',
        'marketing_consent_at' => 'datetime',
        'birth_date' => 'date',
        'password' => 'hashed',
        'accepts_marketing' => 'boolean',
        'total_spent' => 'decimal:2',
    ];

    // =========================================
    // Relationships
    // =========================================

    public function marketplaceClient(): BelongsTo
    {
        return $this->belongsTo(MarketplaceClient::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'marketplace_customer_id');
    }

    // =========================================
    // Status Checks
    // =========================================

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    public function isEmailVerified(): bool
    {
        return $this->email_verified_at !== null;
    }

    public function isGuest(): bool
    {
        return $this->password === null;
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
            'total_orders' => $this->orders()->where('status', 'completed')->count(),
            'total_spent' => $this->orders()->where('status', 'completed')->sum('total'),
        ]);
    }

    /**
     * Record login
     */
    public function recordLogin(): void
    {
        $this->update(['last_login_at' => now()]);
    }

    // =========================================
    // Helpers
    // =========================================

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function getFullAddressAttribute(): ?string
    {
        $parts = array_filter([
            $this->address,
            $this->city,
            $this->state,
            $this->postal_code,
            $this->country,
        ]);

        return !empty($parts) ? implode(', ', $parts) : null;
    }

    /**
     * Convert guest to registered customer
     */
    public function convertFromGuest(string $password): void
    {
        $this->update([
            'password' => bcrypt($password),
        ]);
    }

    // =========================================
    // Email Verification
    // =========================================

    /**
     * Generate email verification token
     */
    public function generateEmailVerificationToken(): string
    {
        $token = \Illuminate\Support\Str::random(64);

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
     * Check if customer can resend verification email
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
