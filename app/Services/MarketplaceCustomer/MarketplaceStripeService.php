<?php

namespace App\Services\MarketplaceCustomer;

use App\Models\MarketplaceClient;
use App\Models\MarketplaceCustomer;
use App\Models\MarketplaceCustomerPaymentMethod;
use Illuminate\Support\Facades\Log;
use Stripe\Customer as StripeCustomer;
use Stripe\PaymentMethod as StripePaymentMethod;
use Stripe\SetupIntent as StripeSetupIntent;
use Stripe\Stripe;
use Stripe\StripeClient;

/**
 * Stripe wrapper for saving / listing / removing customer payment methods.
 *
 * Per-marketplace_client credentials live in
 * `marketplace_clients.settings.stripe.{secret_key, publishable_key}` when
 * present; otherwise falls back to `config('services.stripe.secret' / '.publishable')`.
 *
 * We DO NOT charge cards here — that flow still goes through CheckoutController.
 * This service only:
 *   - creates / fetches a Stripe Customer (`MarketplaceCustomer` ↔ Stripe id
 *     mapping stored on the first MarketplaceCustomerPaymentMethod row)
 *   - issues SetupIntents (no immediate charge — card saved for future)
 *   - syncs Stripe PaymentMethod metadata back into our DB
 *   - deletes / sets default
 */
class MarketplaceStripeService
{
    /**
     * Resolve Stripe credentials for a given marketplace client.
     * Per-client override → global fallback.
     */
    public function credentialsFor(MarketplaceClient $client): array
    {
        $settings = (array) ($client->settings ?? []);
        $stripe   = (array) ($settings['stripe'] ?? []);

        return [
            'secret_key'      => $stripe['secret_key']      ?? config('services.stripe.secret'),
            'publishable_key' => $stripe['publishable_key'] ?? config('services.stripe.publishable'),
        ];
    }

    public function isConfigured(MarketplaceClient $client): bool
    {
        return ! empty($this->credentialsFor($client)['secret_key']);
    }

    public function publishableKey(MarketplaceClient $client): ?string
    {
        return $this->credentialsFor($client)['publishable_key'];
    }

    protected function client(MarketplaceClient $client): StripeClient
    {
        $creds = $this->credentialsFor($client);
        if (empty($creds['secret_key'])) {
            throw new \RuntimeException('Stripe is not configured for this marketplace.');
        }
        return new StripeClient($creds['secret_key']);
    }

