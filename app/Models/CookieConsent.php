<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class CookieConsent extends Model
{
    /**
     * Consent action types
     */
    public const ACTION_ACCEPT_ALL = 'accept_all';
    public const ACTION_REJECT_ALL = 'reject_all';
    public const ACTION_CUSTOMIZE = 'customize';
    public const ACTION_UPDATE = 'update';

    /**
     * Consent sources
     */
    public const SOURCE_BANNER = 'banner';
    public const SOURCE_SETTINGS = 'settings';
    public const SOURCE_API = 'api';

    /**
     * Consent categories
     */
    public const CATEGORY_NECESSARY = 'necessary';
    public const CATEGORY_ANALYTICS = 'analytics';
    public const CATEGORY_MARKETING = 'marketing';
    public const CATEGORY_PREFERENCES = 'preferences';

    /**
     * Default consent expiry in days (12 months)
     */
    public const DEFAULT_EXPIRY_DAYS = 365;

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'visitor_id',
        'session_id',
        'necessary',
        'analytics',
        'marketing',
        'preferences',
        'consent_details',
        'action',
        'consent_version',
        'ip_address',
        'ip_country',
        'user_agent',
        'device_type',
        'browser',
        'os',
        'consent_source',
        'page_url',
        'referrer_url',
        'legal_basis',
        'privacy_policy_url',
        'consented_at',
        'expires_at',
        'withdrawn_at',
    ];

    protected $casts = [
        'necessary' => 'boolean',
        'analytics' => 'boolean',
        'marketing' => 'boolean',
        'preferences' => 'boolean',
        'consent_details' => 'array',
        'consented_at' => 'datetime',
        'expires_at' => 'datetime',
        'withdrawn_at' => 'datetime',
    ];

    /**
     * Tenant relationship
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Customer relationship
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * History records
     */
    public function history(): HasMany
    {
        return $this->hasMany(CookieConsentHistory::class)->orderByDesc('changed_at');
    }

    /**
     * Check if consent is still valid (not expired or withdrawn)
     */
    public function isValid(): bool
    {
        if ($this->withdrawn_at !== null) {
            return false;
        }

        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Check if a specific category is consented
     */
    public function hasConsent(string $category): bool
    {
        if (!$this->isValid()) {
            return $category === self::CATEGORY_NECESSARY;
        }

        return match ($category) {
            self::CATEGORY_NECESSARY => true, // Always true
            self::CATEGORY_ANALYTICS => $this->analytics,
            self::CATEGORY_MARKETING => $this->marketing,
            self::CATEGORY_PREFERENCES => $this->preferences,
            default => false,
        };
    }

    /**
     * Get all consented categories
     */
    public function getConsentedCategories(): array
    {
        $categories = [self::CATEGORY_NECESSARY];

        if ($this->isValid()) {
            if ($this->analytics) {
                $categories[] = self::CATEGORY_ANALYTICS;
            }
            if ($this->marketing) {
                $categories[] = self::CATEGORY_MARKETING;
            }
            if ($this->preferences) {
                $categories[] = self::CATEGORY_PREFERENCES;
            }
        }

        return $categories;
    }

    /**
     * Withdraw consent
     */
    public function withdraw(string $source = 'settings', ?string $ip = null, ?string $userAgent = null): void
    {
        // Record history before updating
        $this->recordHistory('withdrawal', $source, $ip, $userAgent);

        $this->update([
            'analytics' => false,
            'marketing' => false,
            'preferences' => false,
            'withdrawn_at' => now(),
        ]);
    }

    /**
     * Update consent preferences
     */
    public function updateConsent(
        bool $analytics,
        bool $marketing,
        bool $preferences,
        string $source = 'settings',
        ?string $ip = null,
        ?string $userAgent = null
    ): void {
        // Record history before updating
        $this->recordHistory('update', $source, $ip, $userAgent, [
            'new_analytics' => $analytics,
            'new_marketing' => $marketing,
            'new_preferences' => $preferences,
        ]);

        $this->update([
            'analytics' => $analytics,
            'marketing' => $marketing,
            'preferences' => $preferences,
            'action' => self::ACTION_UPDATE,
            'withdrawn_at' => null, // Clear withdrawal if re-consenting
        ]);
    }

    /**
     * Record consent history
     */
    protected function recordHistory(
        string $changeType,
        string $source,
        ?string $ip,
        ?string $userAgent,
        array $newValues = []
    ): void {
        CookieConsentHistory::create([
            'cookie_consent_id' => $this->id,
            'previous_analytics' => $this->analytics,
            'previous_marketing' => $this->marketing,
            'previous_preferences' => $this->preferences,
            'new_analytics' => $newValues['new_analytics'] ?? false,
            'new_marketing' => $newValues['new_marketing'] ?? false,
            'new_preferences' => $newValues['new_preferences'] ?? false,
            'change_type' => $changeType,
            'ip_address' => $ip ?? $this->ip_address,
            'user_agent' => $userAgent ?? $this->user_agent,
            'change_source' => $source,
            'changed_at' => now(),
        ]);
    }

    /**
     * Find or create consent for a visitor/customer
     */
    public static function findOrCreateForVisitor(
        int $tenantId,
        string $visitorId,
        ?int $customerId = null
    ): ?self {
        $query = self::where('tenant_id', $tenantId);

        if ($customerId) {
            $query->where('customer_id', $customerId);
        } else {
            $query->where('visitor_id', $visitorId);
        }

        return $query->whereNull('withdrawn_at')
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->latest('consented_at')
            ->first();
    }

    /**
     * Create new consent record
     */
    public static function createConsent(array $data): self
    {
        $consent = self::create(array_merge($data, [
            'necessary' => true, // Always required
            'consented_at' => now(),
            'expires_at' => now()->addDays(self::DEFAULT_EXPIRY_DAYS),
        ]));

        // Record initial consent in history
        CookieConsentHistory::create([
            'cookie_consent_id' => $consent->id,
            'previous_analytics' => null,
            'previous_marketing' => null,
            'previous_preferences' => null,
            'new_analytics' => $consent->analytics,
            'new_marketing' => $consent->marketing,
            'new_preferences' => $consent->preferences,
            'change_type' => 'initial',
            'ip_address' => $consent->ip_address,
            'user_agent' => $consent->user_agent,
            'change_source' => $consent->consent_source,
            'changed_at' => now(),
        ]);

        return $consent;
    }

    /**
     * Link anonymous consent to authenticated customer
     */
    public function linkToCustomer(int $customerId): void
    {
        if ($this->customer_id === null) {
            $this->update(['customer_id' => $customerId]);
        }
    }

    /**
     * Scope: Active consents only
     */
    public function scopeActive($query)
    {
        return $query->whereNull('withdrawn_at')
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    /**
     * Scope: For tenant
     */
    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope: For customer
     */
    public function scopeForCustomer($query, int $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    /**
     * Scope: For visitor
     */
    public function scopeForVisitor($query, string $visitorId)
    {
        return $query->where('visitor_id', $visitorId);
    }

    /**
     * Get consent summary for API response
     */
    public function toConsentArray(): array
    {
        return [
            'necessary' => true,
            'analytics' => $this->analytics,
            'marketing' => $this->marketing,
            'preferences' => $this->preferences,
            'consented_at' => $this->consented_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'is_valid' => $this->isValid(),
            'version' => $this->consent_version,
        ];
    }
}
