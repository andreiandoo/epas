<?php

namespace App\Services\Payments;

use App\Models\MarketplaceClient;
use App\Models\MarketplaceOrganizer;

/**
 * Compute payment processing fees (Stripe / Netopia / RoPay / etc.)
 * for one checkout.
 *
 * Mental model — F1 spec:
 *   subtotal_cents = ticket_gross + marketplace_commission + insurance
 *   fee_cents      = floor(subtotal * percent_rate / 100) + fixed_cents
 *
 * Whether the fee is added to the customer's total or absorbed by the
 * marketplace commission is governed by two levels:
 *
 *   1. organizer.payment_fee_mode       (override; NULL = inherit)
 *   2. marketplace.payment_fees.pass_to_customer  (default)
 *
 * KILL-SWITCH GUARANTEE for marketplaces that don't opt in:
 *   - If marketplace.payment_fees IS NULL → result is all-zero, passed=false.
 *   - The caller can blindly persist the result on every order; for
 *     non-configured marketplaces this means orders carry zeros and the
 *     existing flow is observationally identical to before.
 *
 * Stateless. Pure compute. No DB writes, no side-effects.
 */
class ProcessingFeeCalculator
{
    /**
     * Shape of the returned array — explicit for IDE + Phpstan friendliness.
     *
     * @return array{
     *   fee_cents: int,
     *   pass_to_customer: bool,
     *   provider: string|null,
     *   percent_rate: float|null,
     *   fixed_cents: int|null,
     *   active: bool
     * }
     */
    public function compute(
        ?MarketplaceClient $client,
        ?MarketplaceOrganizer $organizer,
        string $provider,
        int $subtotalCents
    ): array {
        $zero = [
            'fee_cents'        => 0,
            'pass_to_customer' => false,
            'provider'         => null,
            'percent_rate'     => null,
            'fixed_cents'      => null,
            'active'           => false,
        ];

        // Kill switch — marketplace hasn't opted in.
        if (! $client || ! is_array($client->payment_fees)) {
            return $zero;
        }

        $providers = $client->payment_fees['providers'] ?? [];
        if (! is_array($providers) || ! isset($providers[$provider])) {
            // Marketplace opted in but didn't configure this provider yet
            // (e.g. set up Stripe rates, not Netopia). No fee snapshot.
            return $zero;
        }

        $config = $providers[$provider];
        $percentRate = (float) ($config['percent_rate'] ?? 0);
        $fixedCents  = (int)   ($config['fixed_cents']  ?? 0);

        if ($percentRate <= 0 && $fixedCents <= 0) {
            return $zero;
        }
        if ($subtotalCents <= 0) {
            return $zero;
        }

        // floor() to avoid charging a fraction of a ban — final number is bani-int.
        // We round the percent portion to nearest ban via intval to keep the
        // sum deterministic across PHP/JS rounding modes downstream.
        $percentFee = (int) floor($subtotalCents * $percentRate / 100);
        $feeCents   = $percentFee + $fixedCents;

        // Resolve pass_to_customer in the documented priority order:
        //   organizer override (non-null) → marketplace default → false
        $marketplaceDefault = (bool) ($client->payment_fees['pass_to_customer'] ?? false);
        $passToCustomer     = $this->resolvePassMode($organizer, $marketplaceDefault);

        return [
            'fee_cents'        => $feeCents,
            'pass_to_customer' => $passToCustomer,
            'provider'         => $provider,
            'percent_rate'     => $percentRate,
            'fixed_cents'      => $fixedCents,
            'active'           => true,
        ];
    }

    /**
     * Resolves the pass-to-customer flag against the org override.
     */
    protected function resolvePassMode(?MarketplaceOrganizer $organizer, bool $marketplaceDefault): bool
    {
        if (! $organizer) {
            return $marketplaceDefault;
        }
        return match ($organizer->payment_fee_mode) {
            'pass_to_customer'       => true,
            'absorbed_by_commission' => false,
            default                  => $marketplaceDefault,
        };
    }

    /**
     * Convenience helper for code that only needs to know IF the marketplace
     * opted in (e.g. to decide whether to render a checkout line item).
     */
    public function isActiveFor(?MarketplaceClient $client): bool
    {
        return $client && is_array($client->payment_fees);
    }

    /**
     * Returns the per-provider config dictionary, or [] if not configured.
     * Useful for admin UIs surfacing the current rates.
     *
     * @return array<string, array{percent_rate: float, fixed_cents: int, label?: string}>
     */
    public function providersFor(?MarketplaceClient $client): array
    {
        if (! $client || ! is_array($client->payment_fees)) {
            return [];
        }
        $providers = $client->payment_fees['providers'] ?? [];
        return is_array($providers) ? $providers : [];
    }
}
