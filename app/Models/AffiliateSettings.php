<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliateSettings extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'default_commission_type',
        'default_commission_value',
        'cookie_name',
        'cookie_duration_days',
        'allow_self_registration',
        'require_approval',
        'registration_terms',
        'min_withdrawal_amount',
        'currency',
        'payment_methods',
        'withdrawal_processing_days',
        'auto_approve_withdrawals',
        'exclude_taxes',
        'exclude_shipping',
        'prevent_self_purchase',
        'commission_hold_days',
        'program_name',
        'program_description',
        'program_benefits',
        'is_active',
    ];

    protected $casts = [
        'default_commission_value' => 'decimal:2',
        'min_withdrawal_amount' => 'decimal:2',
        'allow_self_registration' => 'boolean',
        'require_approval' => 'boolean',
        'auto_approve_withdrawals' => 'boolean',
        'exclude_taxes' => 'boolean',
        'exclude_shipping' => 'boolean',
        'prevent_self_purchase' => 'boolean',
        'is_active' => 'boolean',
        'payment_methods' => 'array',
        'program_benefits' => 'array',
    ];

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    // ==========================================
    // HELPER METHODS
    // ==========================================

    /**
     * Get or create settings for a tenant
     */
    public static function getOrCreate(int $tenantId): self
    {
        return self::firstOrCreate(
            ['tenant_id' => $tenantId],
            [
                'default_commission_type' => 'percent',
                'default_commission_value' => 10.00,
                'cookie_name' => 'aff_ref',
                'cookie_duration_days' => 90,
                'allow_self_registration' => true,
                'require_approval' => true,
                'min_withdrawal_amount' => 50.00,
                'currency' => 'RON',
                'payment_methods' => ['bank_transfer'],
                'withdrawal_processing_days' => 14,
                'commission_hold_days' => 30,
                'is_active' => true,
            ]
        );
    }

    /**
     * Get available payment methods as options
     */
    public function getPaymentMethodOptions(): array
    {
        $methods = $this->payment_methods ?? ['bank_transfer'];
        $options = [];

        $labels = [
            'bank_transfer' => 'Transfer bancar',
            'paypal' => 'PayPal',
            'revolut' => 'Revolut',
            'wise' => 'Wise',
        ];

        foreach ($methods as $method) {
            $options[$method] = $labels[$method] ?? ucfirst(str_replace('_', ' ', $method));
        }

        return $options;
    }

    /**
     * Format commission display
     */
    public function getFormattedCommission(): string
    {
        if ($this->default_commission_type === 'percent') {
            return number_format($this->default_commission_value, 0) . '%';
        }

        return number_format($this->default_commission_value, 2) . ' ' . $this->currency;
    }

    /**
     * Calculate commission for an amount
     */
    public function calculateCommission(float $amount): float
    {
        if ($this->default_commission_type === 'percent') {
            return round($amount * ($this->default_commission_value / 100), 2);
        }

        return (float) $this->default_commission_value;
    }
}