    /**
     * Return the Stripe customer id for this user, creating one on Stripe's
     * side if we've never seen them. The id is persisted on the
     * MarketplaceCustomerPaymentMethod rows (so dropping all cards clears it
     * naturally — Stripe customer is "free" to re-create).
     */
    public function ensureStripeCustomer(MarketplaceCustomer $customer): string
    {
        $client = $customer->marketplaceClient;
        if (! $client) {
            throw new \RuntimeException('Customer has no marketplace client.');
        }

        $existing = MarketplaceCustomerPaymentMethod::where('marketplace_customer_id', $customer->id)
            ->where('provider', 'stripe')
            ->whereNotNull('provider_customer_id')
            ->first();

        if ($existing && $existing->provider_customer_id) {
            return $existing->provider_customer_id;
        }

        $stripe = $this->client($client);
        $stripeCustomer = $stripe->customers->create([
            'email' => $customer->email,
            'name'  => trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? '')) ?: null,
            'metadata' => [
                'marketplace_client_id'   => $client->id,
                'marketplace_customer_id' => $customer->id,
            ],
        ]);

        return $stripeCustomer->id;
    }

    /**
     * Create a SetupIntent for the customer — returns the client_secret the
     * front-end uses with stripe.confirmCardSetup(). No money moves.
     */
    public function createSetupIntent(MarketplaceCustomer $customer): array
    {
        $client = $customer->marketplaceClient;
        $stripeCustomerId = $this->ensureStripeCustomer($customer);

        $stripe = $this->client($client);
        $intent = $stripe->setupIntents->create([
            'customer'             => $stripeCustomerId,
            'payment_method_types' => ['card'],
            'usage'                => 'off_session',
            'metadata' => [
                'marketplace_client_id'   => $client->id,
                'marketplace_customer_id' => $customer->id,
            ],
        ]);

        return [
            'client_secret'        => $intent->client_secret,
            'setup_intent_id'      => $intent->id,
            'stripe_customer_id'   => $stripeCustomerId,
            'publishable_key'      => $this->publishableKey($client),
        ];
    }

    /**
     * Read a confirmed SetupIntent's payment_method, persist it locally as
     * a MarketplaceCustomerPaymentMethod row, and mark default if first card.
     */
    public function attachConfirmedSetupIntent(MarketplaceCustomer $customer, string $setupIntentId): MarketplaceCustomerPaymentMethod
    {
        $client = $customer->marketplaceClient;
        $stripe = $this->client($client);

        $intent = $stripe->setupIntents->retrieve($setupIntentId, ['expand' => ['payment_method']]);

        if ($intent->status !== 'succeeded') {
            throw new \RuntimeException('SetupIntent is not in a successful state (status=' . $intent->status . ').');
        }

        $pm = $intent->payment_method;
        if (is_string($pm)) {
            $pm = $stripe->paymentMethods->retrieve($pm);
        }
        if (! $pm || empty($pm->card)) {
            throw new \RuntimeException('SetupIntent has no card payment method attached.');
        }

        // De-dupe — if we already saved this payment method id, return it
        $existing = MarketplaceCustomerPaymentMethod::where('marketplace_customer_id', $customer->id)
            ->where('provider_payment_method_id', $pm->id)
            ->first();
        if ($existing) {
            return $existing;
        }

        $isFirst = MarketplaceCustomerPaymentMethod::where('marketplace_customer_id', $customer->id)
            ->where('is_active', true)
            ->count() === 0;

        return MarketplaceCustomerPaymentMethod::create([
            'marketplace_client_id'      => $client->id,
            'marketplace_customer_id'    => $customer->id,
            'provider'                   => 'stripe',
            'card_brand'                 => $pm->card->brand ?? null,
            'card_last_four'             => $pm->card->last4 ?? null,
            'card_exp_month'             => $pm->card->exp_month ?? null,
            'card_exp_year'              => $pm->card->exp_year ?? null,
            'cardholder_name'            => $pm->billing_details->name ?? null,
            'provider_customer_id'       => $intent->customer,
            'provider_payment_method_id' => $pm->id,
            'label'                      => null,
            'is_default'                 => $isFirst,
            'is_active'                  => true,
        ]);
    }

    /**
     * Detach a saved card from Stripe and remove the local row.
     */
    public function deleteMethod(MarketplaceCustomerPaymentMethod $method): void
    {
        $client = $method->marketplaceClient ?? MarketplaceClient::find($method->marketplace_client_id);
        if ($client && $method->provider_payment_method_id) {
            try {
                $this->client($client)->paymentMethods->detach($method->provider_payment_method_id);
            } catch (\Throwable $e) {
                // log but don't block — the local row should still be removed
                Log::warning('Stripe paymentMethods.detach failed', [
                    'method_id' => $method->id,
                    'pm_id'     => $method->provider_payment_method_id,
                    'error'     => $e->getMessage(),
                ]);
            }
        }
        $wasDefault = $method->is_default;
        $customerId = $method->marketplace_customer_id;
        $method->delete();

        if ($wasDefault) {
            // Promote the next active card to default
            $next = MarketplaceCustomerPaymentMethod::where('marketplace_customer_id', $customerId)
                ->where('is_active', true)
                ->orderByDesc('created_at')
                ->first();
            if ($next) {
                $next->update(['is_default' => true]);
            }
        }
    }

    /**
     * Set a specific saved card as default (clearing the flag on others).
     */
    public function setDefault(MarketplaceCustomerPaymentMethod $method): void
    {
        MarketplaceCustomerPaymentMethod::where('marketplace_customer_id', $method->marketplace_customer_id)
            ->update(['is_default' => false]);

        $method->update(['is_default' => true]);
    }
}
