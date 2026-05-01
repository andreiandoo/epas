<?php

namespace App\Models;

use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class MarketplaceArtistAccount extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'marketplace_client_id',
        'artist_id',
        'email',
        'password',
        'first_name',
        'last_name',
        'phone',
        'locale',
        'status',
        'approved_at',
        'approved_by',
        'rejected_at',
        'rejection_reason',
        'claim_message',
        'claim_proof',
        'claim_submitted_at',
        'email_verified_at',
        'email_verification_token',
        'email_verification_expires_at',
        'last_login_at',
        'settings',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'email_verification_token',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'claim_submitted_at' => 'datetime',
        'email_verified_at' => 'datetime',
        'email_verification_expires_at' => 'datetime',
        'last_login_at' => 'datetime',
        'password' => 'hashed',
        'claim_proof' => 'array',
        'settings' => 'array',
    ];

    // =========================================
    // Relationships
    // =========================================

    public function marketplaceClient(): BelongsTo
    {
        return $this->belongsTo(MarketplaceClient::class);
    }

    public function artist(): BelongsTo
    {
        return $this->belongsTo(Artist::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function microservices(): BelongsToMany
    {
        return $this->belongsToMany(
            Microservice::class,
            'marketplace_artist_account_microservices'
        )
            ->withPivot([
                'status',
                'granted_by',
                'granted_by_user_id',
                'service_order_id',
                'activated_at',
                'trial_ends_at',
                'expires_at',
                'cancelled_at',
                'settings',
            ])
            ->withTimestamps();
    }

    public function microserviceActivations(): HasMany
    {
        return $this->hasMany(MarketplaceArtistAccountMicroservice::class, 'marketplace_artist_account_id');
    }

    /**
     * Shortcut catre activarea Extended Artist (sau null daca nu e activat).
     * Returneaza intotdeauna ultimul rand (in caz de duplicare istorica), dar
     * pivot-ul are unique(account, microservice) deci normal e maxim unul.
     */
    public function extendedArtistActivation(): ?MarketplaceArtistAccountMicroservice
    {
        return MarketplaceArtistAccountMicroservice::query()
            ->where('marketplace_artist_account_id', $this->id)
            ->whereHas('microservice', fn ($q) => $q->where('slug', 'extended-artist'))
            ->latest('id')
            ->first();
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

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    public function isEmailVerified(): bool
    {
        return $this->email_verified_at !== null;
    }

    /**
     * Whether this account is allowed to edit the linked Artist profile.
     * Requires an active status AND a linked artist record.
     */
    public function canEditArtistProfile(): bool
    {
        return $this->isActive() && $this->artist_id !== null;
    }

    // =========================================
    // Approval Workflow
    // =========================================

    /**
     * Approve the account. Sets status=active, stores the approving
     * admin's id, and clears any prior rejection.
     *
     * Accepts any Authenticatable (User OR MarketplaceAdmin) — the FK
     * on `approved_by` was dropped in
     * 2026_04_30_140000_drop_approved_by_fk_on_marketplace_artist_accounts
     * because the marketplace panel uses the `marketplace_admin` guard
     * with a separate id space. The `approver()` relation still points
     * at User (best-effort) and resolves to null for marketplace_admin
     * rows — UI just shows "—" in that case.
     */
    public function markApproved(AuthenticatableContract $admin): void
    {
        $this->update([
            'status' => 'active',
            'approved_at' => now(),
            'approved_by' => $admin->getAuthIdentifier(),
            'rejected_at' => null,
            'rejection_reason' => null,
        ]);
    }

    public function markRejected(string $reason): void
    {
        $this->update([
            'status' => 'rejected',
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ]);
    }

    public function markSuspended(): void
    {
        $this->update(['status' => 'suspended']);
    }

    public function markReactivated(): void
    {
        $this->update(['status' => 'active']);
    }

    // =========================================
    // Email Verification
    // =========================================

    /**
     * Generate a fresh email verification token (random 64 chars).
     * Stored as SHA-256 in DB; the plaintext is returned and emailed to the user.
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

    public function isVerificationTokenExpired(): bool
    {
        return $this->email_verification_expires_at
            && $this->email_verification_expires_at->isPast();
    }

    /**
     * Rate-limit re-sending verification emails to once per minute.
     */
    public function canResendVerification(): bool
    {
        if ($this->isEmailVerified()) {
            return false;
        }

        if (!$this->email_verification_expires_at) {
            return true;
        }

        $tokenCreatedAt = $this->email_verification_expires_at->subHours(24);
        return $tokenCreatedAt->diffInMinutes(now()) >= 1;
    }

    // =========================================
    // Helpers
    // =========================================

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function recordLogin(): void
    {
        $this->update(['last_login_at' => now()]);
    }
}
