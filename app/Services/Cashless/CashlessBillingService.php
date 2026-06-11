<?php

namespace App\Services\Cashless;

use App\Models\FestivalEdition;
use App\Models\Invoice;
use App\Models\Microservice;
use App\Models\TenantMicroservice;

class CashlessBillingService
{
    /**
     * Resolve cashless pricing for a festival edition.
     * Priority: TenantMicroservice.settings > Microservice.metadata
     */
    public function getPricing(FestivalEdition $edition): array
    {
        $defaults = [
            'activation_fee' => 0,
            'commission_rate' => 0,
            'currency' => 'EUR',
        ];

        $microservice = Microservice::where('slug', 'cashless')->first();
        if (! $microservice) {
            return $defaults;
        }

        // Global defaults from microservice metadata
        $global = $microservice->metadata['cashless_pricing'] ?? [];

        // Per-tenant override
        $tenantMs = TenantMicroservice::where('tenant_id', $edition->tenant_id)
            ->where('microservice_id', $microservice->id)
            ->active()
            ->first();

        $override = $tenantMs?->settings['cashless_pricing'] ?? [];

        return array_merge($defaults, $global, array_filter($override));
    }

    /**
     * Generate activation invoice (one-time fee per edition).
     * Idempotent: returns null if already invoiced.
     */
    public function generateActivationInvoice(FestivalEdition $edition): ?Invoice
    {
        if ($edition->hasCashlessActivationInvoice()) {
            return null;
        }

        $pricing = $this->getPricing($edition);
        $activationFee = (float) ($pricing['activation_fee'] ?? 0);

        if ($activationFee <= 0) {
            return null;
        }

        $currency = $pricing['currency'] ?? 'EUR';
        $vatRate = $currency === 'RON' ? 19.00 : 0.00;
        $vatAmount = round($activationFee * ($vatRate / 100), 2);

        return Invoice::create([
            'tenant_id' => $edition->tenant_id,
            'type' => 'proforma',
            'description' => "Taxa activare Cashless - {$edition->name} ({$edition->year})",
            'issue_date' => now(),
            'period_start' => $edition->start_date,
            'period_end' => $edition->end_date,
            'due_date' => now()->addDays(14),
            'subtotal' => $activationFee,
            'vat_rate' => $vatRate,
            'vat_amount' => $vatAmount,
            'amount' => $activationFee + $vatAmount,
            'currency' => $currency,
            'status' => 'outstanding',
            'meta' => [
                'type' => 'cashless_activation',
                'festival_edition_id' => $edition->id,
                'activation_fee' => $activationFee,
            ],
        ]);
    }

    /**
     * Generate completion invoice (percentage of cashless sales).
     * Idempotent: returns null if already invoiced.
     */
    public function generateCompletionInvoice(FestivalEdition $edition): ?Invoice
    {
        if ($edition->hasCashlessCompletionInvoice()) {
            return null;
        }

        $pricing = $this->getPricing($edition);
        $commissionRate = (float) ($pricing['commission_rate'] ?? 0);

        if ($commissionRate <= 0) {
            return null;
        }

        $cashlessRevenueCents = $edition->totalCashlessRevenueCents();

        if ($cashlessRevenueCents <= 0) {
            return null;
        }

        $cashlessRevenue = $cashlessRevenueCents / 100;
        $subtotal = round($cashlessRevenue * ($commissionRate / 100), 2);
        $currency = $pricing['currency'] ?? 'EUR';
        $vatRate = $currency === 'RON' ? 19.00 : 0.00;
        $vatAmount = round($subtotal * ($vatRate / 100), 2);

        return Invoice::create([
            'tenant_id' => $edition->tenant_id,
            'type' => 'proforma',
            'description' => "Comision Cashless ({$commissionRate}%) - {$edition->name} ({$edition->year})",
            'issue_date' => now(),
            'period_start' => $edition->start_date,
            'period_end' => $edition->end_date,
            'due_date' => now()->addDays(14),
            'subtotal' => $subtotal,
            'vat_rate' => $vatRate,
            'vat_amount' => $vatAmount,
            'amount' => $subtotal + $vatAmount,
            'currency' => $currency,
            'status' => 'outstanding',
            'meta' => [
                'type' => 'cashless_completion',
                'festival_edition_id' => $edition->id,
                'total_cashless_revenue_cents' => $cashlessRevenueCents,
                'commission_rate' => $commissionRate,
                'items' => [
                    [
                        'description' => "Comision {$commissionRate}% din vânzări cashless ({$edition->name})",
                        'quantity' => 1,
                        'price' => $subtotal,
                        'total' => $subtotal,
                    ],
                ],
            ],
        ]);
    }
}
