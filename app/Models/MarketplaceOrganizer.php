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

        // Organizer Type Fields
        'person_type',
        'work_mode',
        'organizer_type',
        'leisure_template_variant',

        // Company details
        'company_name',
        'company_tax_id',
        'company_registration',
        'vat_payer',
        'company_address',
        'company_city',
        'company_county',
        'company_zip',
        'past_contract',
        'representative_first_name',
        'representative_last_name',

        // Guarantor / Personal Details
        'guarantor_first_name',
        'guarantor_last_name',
        'guarantor_cnp',
        'guarantor_address',
        'guarantor_city',
        'guarantor_id_type',
        'guarantor_id_series',
        'guarantor_id_number',
        'guarantor_id_issued_by',
        'guarantor_id_issued_date',

        'city',
        'state',
        'logo',
        'id_card_document',
        'cui_document',
        'description',
        'ticket_terms',
        'website',
        'social_links',
        'status',
        'verified_at',
        'has_proxy_authorization',
        'proxy_authorization_file',
        'proxy_admin_id',
        'email_verified_at',
        'email_verification_token',
        'email_verification_expires_at',
        'commission_rate',
        'fixed_commission_default',
        'default_commission_mode',
        'commission_use_floor',
        'test_pos_enabled',
        // Override for marketplace.payment_fees.pass_to_customer flag.
        // NULL = inherit. Values: 'pass_to_customer' | 'absorbed_by_commission'.
        'payment_fee_mode',
        'settings',
        'gamification_enabled',
        'invitations_enabled',
        'service_settings',
        'tax_settings',
        'api_key',
        'payout_details',
        'bank_name',
        'iban',
        // A doua societate emitenta (cazul Lacul Sf. Ana)
        'has_secondary_issuer',
        'secondary_company_name',
        'secondary_company_tax_id',
        'secondary_company_registration',
        'secondary_company_address',
        'secondary_company_city',
        'secondary_company_county',
        'secondary_company_zip',
        'secondary_bank_name',
        'secondary_iban',
        // Numerotare facturi separata per societate
        'primary_invoice_series',
        'primary_last_invoice_number',
        'secondary_invoice_series',
        'secondary_last_invoice_number',
        // Per-issuer VAT (vat_payer = legacy/global; primary_/secondary_* per societate)
        'primary_vat_payer',
        'primary_vat_rate',
        'secondary_vat_payer',
        'secondary_vat_rate',
        'contract_number_series',
        'contract_date',
        'invoice_due_days',
        'total_events',
        'total_tickets_sold',
        'total_revenue',
        'available_balance',
        'pending_balance',
        'total_paid_out',
        'is_public',
        'is_featured',
        'cover_image',
    ];

    /**
     * Resolve the public profile URL on the marketplace front-end.
     * Prefers an active vanity URL pointing to this organizer; falls back
     * to /organizator/{slug}. Returns null if the organizer has no slug.
     */
    public function getPublicProfileUrl(): ?string
    {
        if (empty($this->slug)) return null;

        $marketplace = $this->marketplaceClient ?? MarketplaceClient::find($this->marketplace_client_id);
        $domain = $marketplace?->domain ?? null;
        if (!$domain) return null;

        $base = (str_starts_with($domain, 'http') ? $domain : 'https://' . $domain);

        $vanity = MarketplaceVanityUrl::where('marketplace_client_id', $this->marketplace_client_id)
            ->where('target_type', MarketplaceVanityUrl::TYPE_ORGANIZER)
            ->where('target_id', $this->id)
            ->where('is_active', true)
            ->orderByDesc('id')
            ->first();

        if ($vanity) {
            return rtrim($base, '/') . '/' . ltrim($vanity->slug, '/');
        }

        return rtrim($base, '/') . '/organizator/' . $this->slug;
    }

    protected $hidden = [
        'password',
        'remember_token',
        'payout_details',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'email_verification_expires_at' => 'datetime',
        'verified_at' => 'datetime',
        'has_proxy_authorization' => 'boolean',
        'password' => 'hashed',
        'social_links' => 'array',
        'settings' => 'array',
        'is_public' => 'boolean',
        'is_featured' => 'boolean',
        'gamification_enabled' => 'boolean',
        'invitations_enabled' => 'boolean',
        'service_settings' => 'array',
        'vat_payer' => 'boolean',
        'tax_settings' => 'array',
        'payout_details' => 'encrypted:array',
        'commission_rate' => 'decimal:2',
        'fixed_commission_default' => 'decimal:2',
        'commission_use_floor' => 'boolean',
        'test_pos_enabled' => 'boolean',
        'total_revenue' => 'decimal:2',
        'available_balance' => 'decimal:2',
        'pending_balance' => 'decimal:2',
        'total_paid_out' => 'decimal:2',
        'guarantor_id_issued_date' => 'date',
        'contract_date' => 'date',
        'has_secondary_issuer' => 'boolean',
        'primary_last_invoice_number' => 'integer',
        'secondary_last_invoice_number' => 'integer',
        'primary_vat_payer' => 'boolean',
        'secondary_vat_payer' => 'boolean',
        'primary_vat_rate' => 'decimal:2',
        'secondary_vat_rate' => 'decimal:2',
    ];

    /**
     * Returneaza datele juridice ale societatii emitente cerute (primary | secondary).
     * Cand 'secondary' e cerut dar has_secondary_issuer=false, cade la primary.
     */
    public function getIssuerData(?string $company = 'primary'): array
    {
        $useSecondary = ($company === 'secondary') && $this->has_secondary_issuer;

        if ($useSecondary) {
            return [
                'company' => 'secondary',
                'name' => $this->secondary_company_name,
                'tax_id' => $this->secondary_company_tax_id,
                'registration' => $this->secondary_company_registration,
                'address' => $this->secondary_company_address,
                'city' => $this->secondary_company_city,
                'county' => $this->secondary_company_county,
                'zip' => $this->secondary_company_zip,
                'bank_name' => $this->secondary_bank_name,
                'iban' => $this->secondary_iban,
                'invoice_series' => $this->secondary_invoice_series,
                'last_invoice_number' => (int) ($this->secondary_last_invoice_number ?? 0),
                'vat_payer' => (bool) $this->secondary_vat_payer,
                'vat_rate' => $this->secondary_vat_rate !== null ? (float) $this->secondary_vat_rate : null,
                // Email de contact al organizatorului — folosit pe factura POS in
                // linia "Emis de {name} - {email}" sub seria facturii.
                'contact_email' => $this->email,
            ];
        }

        return [
            'company' => 'primary',
            'name' => $this->company_name,
            'tax_id' => $this->company_tax_id,
            'registration' => $this->company_registration,
            'address' => $this->company_address,
            'city' => $this->company_city,
            'county' => $this->company_county,
            'zip' => $this->company_zip,
            'bank_name' => $this->bank_name,
            'iban' => $this->iban,
            'invoice_series' => $this->primary_invoice_series,
            'last_invoice_number' => (int) ($this->primary_last_invoice_number ?? 0),
            // Fallback la legacy vat_payer / tax_settings.vat_rate cand primary_* nu sunt setate
            'vat_payer' => $this->primary_vat_payer !== null
                ? (bool) $this->primary_vat_payer
                : (bool) ($this->vat_payer ?? false),
            'vat_rate' => $this->primary_vat_rate !== null
                ? (float) $this->primary_vat_rate
                : (isset($this->tax_settings['vat_rate']) ? (float) $this->tax_settings['vat_rate'] : null),
            // Email de contact al organizatorului — folosit pe factura POS in
            // linia "Emis de {name} - {email}" sub seria facturii.
            'contact_email' => $this->email,
        ];
    }

    /**
     * Reserve atomic next invoice number for the requested company (primary|secondary).
     * Returns formatted "SERIES-000123" when series is set, plain number string otherwise.
     */
    public function reserveNextInvoiceNumber(string $company = 'primary', int $padding = 6): string
    {
        $field = $company === 'secondary' ? 'secondary_last_invoice_number' : 'primary_last_invoice_number';
        $seriesField = $company === 'secondary' ? 'secondary_invoice_series' : 'primary_invoice_series';

        return \Illuminate\Support\Facades\DB::transaction(function () use ($field, $seriesField, $padding) {
            $fresh = static::where('id', $this->id)->lockForUpdate()->first();
            $next = (int) ($fresh->{$field} ?? 0) + 1;
            $fresh->{$field} = $next;
            $fresh->save();

            $this->{$field} = $next;

            $padded = str_pad((string) $next, $padding, '0', STR_PAD_LEFT);
            $series = $fresh->{$seriesField};
            return $series ? $series . '-' . $padded : $padded;
        });
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($organizer) {
            if (!$organizer->name) {
                return;
            }
            $base = !empty($organizer->slug) ? $organizer->slug : Str::slug($organizer->name);
            $slug = $base;
            $i = 2;
            while (static::withTrashed()->where('marketplace_client_id', $organizer->marketplace_client_id)->where('slug', $slug)->exists()) {
                $slug = $base . '-' . $i++;
            }
            $organizer->slug = $slug;
        });
    }

    // =========================================
    // Relationships
    // =========================================

    public function contactMessages(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(MarketplaceContactMessage::class, 'marketplace_organizer_id');
    }

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

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'marketplace_organizer_id');
    }

    public function bankAccounts(): HasMany
    {
        return $this->hasMany(MarketplaceOrganizerBankAccount::class);
    }

    public function primaryBankAccount()
    {
        return $this->hasOne(MarketplaceOrganizerBankAccount::class)->where('is_primary', true);
    }

    public function teamMembers(): HasMany
    {
        return $this->hasMany(MarketplaceOrganizerTeamMember::class);
    }

    public function activeTeamMembers(): HasMany
    {
        return $this->hasMany(MarketplaceOrganizerTeamMember::class)->where('status', 'active');
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

    /**
     * Get the effective commission mode for this organizer
     * Returns 'included' or 'added_on_top'
     */
    public function getEffectiveCommissionMode(): string
    {
        // Organizer-specific mode takes priority
        if ($this->default_commission_mode !== null) {
            return $this->default_commission_mode;
        }

        // Fall back to marketplace client's mode
        return $this->marketplaceClient->commission_mode ?? 'included';
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

    // =========================================
    // API Key Management
    // =========================================

    /**
     * Generate a new API key for this organizer
     * Format: mpo_{random_string} (marketplace organizer)
     */
    public function generateApiKey(): string
    {
        $key = 'mpo_' . Str::random(48);

        $this->update([
            'api_key' => $key,
        ]);

        return $key;
    }

    /**
     * Regenerate API key (invalidates the old one)
     */
    public function regenerateApiKey(): string
    {
        return $this->generateApiKey();
    }

    /**
     * Check if organizer has an API key
     */
    public function hasApiKey(): bool
    {
        return !empty($this->api_key);
    }

    /**
     * Get masked API key (shows only first and last 4 chars)
     */
    public function getMaskedApiKey(): ?string
    {
        if (!$this->api_key) {
            return null;
        }

        $key = $this->api_key;
        $length = strlen($key);

        if ($length <= 12) {
            return str_repeat('*', $length);
        }

        return substr($key, 0, 8) . str_repeat('*', $length - 12) . substr($key, -4);
    }
}
