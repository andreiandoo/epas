<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Customer;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\MarketplaceCustomer;
use App\Models\MarketplaceCustomerPaymentMethod;
use App\Services\MarketplaceCustomer\MarketplaceStripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Saved Stripe cards for /cont/setari → Plăți tab.
 *
 * Card-saving flow (no money moves):
 *   POST /customer/payment-methods/setup-intent → returns client_secret +
 *        publishable_key. Front-end calls stripe.confirmCardSetup() with it.
 *   POST /customer/payment-methods/confirm     → body { setup_intent_id }
 *        — backend reads the confirmed intent and persists the card row.
 *
 * GET    /customer/payment-methods            → list
 * PUT    /customer/payment-methods/{id}/default → set default
 * DELETE /customer/payment-methods/{id}       → detach from Stripe + delete row
 */
class PaymentMethodsController extends BaseController
{
    public function __construct(protected MarketplaceStripeService $stripe) {}

    public function index(Request $request): JsonResponse
    {
        $customer = $request->user();
        if (! $customer instanceof MarketplaceCustomer) {
            return $this->error('Unauthorized', 401);
        }

        $methods = MarketplaceCustomerPaymentMethod::where('marketplace_customer_id', $customer->id)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderByDesc('created_at')
            ->get();

        return $this->success([
            'payment_methods' => $methods->map(fn ($m) => $this->present($m))->values()->all(),
            'stripe_publishable_key' => $customer->marketplaceClient
                ? $this->stripe->publishableKey($customer->marketplaceClient)
                : null,
            'stripe_configured' => $customer->marketplaceClient
                ? $this->stripe->isConfigured($customer->marketplaceClient)
                : false,
        ]);
    }

    public function createSetupIntent(Request $request): JsonResponse
    {
        $customer = $request->user();
        if (! $customer instanceof MarketplaceCustomer) {
            return $this->error('Unauthorized', 401);
        }
        if (! $customer->marketplaceClient || ! $this->stripe->isConfigured($customer->marketplaceClient)) {
            return $this->error('Procesatorul de plăți nu este configurat pentru această platformă.', 503);
        }

        try {
            $data = $this->stripe->createSetupIntent($customer);
            return $this->success($data, 'SetupIntent creat.');
        } catch (\Throwable $e) {
            return $this->error('Nu am putut iniția salvarea cardului: ' . $e->getMessage(), 502);
        }
    }

    public function confirmSetup(Request $request): JsonResponse
    {
        $customer = $request->user();
        if (! $customer instanceof MarketplaceCustomer) {
            return $this->error('Unauthorized', 401);
        }

        $validated = $request->validate([
            'setup_intent_id' => 'required|string|max:200',
        ]);

        try {
            $method = $this->stripe->attachConfirmedSetupIntent($customer, $validated['setup_intent_id']);
            return $this->success(['payment_method' => $this->present($method)], 'Cardul a fost salvat.');
        } catch (\Throwable $e) {
            return $this->error('Cardul nu a putut fi salvat: ' . $e->getMessage(), 422);
        }
    }

    public function setDefault(Request $request, int $id): JsonResponse
    {
        $customer = $request->user();
        if (! $customer instanceof MarketplaceCustomer) {
            return $this->error('Unauthorized', 401);
        }

        $method = MarketplaceCustomerPaymentMethod::where('marketplace_customer_id', $customer->id)
            ->where('id', $id)
            ->where('is_active', true)
            ->first();
        if (! $method) {
            return $this->error('Cardul nu a fost găsit.', 404);
        }

        $this->stripe->setDefault($method);

        return $this->success(['payment_method' => $this->present($method->fresh())], 'Card setat ca implicit.');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $customer = $request->user();
        if (! $customer instanceof MarketplaceCustomer) {
            return $this->error('Unauthorized', 401);
        }

        $method = MarketplaceCustomerPaymentMethod::where('marketplace_customer_id', $customer->id)
            ->where('id', $id)
            ->first();
        if (! $method) {
            return $this->error('Cardul nu a fost găsit.', 404);
        }

        try {
            $this->stripe->deleteMethod($method);
            return $this->success(null, 'Cardul a fost șters.');
        } catch (\Throwable $e) {
            return $this->error('Cardul nu a putut fi șters: ' . $e->getMessage(), 502);
        }
    }

    protected function present(MarketplaceCustomerPaymentMethod $m): array
    {
        return [
            'id'             => $m->id,
            'provider'       => $m->provider,
            'brand'          => $m->card_brand,
            'last4'          => $m->card_last_four,
            'exp_month'      => $m->card_exp_month,
            'exp_year'       => $m->card_exp_year,
            'cardholder'     => $m->cardholder_name,
            'label'          => $m->display_label,
            'is_default'     => (bool) $m->is_default,
            'is_expired'     => $m->isExpired(),
            'created_at'     => $m->created_at?->toIso8601String(),
        ];
    }
}
