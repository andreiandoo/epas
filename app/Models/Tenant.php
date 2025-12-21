<?php

namespace App\Models;

use App\Models\Marketplace\MarketplaceOrganizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Tenant extends Model
{
    /**
     * Tenant type constants
     */
    public const TYPE_STANDARD = 'standard';
    public const TYPE_MARKETPLACE = 'marketplace';

    /**
     * Marketplace commission type constants
     */
    public const COMMISSION_PERCENT = 'percent';
    public const COMMISSION_FIXED = 'fixed';
    public const COMMISSION_BOTH = 'both';

    /**
     * Tixello platform fee (always 1%)
     */
    public const TIXELLO_PLATFORM_FEE = 0.01;

    protected $fillable = [
        'name',
        'public_name',
        'owner_id',
        'slug',
        'domain',
        'status',
        'plan',
        'type',
        'due_at',
        'commission_mode',
        'commission_rate',
        'settings',
        'ticket_terms',
        'features',
        'use_core_smtp',
        // Company details
        'company_name',
        'cui',
        'reg_com',
        'contract_number',
        'contract_file',
        'contract_generated_at',
        'contract_sent_at',
        'contract_template_id',
        'contract_status',
        'contract_viewed_at',
        'contract_signed_at',
        'contract_signature_ip',
        'contract_signature_data',
        'contract_renewal_date',
        'contract_auto_renew',
        'bank_account',
        'bank_name',
        'address',
        'city',
        'state',
        'country',
        // Billing
        'billing_starts_at',
        'billing_cycle_days',
        'next_billing_date',
        'last_billing_date',
        // Onboarding
        'locale',
        'vat_payer',
        'estimated_monthly_tickets',
        'work_method',
        'onboarding_completed',
        'onboarding_completed_at',
        'onboarding_step',
        'contact_first_name',
        'contact_last_name',
        'contact_email',
        'contact_phone',
        'contact_position',
        'postal_code',
        'website',
        // Payment processor
        'payment_processor',
        'payment_processor_mode',
        'currency',
        // Marketplace fields
        'tenant_type',
        'marketplace_commission_type',
        'marketplace_commission_percent',
        'marketplace_commission_fixed',
        'marketplace_settings',
    ];

    protected $casts = [
        'settings' => 'array',
        'payment_credentials' => 'array',
        'features' => 'array',
        'marketplace_settings' => 'array',
        'marketplace_commission_percent' => 'decimal:2',
        'marketplace_commission_fixed' => 'decimal:2',
        'due_at' => 'datetime',
        'billing_starts_at' => 'datetime',
        'next_billing_date' => 'date',
        'last_billing_date' => 'date',
        'onboarding_completed_at' => 'datetime',
        'contract_generated_at' => 'datetime',
        'contract_sent_at' => 'datetime',
        'contract_viewed_at' => 'datetime',
        'contract_signed_at' => 'datetime',
        'contract_renewal_date' => 'date',
        'contract_auto_renew' => 'boolean',
        'commission_rate' => 'decimal:2',
        'vat_payer' => 'boolean',
        'onboarding_completed' => 'boolean',
        'use_core_smtp' => 'boolean',
    ];

    public function contractTemplate(): BelongsTo
    {
        return $this->belongsTo(ContractTemplate::class);
    }

    public function contractVersions(): HasMany
    {
        return $this->hasMany(ContractVersion::class)->orderByDesc('version_number');
    }

    /**
     * Get the latest contract version
     */
    public function latestContractVersion()
    {
        return $this->hasOne(ContractVersion::class)->latestOfMany('version_number');
    }

    public function customVariables(): HasMany
    {
        return $this->hasMany(TenantCustomVariable::class);
    }

    public function amendments(): HasMany
    {
        return $this->hasMany(ContractAmendment::class)->orderByDesc('created_at');
    }

    /**
     * Get custom variable value by name
     */
    public function getCustomVariableValue(string $name): ?string
    {
        $variable = ContractCustomVariable::where('name', $name)->first();
        if (!$variable) {
            return null;
        }

        $tenantValue = $this->customVariables()
            ->where('contract_custom_variable_id', $variable->id)
            ->first();

        return $tenantValue?->value ?? $variable->default_value;
    }

    /**
     * Get contract status color for display
     */
    public function getContractStatusColor(): string
    {
        return match ($this->contract_status) {
            'draft' => 'gray',
            'generated' => 'info',
            'sent' => 'warning',
            'viewed' => 'primary',
            'signed' => 'success',
            default => 'gray',
        };
    }

    /**
     * Check if contract needs renewal
     */
    public function contractNeedsRenewal(): bool
    {
        if (!$this->contract_renewal_date) {
            return false;
        }

        return $this->contract_renewal_date->isPast() || $this->contract_renewal_date->isToday();
    }

    public function customers(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Customer::class, 'customer_tenant')->withTimestamps();
    }

    public function events(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function orders(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function domains(): HasMany
    {
        return $this->hasMany(Domain::class);
    }

    public function packages(): HasMany
    {
        return $this->hasMany(TenantPackage::class);
    }

    public function verifications(): HasMany
    {
        return $this->hasMany(DomainVerification::class);
    }

    public function microservices(): BelongsToMany
    {
        return $this->belongsToMany(Microservice::class, 'tenant_microservice')
            ->using(TenantMicroservicePivot::class)
            ->withPivot(['is_active', 'activated_at', 'expires_at', 'configuration'])
            ->withTimestamps();
    }

    public function tenantMicroservices(): HasMany
    {
        return $this->hasMany(TenantMicroservice::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function paymentConfigs(): HasMany
    {
        return $this->hasMany(TenantPaymentConfig::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Get active payment config for the selected processor
     */
    public function activePaymentConfig(): ?TenantPaymentConfig
    {
        if (!$this->payment_processor) {
            return null;
        }

        return $this->paymentConfigs()
            ->where('processor', $this->payment_processor)
            ->where('is_active', true)
            ->first();
    }

    public function trackingIntegrations(): HasMany
    {
        return $this->hasMany(TrackingIntegration::class);
    }

    /**
     * Get enabled tracking integrations
     */
    public function activeTrackingIntegrations(): HasMany
    {
        return $this->trackingIntegrations()->where('enabled', true);
    }

    public function ticketTemplates(): HasMany
    {
        return $this->hasMany(TicketTemplate::class);
    }

    /**
     * Get active ticket templates
     */
    public function activeTicketTemplates(): HasMany
    {
        return $this->ticketTemplates()->where('status', 'active');
    }

    /**
     * Get default ticket template
     */
    public function defaultTicketTemplate(): ?TicketTemplate
    {
        return $this->ticketTemplates()
            ->where('is_default', true)
            ->where('status', 'active')
            ->first();
    }

    public function inviteBatches(): HasMany
    {
        return $this->hasMany(InviteBatch::class);
    }

    public function invites(): HasMany
    {
        return $this->hasMany(Invite::class);
    }

    /**
     * Get active invite batches (not cancelled)
     */
    public function activeInviteBatches(): HasMany
    {
        return $this->inviteBatches()->where('status', '!=', 'cancelled');
    }

    public function insuranceConfigs(): HasMany
    {
        return $this->hasMany(InsuranceConfig::class);
    }

    public function insurancePolicies(): HasMany
    {
        return $this->hasMany(InsurancePolicy::class);
    }

    public function pages(): HasMany
    {
        return $this->hasMany(TenantPage::class);
    }

    public function venues(): HasMany
    {
        return $this->hasMany(Venue::class);
    }

    /**
     * Check if tenant owns any venues
     */
    public function ownsVenues(): bool
    {
        return $this->venues()->exists();
    }

    /**
     * Get events happening at venues owned by this tenant (but created by other tenants)
     */
    public function hostedEvents(): \Illuminate\Database\Eloquent\Builder
    {
        return Event::whereHas('venue', function ($query) {
            $query->where('tenant_id', $this->id);
        })->where('tenant_id', '!=', $this->id);
    }

    protected static function booted(): void
    {
        static::saving(function (Tenant $tenant) {
            // Auto-calculate next_billing_date if empty
            if (empty($tenant->next_billing_date)) {
                $cycleDays = $tenant->billing_cycle_days ?? 30;

                // Use last_billing_date if available, otherwise billing_starts_at
                if ($tenant->last_billing_date) {
                    $tenant->next_billing_date = $tenant->last_billing_date->copy()->addDays($cycleDays);
                } elseif ($tenant->billing_starts_at) {
                    $tenant->next_billing_date = $tenant->billing_starts_at->copy()->addDays($cycleDays);
                }
            }
        });
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Calculate next billing date based on last billing or billing start
     */
    public function calculateNextBillingDate(): ?\Carbon\Carbon
    {
        $cycleDays = $this->billing_cycle_days ?? 30;

        if ($this->last_billing_date) {
            return $this->last_billing_date->copy()->addDays($cycleDays);
        }

        if ($this->billing_starts_at) {
            return $this->billing_starts_at->copy()->addDays($cycleDays);
        }

        return null;
    }

    /**
     * Advance billing cycle after invoice generation
     */
    public function advanceBillingCycle(): void
    {
        $this->last_billing_date = $this->next_billing_date ?? now();
        $this->next_billing_date = $this->calculateNextBillingDate();
        $this->save();
    }

    /**
     * Get current billing period start date
     */
    public function getCurrentPeriodStart(): ?\Carbon\Carbon
    {
        if ($this->last_billing_date) {
            return $this->last_billing_date->copy();
        }

        if ($this->billing_starts_at) {
            return $this->billing_starts_at->copy();
        }

        return null;
    }

    /**
     * Get current billing period end date
     */
    public function getCurrentPeriodEnd(): ?\Carbon\Carbon
    {
        return $this->next_billing_date?->copy();
    }

    /**
     * Check if billing is overdue
     */
    public function isBillingOverdue(): bool
    {
        if (!$this->next_billing_date) {
            return false;
        }

        return $this->next_billing_date->isPast();
    }

    public function getActiveMicroservices()
    {
        return $this->tenantMicroservices()->where('is_active', true)->get();
    }

    /**
     * Check if tenant has an active microservice by slug
     */
    public function hasMicroservice(string $slug): bool
    {
        return $this->microservices()
            ->where('slug', $slug)
            ->wherePivot('is_active', true)
            ->exists();
    }

    /**
     * Get microservice configuration by slug
     */
    public function getMicroserviceConfig(string $slug): ?array
    {
        $microservice = $this->microservices()
            ->where('slug', $slug)
            ->wherePivot('is_active', true)
            ->first();

        return $microservice?->pivot?->configuration;
    }

    // =========================================================================
    // MARKETPLACE RELATIONSHIPS
    // =========================================================================

    /**
     * Get the organizers for this marketplace tenant.
     */
    public function organizers(): HasMany
    {
        return $this->hasMany(MarketplaceOrganizer::class);
    }

    /**
     * Get active organizers.
     */
    public function activeOrganizers(): HasMany
    {
        return $this->organizers()->where('status', 'active');
    }

    /**
     * Get pending approval organizers.
     */
    public function pendingOrganizers(): HasMany
    {
        return $this->organizers()->where('status', 'pending_approval');
    }

    // =========================================================================
    // MARKETPLACE TYPE HELPERS
    // =========================================================================

    /**
     * Check if this is a marketplace tenant.
     */
    public function isMarketplace(): bool
    {
        return $this->tenant_type === self::TYPE_MARKETPLACE;
    }

    /**
     * Check if this is a standard tenant.
     */
    public function isStandardTenant(): bool
    {
        return $this->tenant_type === self::TYPE_STANDARD || $this->tenant_type === null;
    }

    /**
     * Scope to get only marketplace tenants.
     */
    public function scopeMarketplaces($query)
    {
        return $query->where('tenant_type', self::TYPE_MARKETPLACE);
    }

    /**
     * Scope to get only standard tenants.
     */
    public function scopeStandard($query)
    {
        return $query->where(function ($q) {
            $q->where('tenant_type', self::TYPE_STANDARD)
              ->orWhereNull('tenant_type');
        });
    }

    // =========================================================================
    // MARKETPLACE COMMISSION CALCULATION
    // =========================================================================

    /**
     * Calculate marketplace commission for an order.
     *
     * Commission flow:
     * 1. Tixello takes 1% (always, from total)
     * 2. Marketplace takes their configured commission (from remainder)
     * 3. Organizer gets the rest
     *
     * @param float $orderTotal Total order amount
     * @param MarketplaceOrganizer|null $organizer Optional organizer for override
     * @return array Commission breakdown
     */
    public function calculateMarketplaceCommission(float $orderTotal, ?MarketplaceOrganizer $organizer = null): array
    {
        // Tixello always takes 1%
        $tixelloCommission = round($orderTotal * self::TIXELLO_PLATFORM_FEE, 2);
        $afterTixello = $orderTotal - $tixelloCommission;

        // If not a marketplace or no commission settings, return simple breakdown
        if (!$this->isMarketplace()) {
            return [
                'order_total' => $orderTotal,
                'tixello_commission' => $tixelloCommission,
                'marketplace_commission' => 0,
                'organizer_revenue' => 0,
            ];
        }

        // Get commission settings (organizer override or marketplace default)
        $commissionType = $organizer?->commission_type ?? $this->marketplace_commission_type;
        $commissionPercent = $organizer?->commission_percent ?? $this->marketplace_commission_percent ?? 0;
        $commissionFixed = $organizer?->commission_fixed ?? $this->marketplace_commission_fixed ?? 0;

        // Calculate marketplace commission
        $marketplaceCommission = 0;

        switch ($commissionType) {
            case self::COMMISSION_PERCENT:
                $marketplaceCommission = round($afterTixello * ((float) $commissionPercent / 100), 2);
                break;

            case self::COMMISSION_FIXED:
                $marketplaceCommission = (float) $commissionFixed;
                break;

            case self::COMMISSION_BOTH:
                $percentAmount = round($afterTixello * ((float) $commissionPercent / 100), 2);
                $marketplaceCommission = $percentAmount + (float) $commissionFixed;
                break;

            default:
                $marketplaceCommission = 0;
        }

        // Ensure marketplace commission doesn't exceed available amount
        $marketplaceCommission = min($marketplaceCommission, $afterTixello);

        // Organizer gets the rest
        $organizerRevenue = max(0, $afterTixello - $marketplaceCommission);

        return [
            'order_total' => $orderTotal,
            'tixello_commission' => $tixelloCommission,
            'marketplace_commission' => round($marketplaceCommission, 2),
            'organizer_revenue' => round($organizerRevenue, 2),
        ];
    }

    /**
     * Get formatted commission description.
     */
    public function getMarketplaceCommissionDescription(): string
    {
        if (!$this->marketplace_commission_type) {
            return 'Not configured';
        }

        switch ($this->marketplace_commission_type) {
            case self::COMMISSION_PERCENT:
                return $this->marketplace_commission_percent . '%';

            case self::COMMISSION_FIXED:
                return number_format($this->marketplace_commission_fixed, 2) . ' ' . ($this->currency ?? 'RON');

            case self::COMMISSION_BOTH:
                return $this->marketplace_commission_percent . '% + ' .
                       number_format($this->marketplace_commission_fixed, 2) . ' ' . ($this->currency ?? 'RON');

            default:
                return 'Not configured';
        }
    }

    /**
     * Get a marketplace setting value.
     */
    public function getMarketplaceSetting(string $key, $default = null)
    {
        return data_get($this->marketplace_settings, $key, $default);
    }

    /**
     * Set a marketplace setting value.
     */
    public function setMarketplaceSetting(string $key, $value): void
    {
        $settings = $this->marketplace_settings ?? [];
        data_set($settings, $key, $value);
        $this->marketplace_settings = $settings;
    }
}
