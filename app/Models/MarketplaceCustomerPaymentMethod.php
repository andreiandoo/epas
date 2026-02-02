<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceCustomerPaymentMethod extends Model
{
    protected $fillable = [
        'marketplace_client_id',
        'marketplace_customer_id',
        'provider',
        'card_brand',
        'card_last_four',
        'card_exp_month',
        'card_exp_year',
        'cardholder_name',
        'provider_customer_id',
        'provider_payment_method_id',
        'provider_token',
        'label',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected $hidden = [
        'provider_customer_id',
        'provider_payment_method_id',
        'provider_token',
    ];

    // =========================================
    // Relationships
    // =========================================

    public function marketplaceClient(): BelongsTo
    {
        return $this->belongsTo(MarketplaceClient::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(MarketplaceCustomer::class, 'marketplace_customer_id');
    }

    // =========================================
    // Helpers
    // =========================================

    /**
     * Get masked card number for display
     */
    public function getMaskedCardAttribute(): string
    {
        return '**** **** **** ' . ($this->card_last_four ?? '****');
    }

    /**
     * Get expiry date formatted
     */
    public function getExpiryDateAttribute(): ?string
    {
        if ($this->card_exp_month && $this->card_exp_year) {
            return $this->card_exp_month . '/' . substr($this->card_exp_year, -2);
        }
        return null;
    }

    /**
     * Get card brand icon name
     */
    public function getCardIconAttribute(): string
    {
        return match (strtolower($this->card_brand ?? '')) {
            'visa' => 'visa',
            'mastercard' => 'mastercard',
            'amex', 'american express' => 'amex',
            'discover' => 'discover',
            'diners' => 'diners',
            'jcb' => 'jcb',
            default => 'credit-card',
        };
    }

    /**
     * Check if card is expired
     */
    public function isExpired(): bool
    {
        if (!$this->card_exp_month || !$this->card_exp_year) {
            return false;
        }

        $expiry = \Carbon\Carbon::createFromDate(
            (int) $this->card_exp_year,
            (int) $this->card_exp_month,
            1
        )->endOfMonth();

        return $expiry->isPast();
    }

    /**
     * Get display label
     */
    public function getDisplayLabelAttribute(): string
    {
        if ($this->label) {
            return $this->label;
        }

        $brand = ucfirst($this->card_brand ?? 'Card');
        return "{$brand} ending in {$this->card_last_four}";
    }
}
