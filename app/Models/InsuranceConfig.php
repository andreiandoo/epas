<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InsuranceConfig extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'ti_configs';

    protected $fillable = [
        'tenant_id', 'scope', 'scope_ref', 'pricing_mode', 'value_decimal',
        'min_decimal', 'max_decimal', 'tax_policy', 'scope_level',
        'eligibility', 'terms', 'insurer_provider', 'provider_config',
        'enabled', 'priority',
    ];

    protected $casts = [
        'value_decimal' => 'decimal:2',
        'min_decimal' => 'decimal:2',
        'max_decimal' => 'decimal:2',
        'tax_policy' => 'array',
        'eligibility' => 'array',
        'terms' => 'array',
        'provider_config' => 'array',
        'enabled' => 'boolean',
        'priority' => 'integer',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function calculatePremium(float $ticketPrice): float
    {
        if ($this->pricing_mode === 'fixed') {
            $premium = (float) $this->value_decimal;
        } else {
            $premium = $ticketPrice * ((float) $this->value_decimal / 100);
        }

        if ($this->min_decimal && $premium < $this->min_decimal) {
            $premium = (float) $this->min_decimal;
        }

        if ($this->max_decimal && $premium > $this->max_decimal) {
            $premium = (float) $this->max_decimal;
        }

        return round($premium, 2);
    }

    public function isEligible(array $context): bool
    {
        $rules = $this->eligibility ?? [];

        // Check country eligibility
        if (!empty($rules['countries']) && !empty($context['country'])) {
            if (!in_array($context['country'], $rules['countries'])) {
                return false;
            }
        }

        // Check excluded ticket types
        if (!empty($rules['exclude_ticket_types']) && !empty($context['ticket_type'])) {
            if (in_array($context['ticket_type'], $rules['exclude_ticket_types'])) {
                return false;
            }
        }

        // Check excluded events
        if (!empty($rules['exclude_events']) && !empty($context['event_ref'])) {
            if (in_array($context['event_ref'], $rules['exclude_events'])) {
                return false;
            }
        }

        // Check ticket price range
        if (isset($rules['min_ticket_price']) && $context['ticket_price'] < $rules['min_ticket_price']) {
            return false;
        }

        if (isset($rules['max_ticket_price']) && $context['ticket_price'] > $rules['max_ticket_price']) {
            return false;
        }

        return true;
    }

    public function getTermsUrl(): ?string
    {
        return $this->terms['terms_url'] ?? null;
    }

    public function getDescription(): string
    {
        return $this->terms['description'] ?? 'Protect your ticket with insurance';
    }

    public function getCancellationPolicy(): string
    {
        return $this->terms['cancellation_policy'] ?? 'no_refund';
    }
}
