<?php

namespace App\Services\Stripe;

use App\Models\Tenant;
use App\Models\PaymentSplit;
use App\Models\DoorSale;
use Stripe\StripeClient;
use Stripe\Exception\ApiErrorException;

class StripeConnectService
{
    protected StripeClient $stripe;

    public function __construct()
    {
        $this->stripe = new StripeClient(config('services.stripe.secret'));
    }

    /**
     * Create Stripe Connect account for tenant
     */
    public function createConnectAccount(Tenant $tenant): array
    {
        try {
            $account = $this->stripe->accounts->create([
                'type' => 'express', // or 'standard' for more control
                'country' => $tenant->country ?? 'RO',
                'email' => $tenant->email,
                'capabilities' => [
                    'card_payments' => ['requested' => true],
                    'transfers' => ['requested' => true],
                ],
                'business_type' => 'company',
                'metadata' => [
                    'tenant_id' => $tenant->id,
                ],
            ]);

            $tenant->update([
                'stripe_connect_id' => $account->id,
                'stripe_connect_meta' => [
                    'created_at' => now()->toIso8601String(),
                    'type' => $account->type,
                ],
            ]);

            return [
                'success' => true,
                'account_id' => $account->id,
            ];
        } catch (ApiErrorException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Generate onboarding link for tenant
     */
    public function createOnboardingLink(Tenant $tenant): array
    {
        if (!$tenant->stripe_connect_id) {
            $result = $this->createConnectAccount($tenant);
            if (!$result['success']) {
                return $result;
            }
        }

        try {
            $accountLink = $this->stripe->accountLinks->create([
                'account' => $tenant->stripe_connect_id,
                'refresh_url' => config('app.url') . '/stripe/connect/refresh?tenant=' . $tenant->id,
                'return_url' => config('app.url') . '/stripe/connect/complete?tenant=' . $tenant->id,
                'type' => 'account_onboarding',
            ]);

            return [
                'success' => true,
                'url' => $accountLink->url,
                'expires_at' => $accountLink->expires_at,
            ];
        } catch (ApiErrorException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check and update account status
     */
    public function refreshAccountStatus(Tenant $tenant): array
    {
        if (!$tenant->stripe_connect_id) {
            return ['success' => false, 'error' => 'No Stripe account'];
        }

        try {
            $account = $this->stripe->accounts->retrieve($tenant->stripe_connect_id);

            $tenant->update([
                'stripe_onboarding_complete' => $account->details_submitted,
                'stripe_charges_enabled' => $account->charges_enabled,
                'stripe_payouts_enabled' => $account->payouts_enabled,
            ]);

            return [
                'success' => true,
                'details_submitted' => $account->details_submitted,
                'charges_enabled' => $account->charges_enabled,
                'payouts_enabled' => $account->payouts_enabled,
            ];
        } catch (ApiErrorException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create payment with automatic split
     */
    public function createSplitPayment(DoorSale $doorSale): array
    {
        $tenant = $doorSale->tenant;

        if (!$tenant->stripe_connect_id || !$tenant->stripe_charges_enabled) {
            return [
                'success' => false,
                'error' => 'Tenant Stripe account not ready',
            ];
        }

        try {
            // Calculate amounts
            $totalAmount = (int) ($doorSale->total * 100); // Convert to cents
            $platformFeeAmount = (int) ($doorSale->platform_fee * 100);

            // Create payment intent with automatic transfer
            $paymentIntent = $this->stripe->paymentIntents->create([
                'amount' => $totalAmount,
                'currency' => strtolower($doorSale->currency),
                'payment_method_types' => ['card_present'],

                // Automatic transfer to tenant's connected account
                'transfer_data' => [
                    'destination' => $tenant->stripe_connect_id,
                ],

                // Platform fee (what platform keeps)
                'application_fee_amount' => $platformFeeAmount,

                'metadata' => [
                    'door_sale_id' => $doorSale->id,
                    'tenant_id' => $tenant->id,
                    'event_id' => $doorSale->event_id,
                ],
            ]);

            return [
                'success' => true,
                'payment_intent_id' => $paymentIntent->id,
                'client_secret' => $paymentIntent->client_secret,
                'amount' => $totalAmount,
                'platform_fee' => $platformFeeAmount,
            ];
        } catch (ApiErrorException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Confirm payment and record split
     */
    public function confirmPayment(string $paymentIntentId, DoorSale $doorSale): array
    {
        try {
            $paymentIntent = $this->stripe->paymentIntents->retrieve($paymentIntentId);

            if ($paymentIntent->status !== 'succeeded') {
                return [
                    'success' => false,
                    'error' => 'Payment not successful',
                    'status' => $paymentIntent->status,
                ];
            }

            // Calculate Stripe fee (approximate: 1.4% + €0.25)
            $totalAmount = $paymentIntent->amount / 100;
            $stripeFee = ($totalAmount * 0.014) + 0.25;
            $platformFee = $paymentIntent->application_fee_amount / 100;
            $tenantAmount = $totalAmount - $platformFee;

            // Record the split
            $split = PaymentSplit::create([
                'tenant_id' => $doorSale->tenant_id,
                'door_sale_id' => $doorSale->id,
                'stripe_payment_intent_id' => $paymentIntentId,
                'stripe_transfer_id' => $paymentIntent->transfer ?? null,
                'total_amount' => $totalAmount,
                'tenant_amount' => $tenantAmount,
                'platform_fee' => $platformFee,
                'stripe_fee' => $stripeFee,
                'currency' => strtoupper($paymentIntent->currency),
                'status' => PaymentSplit::STATUS_COMPLETED,
            ]);

            return [
                'success' => true,
                'split' => $split,
                'transaction_id' => $paymentIntent->latest_charge,
            ];
        } catch (ApiErrorException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Process refund with split reversal
     */
    public function refundPayment(PaymentSplit $split, ?float $amount = null): array
    {
        try {
            $refundAmount = $amount ? (int) ($amount * 100) : null;

            // Create refund (automatically reverses the transfer)
            $refund = $this->stripe->refunds->create([
                'payment_intent' => $split->stripe_payment_intent_id,
                'amount' => $refundAmount,
                'reverse_transfer' => true,
                'refund_application_fee' => true, // Also refund platform fee
            ]);

            if (!$amount || $amount >= $split->total_amount) {
                $split->update(['status' => PaymentSplit::STATUS_REFUNDED]);
            }

            return [
                'success' => true,
                'refund_id' => $refund->id,
                'amount_refunded' => $refund->amount / 100,
            ];
        } catch (ApiErrorException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create Stripe Terminal connection token
     */
    public function createConnectionToken(Tenant $tenant): array
    {
        try {
            $token = $this->stripe->terminal->connectionTokens->create([
                'location' => config('door-sales.stripe.terminal_location'),
            ], [
                'stripe_account' => $tenant->stripe_connect_id,
            ]);

            return [
                'success' => true,
                'secret' => $token->secret,
            ];
        } catch (ApiErrorException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get tenant earnings summary
     */
    public function getEarningsSummary(Tenant $tenant, ?string $period = 'month'): array
    {
        $query = PaymentSplit::forTenant($tenant->id)->completed();

        switch ($period) {
            case 'today':
                $query->whereDate('created_at', today());
                break;
            case 'week':
                $query->where('created_at', '>=', now()->startOfWeek());
                break;
            case 'month':
                $query->where('created_at', '>=', now()->startOfMonth());
                break;
        }

        $splits = $query->get();

        return [
            'total_sales' => $splits->sum('total_amount'),
            'tenant_earnings' => $splits->sum('tenant_amount'),
            'platform_fees' => $splits->sum('platform_fee'),
            'stripe_fees' => $splits->sum('stripe_fee'),
            'transaction_count' => $splits->count(),
            'period' => $period,
        ];
    }

    /**
     * Get Stripe dashboard login link for tenant
     */
    public function getDashboardLink(Tenant $tenant): array
    {
        if (!$tenant->stripe_connect_id) {
            return ['success' => false, 'error' => 'No Stripe account'];
        }

        try {
            $loginLink = $this->stripe->accounts->createLoginLink(
                $tenant->stripe_connect_id
            );

            return [
                'success' => true,
                'url' => $loginLink->url,
            ];
        } catch (ApiErrorException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
