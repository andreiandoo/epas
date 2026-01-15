<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransportFactory;

class MarketplaceClient extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'domain',
        'api_key',
        'api_secret',
        'contact_email',
        'contact_phone',
        'company_name',
        'status',
        'commission_rate',
        'commission_mode',
        'allowed_tenants',
        'settings',
        'notes',
        'api_calls_count',
        'last_api_call_at',
        'locale',
        'language',
        'smtp_settings',
        'email_settings',
    ];

    protected $casts = [
        'commission_rate' => 'decimal:2',
        'allowed_tenants' => 'array',
        'settings' => 'array',
        'smtp_settings' => 'array',
        'email_settings' => 'array',
        'last_api_call_at' => 'datetime',
    ];

    protected $hidden = [
        'api_secret',
    ];

    /**
     * Boot method to generate API keys
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($client) {
            if (empty($client->api_key)) {
                $client->api_key = 'mpc_' . Str::random(60);
            }
            if (empty($client->api_secret)) {
                $client->api_secret = hash('sha256', Str::random(64));
            }
            if (empty($client->slug)) {
                $client->slug = Str::slug($client->name);
            }
        });
    }

    /**
     * Microservices enabled for this marketplace client
     */
    public function microservices(): BelongsToMany
    {
        return $this->belongsToMany(Microservice::class, 'marketplace_client_microservices')
            ->using(MarketplaceClientMicroservicePivot::class)
            ->withPivot(['status', 'is_active', 'activated_at', 'expires_at', 'settings', 'usage_stats', 'is_default', 'sort_order'])
            ->withTimestamps();
    }

    /**
     * Get the pivot records directly
     */
    public function marketplaceClientMicroservices(): HasMany
    {
        return $this->hasMany(MarketplaceClientMicroservice::class);
    }

    /**
     * Check if marketplace client has a specific microservice enabled
     */
    public function hasMicroservice(string $slug): bool
    {
        return $this->microservices()
            ->where('slug', $slug)
            ->wherePivot('status', 'active')
            ->exists();
    }

    /**
     * Get microservice configuration
     */
    public function getMicroserviceConfig(string $slug): ?array
    {
        $microservice = $this->microservices()
            ->where('slug', $slug)
            ->wherePivot('status', 'active')
            ->first();

        return $microservice?->pivot?->settings;
    }

    /**
     * Get all active payment methods for this marketplace
     */
    public function getActivePaymentMethods()
    {
        return $this->microservices()
            ->where('category', 'payment')
            ->wherePivot('status', 'active')
            ->orderByPivot('sort_order')
            ->get();
    }

    /**
     * Get the default payment method
     */
    public function getDefaultPaymentMethod(): ?Microservice
    {
        return $this->microservices()
            ->where('category', 'payment')
            ->wherePivot('status', 'active')
            ->wherePivot('is_default', true)
            ->first();
    }

    /**
     * Check if a payment method is enabled
     */
    public function hasPaymentMethod(string $slug): bool
    {
        return $this->microservices()
            ->where('slug', $slug)
            ->where('category', 'payment')
            ->wherePivot('status', 'active')
            ->exists();
    }

    /**
     * Get payment method settings
     */
    public function getPaymentMethodSettings(string $slug): ?array
    {
        return $this->getMicroserviceConfig($slug);
    }

    /**
     * Admins for this marketplace client
     */
    public function admins(): HasMany
    {
        return $this->hasMany(MarketplaceAdmin::class);
    }

    /**
     * Organizers for this marketplace client
     */
    public function organizers(): HasMany
    {
        return $this->hasMany(MarketplaceOrganizer::class);
    }

    /**
     * Invoices for this marketplace client
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Tenants this marketplace client can sell tickets for
     */
    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'marketplace_client_tenants')
            ->withPivot(['is_active', 'commission_override'])
            ->withTimestamps();
    }

    /**
     * Get active tenants
     */
    public function activeTenants(): BelongsToMany
    {
        return $this->tenants()->wherePivot('is_active', true);
    }

    /**
     * Check if client can sell tickets for a tenant
     */
    public function canSellForTenant(int $tenantId): bool
    {
        // If allowed_tenants is null, all tenants are allowed
        if (is_null($this->allowed_tenants)) {
            return true;
        }

        // Check if tenant is in allowed list
        if (in_array($tenantId, $this->allowed_tenants)) {
            return true;
        }

        // Check pivot table
        return $this->activeTenants()->where('tenant_id', $tenantId)->exists();
    }

    /**
     * Get commission rate for a specific tenant
     */
    public function getCommissionForTenant(int $tenantId): float
    {
        $pivot = $this->tenants()->where('tenant_id', $tenantId)->first();

        if ($pivot && !is_null($pivot->pivot->commission_override)) {
            return (float) $pivot->pivot->commission_override;
        }

        return (float) $this->commission_rate;
    }

    /**
     * Check if client is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Update last API call timestamp
     */
    public function touchApiCall(): void
    {
        $this->update(['last_api_call_at' => now()]);
    }

    /**
     * Regenerate API secret
     */
    public function regenerateApiSecret(): string
    {
        $newSecret = hash('sha256', Str::random(64));
        $this->update(['api_secret' => $newSecret]);
        return $newSecret;
    }

    /**
     * Regenerate both API key and secret
     */
    public function regenerateApiCredentials(): void
    {
        $this->update([
            'api_key' => 'mpc_' . Str::random(60),
            'api_secret' => hash('sha256', Str::random(64)),
        ]);
    }

    /**
     * Increment API calls count
     */
    public function incrementApiCalls(): void
    {
        $this->increment('api_calls_count');
    }

    /**
     * Get webhook URL if configured
     */
    public function getWebhookUrl(): ?string
    {
        return $this->settings['webhook_url'] ?? null;
    }

    /**
     * Get webhook secret if configured
     */
    public function getWebhookSecret(): ?string
    {
        return $this->settings['webhook_secret'] ?? null;
    }

    /**
     * Scope for active clients
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Get the website folder path
     */
    public function getWebsitePath(): string
    {
        return base_path("marketplace-clients/{$this->slug}");
    }

    /**
     * Email templates for this marketplace
     */
    public function emailTemplates(): HasMany
    {
        return $this->hasMany(MarketplaceEmailTemplate::class);
    }

    /**
     * Email logs for this marketplace
     */
    public function emailLogs(): HasMany
    {
        return $this->hasMany(MarketplaceEmailLog::class);
    }

    /**
     * Contact lists for this marketplace
     */
    public function contactLists(): HasMany
    {
        return $this->hasMany(MarketplaceContactList::class);
    }

    /**
     * Contact tags for this marketplace
     */
    public function contactTags(): HasMany
    {
        return $this->hasMany(MarketplaceContactTag::class);
    }

    /**
     * Newsletters for this marketplace
     */
    public function newsletters(): HasMany
    {
        return $this->hasMany(MarketplaceNewsletter::class);
    }

    /**
     * Refund requests for this marketplace
     */
    public function refundRequests(): HasMany
    {
        return $this->hasMany(MarketplaceRefundRequest::class);
    }

    /**
     * Customers for this marketplace
     */
    public function customers(): HasMany
    {
        return $this->hasMany(MarketplaceCustomer::class);
    }

    /**
     * Check if SMTP is configured
     */
    public function hasSmtpConfigured(): bool
    {
        $smtp = $this->smtp_settings ?? [];
        return !empty($smtp['host']) && !empty($smtp['username']) && !empty($smtp['password']);
    }

    /**
     * Get SMTP transport for sending emails
     */
    public function getSmtpTransport(): ?\Symfony\Component\Mailer\Transport\TransportInterface
    {
        if (!$this->hasSmtpConfigured()) {
            return null;
        }

        $smtp = $this->smtp_settings;

        try {
            $factory = new EsmtpTransportFactory();
            $dsn = new Dsn(
                $smtp['encryption'] === 'ssl' ? 'smtps' : 'smtp',
                $smtp['host'],
                $smtp['username'],
                $smtp['password'],
                $smtp['port'] ?? 587
            );

            return $factory->create($dsn);
        } catch (\Exception $e) {
            \Log::error('Failed to create SMTP transport for marketplace ' . $this->id, [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Get email from address
     */
    public function getEmailFromAddress(): string
    {
        return $this->email_settings['from_email'] ?? $this->contact_email;
    }

    /**
     * Get email from name
     */
    public function getEmailFromName(): string
    {
        return $this->email_settings['from_name'] ?? $this->name;
    }

    /**
     * Get ticket insurance settings for checkout
     *
     * @return array{
     *     is_enabled: bool,
     *     label: string,
     *     description: string,
     *     price: float,
     *     price_type: string,
     *     price_percentage: float,
     *     apply_to: string,
     *     terms_url: string|null,
     *     show_in_checkout: bool,
     *     pre_checked: bool,
     *     currency: string
     * }|null
     */
    public function getTicketInsuranceSettings(): ?array
    {
        if (!$this->hasMicroservice('ticket-insurance')) {
            return null;
        }

        $settings = $this->getMicroserviceConfig('ticket-insurance');

        if (!$settings || !($settings['is_enabled'] ?? false)) {
            return null;
        }

        return [
            'is_enabled' => true,
            'label' => $settings['label'] ?? 'Taxa de retur',
            'description' => $settings['description'] ?? '',
            'price' => (float) ($settings['price'] ?? 5.00),
            'price_type' => $settings['price_type'] ?? 'fixed',
            'price_percentage' => (float) ($settings['price_percentage'] ?? 5),
            'apply_to' => $settings['apply_to'] ?? 'all',
            'terms_url' => $settings['terms_url'] ?? null,
            'show_in_checkout' => $settings['show_in_checkout'] ?? true,
            'pre_checked' => $settings['pre_checked'] ?? false,
            'currency' => $this->currency ?? 'RON',
        ];
    }

    /**
     * Calculate ticket insurance amount for checkout
     *
     * @param float $orderTotal Total order amount
     * @param bool $hasRefundableTickets Whether order contains refundable tickets
     * @return float|null Insurance amount or null if not applicable
     */
    public function calculateTicketInsuranceAmount(float $orderTotal, bool $hasRefundableTickets = true): ?float
    {
        $settings = $this->getTicketInsuranceSettings();

        if (!$settings) {
            return null;
        }

        // Check if should apply based on refundable tickets setting
        if ($settings['apply_to'] === 'refundable_only' && !$hasRefundableTickets) {
            return null;
        }

        if ($settings['price_type'] === 'percentage') {
            return round($orderTotal * ($settings['price_percentage'] / 100), 2);
        }

        return $settings['price'];
    }
}
