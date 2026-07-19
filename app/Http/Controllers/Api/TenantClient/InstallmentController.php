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
        $ticketTypeIds = array_map('intval', (array) $request->input('ticket_type_ids', []));

        return response()->json(
            $this->eligibility->availability(
                $client,
                $this->normalizeProvider($provider),
                array_map('intval', $eventIds),
                $ticketTypeIds
            )
        );
    }

    /** Priced, eligible plan previews for a single event + amount. */
    public function plans(Request $request): JsonResponse
    {
        $eventId = (int) $request->input('event_id');
        $baseCents = (int) round(((float) $request->input('amount', 0)) * 100);

        // Prefer an explicit date; otherwise derive from the event so the
        // deadline (last payment ≤ event − 1 day) is always enforced.
        $eventStart = $request->input('event_start_date');
        if (! $eventStart) {
            $eventStart = optional(\App\Models\Event::find($eventId))->start_date;
        }

        $plans = $this->eligibility->plansForEvent($eventId, $baseCents, [
            'event_start_date' => $eventStart,
        ]);

        // Apply the same gating as availability() (provider tokenization +
        // marketplace sub-module toggles), which plansForEvent alone doesn't check.
        $event = \App\Models\Event::find($eventId);
        $client = $event && $event->marketplace_client_id ? MarketplaceClient::find($event->marketplace_client_id) : null;
        $provider = $this->normalizeProvider($client?->getDefaultPaymentMethod()?->slug ?? '');
        $avail = $this->eligibility->availability($client, $provider, [$eventId]);

        $plans = array_values(array_filter($plans, function ($p) use ($avail) {
            return $p['plan_type'] === 'bnpl_single'
                ? ($avail['methods']['bnpl'] ?? false)
                : ($avail['methods']['installments'] ?? false);
        }));

        return response()->json(['plans' => $plans]);
    }

    /**
     * Per-ticket-type flexible-payment summary for an event page: which ticket
     * types can be bought in installments/BNPL and the "from X /rată" amount.
     */
    public function ticketTypes(Request $request): JsonResponse
    {
        $eventId = (int) $request->input('event_id');
        $event = \App\Models\Event::with('ticketTypes')->find($eventId);
        if (! $event) {
            return response()->json(['ticket_types' => []]);
        }

        $client = $event->marketplace_client_id ? MarketplaceClient::find($event->marketplace_client_id) : null;
        $provider = $this->normalizeProvider($client?->getDefaultPaymentMethod()?->slug ?? '');
        $eventStart = $event->start_date;

        // Resolve the event-level availability ONCE (same for every ticket type);
        // per-ticket only differs by ticket-eligibility + price.
        $baseAvail = $this->eligibility->availability($client, $provider, [$eventId], []);
        $config = \App\Models\EventFlexiblePaymentConfig::where('event_id', $eventId)
            ->orWhere('marketplace_event_id', $eventId)->first();

        $rows = [];
        foreach ($event->ticketTypes as $tt) {
            $ticketEligible = $config ? $config->ticketTypeEligible($tt->id) : false;
            $instAvail = ($baseAvail['methods']['installments'] ?? false) && $ticketEligible;
            $bnplAvail = ($baseAvail['methods']['bnpl'] ?? false) && $ticketEligible;
            $priceCents = (int) round(((float) ($tt->price ?? 0)) * 100);

            $fromCents = ($instAvail || $bnplAvail)
                ? $this->eligibility->startingFromCents($eventId, $priceCents, $eventStart)
                : null;

            $rows[] = [
                'ticket_type_id' => $tt->id,
                'name' => $tt->name,
                'price' => $tt->price,
                'currency' => $tt->currency ?? 'RON',
                'installments_available' => $instAvail,
                'bnpl_available' => $bnplAvail,
                'delegated_available' => $baseAvail['methods']['delegated_pay'] ?? false,
                'from_amount' => $fromCents !== null ? round($fromCents / 100, 2) : null,
                'from_label' => $fromCents !== null
                    ? 'de la ' . number_format($fromCents / 100, 2, ',', '.') . ' ' . ($tt->currency ?? 'RON') . '/rată'
                    : null,
            ];
        }

        return response()->json(['ticket_types' => $rows]);
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
        // Only a fresh, unpaid order without an existing plan can start one.
        if ($order->status !== 'pending' || $order->installment_agreement_id) {
            return response()->json(['error' => 'Order is not eligible for a payment plan'], 422);
        }
        // Ownership: a logged-in customer may only act on their own order.
        $authId = optional($request->user())->id;
        if ($order->marketplace_customer_id && $authId && (int) $order->marketplace_customer_id !== (int) $authId) {
            return response()->json(['error' => 'Forbidden'], 403);
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

        // The plan must be attached to THIS event and the matching method enabled.
        $methodEnabled = $plan->isBnpl() ? ($config?->enable_bnpl) : ($config?->enable_installments);
        $planAttached = $config && $config->plans()
            ->where('installment_plans.id', $plan->id)
            ->wherePivot('is_active', true)
            ->exists();
        if (! $config || ! $methodEnabled || ! $planAttached) {
            return response()->json(['error' => 'Plan not available for this event'], 422);
        }

        // Every ticket in the order must be an eligible ticket type.
        $orderTicketTypeIds = $order->tickets()->pluck('ticket_type_id')->filter()->unique()->all();
        if (! $config->allTicketTypesEligible($orderTicketTypeIds)) {
            return response()->json(['error' => 'Order contains tickets not eligible for installments'], 422);
        }

        // total is decimal (major units); fall back to total_cents when null.
        $baseCents = $order->total !== null
            ? (int) round(((float) $order->total) * 100)
            : (int) ($order->total_cents ?? 0);
        if ($baseCents <= 0) {
            return response()->json(['error' => 'Order has no payable amount'], 422);
        }

        // BNPL: the 1-RON card capture IS the down payment (sequence 0), so it is
        // deducted from the single deferred charge ("scădem leul din sold").
        if ($plan->isBnpl()) {
            $downType = 'fixed';
            $downValue = (int) config('installments.bnpl_card_capture_cents', 100);
        } else {
            $downType = $config?->down_payment_type ?? 'none';
            $downValue = $config?->down_payment_value ?? 0;
        }

        $quote = $this->calculator->quote($plan, $baseCents, [
            'down_payment_type' => $downType,
            'down_payment_value' => $downValue,
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

        // The down payment (sequence 0) is charged now with a mandate. For BNPL
        // that equals the 1-RON capture set above.
        $captureCents = $quote['down_payment_cents'];

        // The processor confirms the down payment to the marketplace callback,
        // whose installment branch activates the agreement + captures the mandate.
        $callbackUrl = route('api.marketplace-client.payment.callback', ['client' => $client->slug]);

        $payment = $processor->createPaymentWithMandate([
            'amount' => max($captureCents, 1) / 100,
            'currency' => $order->currency ?? 'RON',
            'description' => $plan->isBnpl() ? 'Verificare card (BNPL)' : 'Avans plată în rate',
            'order_id' => (string) $order->id,
            'order_number' => $order->order_number,
            'customer_email' => $order->customer_email,
            'customer_name' => $order->customer_name,
            'success_url' => url("/installments/agreements/{$agreement->portal_token}"),
            'return_url' => url("/installments/agreements/{$agreement->portal_token}"),
            'cancel_url' => url('/checkout'),
            'callback_url' => $callbackUrl,
            'notify_url' => $callbackUrl,
            'metadata' => ['agreement_id' => $agreement->id, 'notify_url' => $callbackUrl],
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
