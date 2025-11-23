<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ContractTemplate extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'content',
        'work_method',
        'plan',
        'locale',
        'is_default',
        'is_active',
        'available_variables',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'available_variables' => 'array',
    ];

    /**
     * Default available variables for contract templates
     */
    public static function getDefaultVariables(): array
    {
        return [
            // Tenant Company Details
            '{{tenant_company_name}}' => 'Company legal name',
            '{{tenant_public_name}}' => 'Public display name',
            '{{tenant_cui}}' => 'Tax ID (CUI)',
            '{{tenant_reg_com}}' => 'Trade Register Number',
            '{{tenant_address}}' => 'Street address',
            '{{tenant_city}}' => 'City',
            '{{tenant_state}}' => 'State/County',
            '{{tenant_country}}' => 'Country',
            '{{tenant_vat_payer}}' => 'VAT Payer status (Yes/No)',

            // Tenant Contact Details
            '{{tenant_contact_name}}' => 'Contact full name',
            '{{tenant_contact_first_name}}' => 'Contact first name',
            '{{tenant_contact_last_name}}' => 'Contact last name',
            '{{tenant_contact_email}}' => 'Contact email',
            '{{tenant_contact_phone}}' => 'Contact phone',
            '{{tenant_contact_position}}' => 'Contact position/title',

            // Tenant Banking Details
            '{{tenant_bank_name}}' => 'Bank name',
            '{{tenant_bank_account}}' => 'Bank account (IBAN)',

            // Contract/Business Details
            '{{tenant_work_method}}' => 'Work method (Exclusive/Mixed/Reseller)',
            '{{tenant_plan}}' => 'Commission plan',
            '{{tenant_commission_rate}}' => 'Commission rate percentage',
            '{{tenant_domain}}' => 'Primary domain',

            // Platform Details
            '{{platform_company_name}}' => 'Platform company name',
            '{{platform_cui}}' => 'Platform Tax ID',
            '{{platform_reg_com}}' => 'Platform Trade Register',
            '{{platform_address}}' => 'Platform address',
            '{{platform_bank_name}}' => 'Platform bank name',
            '{{platform_bank_account}}' => 'Platform bank account',

            // Date/Time
            '{{contract_date}}' => 'Contract generation date',
            '{{contract_number}}' => 'Contract number',
            '{{current_year}}' => 'Current year',
        ];
    }

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($template) {
            if (empty($template->slug)) {
                $template->slug = Str::slug($template->name);
            }

            if (empty($template->available_variables)) {
                $template->available_variables = array_keys(self::getDefaultVariables());
            }
        });

        static::saving(function ($template) {
            // Ensure only one default template per work_method/plan combination
            if ($template->is_default) {
                static::where('id', '!=', $template->id ?? 0)
                    ->where('work_method', $template->work_method)
                    ->where('plan', $template->plan)
                    ->update(['is_default' => false]);
            }
        });
    }

    /**
     * Get tenants using this template
     */
    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }

    /**
     * Find the best matching template for a tenant
     */
    public static function findForTenant(Tenant $tenant): ?self
    {
        $locale = $tenant->locale ?? 'en';

        // First try to find exact match for work_method, plan, and locale
        $template = static::where('is_active', true)
            ->where('work_method', $tenant->work_method)
            ->where('plan', $tenant->plan)
            ->where('locale', $locale)
            ->first();

        if ($template) {
            return $template;
        }

        // Try exact match without locale
        $template = static::where('is_active', true)
            ->where('work_method', $tenant->work_method)
            ->where('plan', $tenant->plan)
            ->where(function ($q) use ($locale) {
                $q->where('locale', 'en')->orWhereNull('locale');
            })
            ->first();

        if ($template) {
            return $template;
        }

        // Try to find match for just work_method with locale
        $template = static::where('is_active', true)
            ->where('work_method', $tenant->work_method)
            ->whereNull('plan')
            ->first();

        if ($template) {
            return $template;
        }

        // Fall back to default template
        return static::where('is_active', true)
            ->where('is_default', true)
            ->first();
    }

    /**
     * Process template content with tenant variables
     */
    public function processContent(Tenant $tenant, ?Setting $settings = null): string
    {
        $settings = $settings ?? Setting::first();
        $content = $this->content;

        $variables = [
            // Tenant Company Details
            '{{tenant_company_name}}' => $tenant->company_name ?? $tenant->name,
            '{{tenant_public_name}}' => $tenant->public_name ?? $tenant->name,
            '{{tenant_cui}}' => $tenant->cui ?? '',
            '{{tenant_reg_com}}' => $tenant->reg_com ?? '',
            '{{tenant_address}}' => $tenant->address ?? '',
            '{{tenant_city}}' => $tenant->city ?? '',
            '{{tenant_state}}' => $tenant->state ?? '',
            '{{tenant_country}}' => $tenant->country ?? '',
            '{{tenant_vat_payer}}' => $tenant->vat_payer ? 'Yes' : 'No',

            // Tenant Contact Details
            '{{tenant_contact_name}}' => trim(($tenant->contact_first_name ?? '') . ' ' . ($tenant->contact_last_name ?? '')),
            '{{tenant_contact_first_name}}' => $tenant->contact_first_name ?? '',
            '{{tenant_contact_last_name}}' => $tenant->contact_last_name ?? '',
            '{{tenant_contact_email}}' => $tenant->contact_email ?? '',
            '{{tenant_contact_phone}}' => $tenant->contact_phone ?? '',
            '{{tenant_contact_position}}' => $tenant->contact_position ?? '',

            // Tenant Banking Details
            '{{tenant_bank_name}}' => $tenant->bank_name ?? '',
            '{{tenant_bank_account}}' => $tenant->bank_account ?? '',

            // Contract/Business Details
            '{{tenant_work_method}}' => $this->formatWorkMethod($tenant->work_method),
            '{{tenant_plan}}' => $tenant->plan ?? '',
            '{{tenant_commission_rate}}' => $tenant->commission_rate ?? '',
            '{{tenant_domain}}' => $tenant->domain ?? '',

            // Platform Details
            '{{platform_company_name}}' => $settings?->company_name ?? '',
            '{{platform_cui}}' => $settings?->cui ?? '',
            '{{platform_reg_com}}' => $settings?->reg_com ?? '',
            '{{platform_address}}' => $this->formatPlatformAddress($settings),
            '{{platform_bank_name}}' => $settings?->bank_name ?? '',
            '{{platform_bank_account}}' => $settings?->bank_account ?? '',

            // Date/Time
            '{{contract_date}}' => now()->format('d.m.Y'),
            '{{contract_number}}' => $tenant->contract_number ?? $this->generateContractNumber($tenant),
            '{{current_year}}' => now()->year,
        ];

        return str_replace(array_keys($variables), array_values($variables), $content);
    }

    /**
     * Format work method for display
     */
    protected function formatWorkMethod(?string $workMethod): string
    {
        return match ($workMethod) {
            'exclusive' => 'Exclusive (1%)',
            'mixed' => 'Mixed (2%)',
            'reseller' => 'Reseller (3%)',
            default => $workMethod ?? '',
        };
    }

    /**
     * Format platform address
     */
    protected function formatPlatformAddress(?Setting $settings): string
    {
        if (!$settings) {
            return '';
        }

        $parts = array_filter([
            $settings->address,
            $settings->city,
            $settings->state,
            $settings->postal_code,
            $settings->country,
        ]);

        return implode(', ', $parts);
    }

    /**
     * Generate a contract number for the tenant
     */
    protected function generateContractNumber(Tenant $tenant): string
    {
        return 'CTR-' . now()->year . '-' . str_pad($tenant->id, 5, '0', STR_PAD_LEFT);
    }
}
