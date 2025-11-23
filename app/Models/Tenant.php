<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Tenant extends Model
{
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
        // Payment processor
        'payment_processor',
        'payment_processor_mode',
    ];

    protected $casts = [
        'settings' => 'array',
        'features' => 'array',
        'due_at' => 'datetime',
        'billing_starts_at' => 'datetime',
        'next_billing_date' => 'date',
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

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function getActiveMicroservices()
    {
        return $this->tenantMicroservices()->where('is_active', true)->get();
    }
}
