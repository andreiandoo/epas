<?php

namespace App\Http\Controllers\Api\TenantClient;

use App\Http\Controllers\Controller;
use App\Models\InstallmentPlan;
use App\Models\MarketplaceClient;
use App\Models\Order;
use App\Services\Installments\FlexiblePaymentEligibilityService;
use App\Services\Installments\InstallmentAgreementService;
use App\Services\Installments\InstallmentPlanCalculator;
use App\Services\Installments\ProcessorResolver;
use App\Services\PaymentProcessors\SupportsTokenizedPayments;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Storefront API for flexible payments: eligible methods, plan previews, and
 * starting a plan (creates the agreement + returns the down-payment redirect).
 */
class InstallmentController extends Controller
{
    public function __construct(
        protected FlexiblePaymentEligibilityService $eligibility,
        protected InstallmentPlanCalculator $calculator,
        protected InstallmentAgreementService $agreements,
        protected ProcessorResolver $resolver,
    ) {}

    /** Which methods are available for a cart (single-event rule enforced). */
    public function availability(Request $request): JsonResponse
    {
        $client = $this->resolveClient($request);
        $provider = $request->input('provider', $client?->getDefaultPaymentMethod()?->slug ?? '');
        $eventIds = (array) $request->input('event_ids', array_filter([$request->input('event_id')]));

        return response()->json(
            $this->eligibility->availability($client, $this->normalizeProvider($provider), array_map('intval', $eventIds))
        );
    }

    /** Priced, eligible plan previews for a single event + amount. */
    public function plans(Request $request): JsonResponse
    {
        $eventId = (int) $request->input('event_id');
        $baseCents = (int) round(((float) $request->input('amount', 0)) * 100);

        $plans = $this->eligibility->plansForEvent($eventId, $baseCents, [
            'event_start_date' => $request->input('event_start_date'),
        ]);

        return response()->json(['plans' => $plans]);
    }

    /**
     * Start a plan for an existing (pending) order.
     * Body: order_id, plan_id. Returns the down-payment / capture redirect URL.
     */
    public function start(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order_id' => 'required',
            'plan_id' => 'required|integer',
        ]);

        $order = Order::find($data['order_id']);
        if (! $order) {
            return response()->json(['error' => 'Order not found'], 404);
        }
        $plan = InstallmentPlan::find($data['plan_id']);
        if (! $plan || ! $plan->is_active) {
            return response()->json(['error' => 'Plan not found'], 404);
        }

        $client = $order->marketplace_client_id ? MarketplaceClient::find($order->marketplace_client_id) : null;
        if (! $client) {
            return response()->json(['error' => 'Marketplace client required'], 422);
        }

        $config = \App\Models\EventFlexiblePaymentConfig::where('event_id', $order->event_id)
            ->orWhere('marketplace_event_id', $order->marketplace_event_id)
            ->first();

        $baseCents = (int) round(((float) $order->total) * 100);
        $quote = $this->calculator->quote($plan, $baseCents, [
            'down_payment_type' => $config?->down_payment_type ?? 'none',
            'down_payment_value' => $config?->down_payment_value ?? 0,
            'event_start_date' => $order->event?->start_date ?? null,
            'platform_fee_percent' => config('installments.platform_fee_percent_installments', 2.0),
            'bnpl_max_horizon_days' => $config?->bnpl_max_horizon_days ?? 30,
        ]);

        if (empty($quote['eligible'])) {
            return response()->json(['error' => 'Plan not eligible', 'reason' => $quote['reason']], 422);
        }

        $resolved = $this->resolver->forMarketplaceClient($client);
        $processor = $resolved['processor'] ?? null;
        if (! $processor instanceof SupportsTokenizedPayments || ! $processor->supportsTokenization()) {
            return response()->json(['error' => 'Processor does not support installments'], 422);
        }

        $agreement = $this->agreements->createFromQuote($plan, $quote, [
            'order_id' => $order->id,
            'marketplace_client_id' => $client->id,
            'tenant_id' => $order->tenant_id,
            'marketplace_customer_id' => $order->marketplace_customer_id,
            'customer_email' => $order->customer_email,
            'customer_name' => $order->customer_name,
            'customer_phone' => $order->customer_phone,
            'event_id' => $order->event_id,
            'marketplace_event_id' => $order->marketplace_event_id,
            'event_start_date' => $order->event?->start_date,
            'provider' => $resolved['type'],
            'currency' => $order->currency,
        ]);

        // Charge the down payment (or the BNPL 1-RON card capture) with a mandate.
        $captureCents = $plan->isBnpl()
            ? (int) config('installments.bnpl_card_capture_cents', 100)
            : $quote['down_payment_cents'];

        $payment = $processor->createPaymentWithMandate([
            'amount' => max($captureCents, 1) / 100,
            'currency' => $order->currency ?? 'RON',
            'description' => $plan->isBnpl() ? 'Verificare card (BNPL)' : 'Avans plată în rate',
            'order_id' => (string) $order->id,
            'customer_email' => $order->customer_email,
            'customer_name' => $order->customer_name,
            'success_url' => url("/installments/agreements/{$agreement->id}"),
            'cancel_url' => url('/checkout'),
            'metadata' => ['agreement_id' => $agreement->id],
        ]);

        return response()->json([
            'agreement_id' => $agreement->id,
            'redirect_url' => $payment['redirect_url'] ?? null,
        ]);
    }

    protected function resolveClient(Request $request): ?MarketplaceClient
    {
        $id = $request->input('marketplace_client_id');
        return $id ? MarketplaceClient::find($id) : null;
    }

    protected function normalizeProvider(string $slug): string
    {
        return match ($slug) {
            'netopia', 'netopia-payments', 'payment-netopia' => 'netopia',
            'stripe', 'stripe-payments', 'payment-stripe' => 'stripe',
            'euplatesc', 'payment-euplatesc' => 'euplatesc',
            'payu', 'payment-payu' => 'payu',
            default => $slug,
        };
    }
}
