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
        'commission_rate' => 'decimal:2',
        'vat_payer' => 'boolean',
        'onboarding_completed' => 'boolean',
    ];

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

    public function microservices(): BelongsToMany
    {
        return $this->belongsToMany(Microservice::class, 'tenant_microservice')
            ->withPivot(['is_active', 'activated_at', 'expires_at', 'configuration'])
            ->withTimestamps();
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function paymentConfigs(): HasMany
    {
        return $this->hasMany(TenantPaymentConfig::class);
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
}
