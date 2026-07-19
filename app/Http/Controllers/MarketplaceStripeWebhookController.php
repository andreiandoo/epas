<?php

namespace App\Http\Controllers;

use App\Models\MarketplaceClient;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

/**
 * Per-marketplace Stripe webhook receiver.
 *
 * URL: POST /webhooks/marketplace-stripe/{marketplaceClientId}
 *
 * Each marketplace registers this URL (with their own client id baked in) on
 * their own Stripe account's Webhooks dashboard and gets a unique signing
 * secret back. The secret is stored in the marketplace's
 * marketplace_client_microservices pivot settings for the `payment-stripe`
 * microservice (`test_webhook_secret` / `live_webhook_secret`).
 *
 * Why a per-marketplace endpoint instead of a shared one:
 *   - Each Stripe account issues its own signing secret. A shared endpoint
 *     can only validate against one secret at a time, so events from any
 *     other Stripe account would fail signature verification.
 *   - Putting the marketplace_client_id in the URL means the routing decision
 *     is made BEFORE signature verification, using only the (trusted) path —
 *     no chicken-and-egg with payload metadata.
 *   - Each marketplace can rotate its secret independently.
 *
 * Events handled (others are logged + acked so Stripe stops retrying):
 *   - payment_intent.succeeded     → mark Order paid, flip tickets to valid,
 *                                    confirm seat holds (held → sold)
 *   - payment_intent.payment_failed → mark Order failed, store error
 *   - charge.refunded               → mark Order refunded (full/partial)
 *
 * Idempotency: every Stripe event has a stable `id` (evt_xxx). We cache it
 * for 7 days (Stripe retries for up to 3 days) so a retry on a transient
 * 500 doesn't double-process. Stripe also includes the event id in retries
 * so this works across delivery attempts.
 *
 * Order lookup priority:
 *   1) paymentIntent.metadata.order_id       (set by CheckoutController when
 *                                             creating the PI — strongest match)
 *   2) Order.payment_reference == paymentIntent.id  (fallback)
 *
 * For charge.refunded the event object is a Charge; we resolve the order via
 * `charge.payment_intent` → same two-step lookup.
 */
class MarketplaceStripeWebhookController extends Controller
{
    /** Stripe retries for up to 3 days; cache 7 days for safety. */
    protected const IDEMPOTENCY_TTL_SECONDS = 7 * 24 * 3600;

    /** Pivot slug for the Stripe payment microservice (see PaymentMicroservicesSeeder). */
    protected const STRIPE_MICROSERVICE_SLUG = 'payment-stripe';

    public function handle(Request $request, int $marketplaceClientId): JsonResponse
    {
        // ─── Resolve marketplace + secret ─────────────────────────────
        $client = MarketplaceClient::find($marketplaceClientId);
        if (!$client) {
            Log::warning('Marketplace Stripe webhook: unknown marketplace_client_id', [
                'marketplace_client_id' => $marketplaceClientId,
            ]);
            return response()->json(['error' => 'marketplace_not_found'], 404);
        }

        $secret = $this->resolveWebhookSecret($client);
        if (!$secret) {
            Log::warning('Marketplace Stripe webhook: secret not configured', [
                'marketplace_client_id' => $client->id,
            ]);
            // 401 instead of 500 — explicit "we're refusing to process" so
            // Stripe stops retrying immediately on a misconfigured endpoint.
            return response()->json(['error' => 'webhook_secret_not_configured'], 401);
        }

        // ─── Verify signature ─────────────────────────────────────────
        $payload   = $request->getContent();
        $signature = (string) $request->header('Stripe-Signature', '');

        try {
            $event = Webhook::constructEvent($payload, $signature, $secret);
        } catch (SignatureVerificationException $e) {
            Log::warning('Marketplace Stripe webhook: signature verification failed', [
                'marketplace_client_id' => $client->id,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['error' => 'invalid_signature'], 400);
        } catch (\Throwable $e) {
            Log::error('Marketplace Stripe webhook: payload parse failed', [
                'marketplace_client_id' => $client->id,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['error' => 'invalid_payload'], 400);
        }

        // ─── Idempotency ──────────────────────────────────────────────
        $cacheKey = "stripe_webhook_processed:{$client->id}:{$event->id}";
        if (Cache::has($cacheKey)) {
            Log::info('Marketplace Stripe webhook: duplicate event ignored', [
                'marketplace_client_id' => $client->id,
                'event_id' => $event->id,
                'event_type' => $event->type,
            ]);
            return response()->json(['status' => 'duplicate', 'event_id' => $event->id]);
        }

        // ─── Route by event type ──────────────────────────────────────
        try {
            switch ($event->type) {
                case 'payment_intent.succeeded':
                    $this->handlePaymentSucceeded($client, $event->data->object);
                    break;
                case 'payment_intent.payment_failed':
                    $this->handlePaymentFailed($client, $event->data->object);
                    break;
                case 'charge.refunded':
                    $this->handleChargeRefunded($client, $event->data->object);
                    break;
                default:
                    Log::info('Marketplace Stripe webhook: unhandled event type', [
                        'marketplace_client_id' => $client->id,
                        'event_type' => $event->type,
                        'event_id' => $event->id,
                    ]);
            }

            // Only mark processed AFTER successful handling. If the handler
            // threw, we want Stripe to retry — that's the point of the queue.
            Cache::put($cacheKey, true, static::IDEMPOTENCY_TTL_SECONDS);

            return response()->json(['status' => 'ok']);
        } catch (\Throwable $e) {
            Log::error('Marketplace Stripe webhook: handler threw', [
                'marketplace_client_id' => $client->id,
                'event_id'              => $event->id,
                'event_type'            => $event->type,
                'error'                 => $e->getMessage(),
                'trace'                 => $e->getTraceAsString(),
            ]);
            // 500 → Stripe will retry with exponential backoff.
            return response()->json(['error' => 'processing_failed'], 500);
        }
    }

    /**
     * Pick the right webhook secret based on the marketplace's `test_mode` flag.
     */
    protected function resolveWebhookSecret(MarketplaceClient $client): ?string
    {
        $stripe = $client->microservices()
            ->where('slug', static::STRIPE_MICROSERVICE_SLUG)
            ->first();

        if (!$stripe) {
            return null;
        }

        $settings = $stripe->pivot->settings ?? [];
        if (is_string($settings)) {
            $settings = json_decode($settings, true) ?: [];
        }

        // test_mode boolean controls which credentials we use. Defaults to
        // test mode if unset (safer — refuses to accept live webhooks until
        // explicitly switched).
        $isTest = (bool) ($settings['test_mode'] ?? true);
        $key    = $isTest ? 'test_webhook_secret' : 'live_webhook_secret';

        $secret = $settings[$key] ?? null;
        return is_string($secret) && $secret !== '' ? $secret : null;
    }

    // ────────────────────────────────────────────────────────────────
    // Event handlers
    // ────────────────────────────────────────────────────────────────

    protected function handlePaymentSucceeded(MarketplaceClient $client, $paymentIntent): void
    {
        $order = $this->findOrderForPaymentIntent($client, $paymentIntent);
        if (!$order) {
            Log::warning('Marketplace Stripe webhook: order not found for succeeded PI', [
                'marketplace_client_id' => $client->id,
                'payment_intent_id'     => $paymentIntent->id,
            ]);
            return;
        }

        // Already paid — don't re-run side effects. Webhook may arrive AFTER
        // the client-side confirm endpoint already flipped the order.
        if ($order->payment_status === 'paid') {
            Log::info('Marketplace Stripe webhook: order already paid, no-op', [
                'order_id' => $order->id,
                'payment_intent_id' => $paymentIntent->id,
            ]);
            return;
        }

        // Flexible payments: this PI is only the down payment / BNPL capture.
        // Activate the agreement (capturing the saved-card mandate) and keep the
        // order partially_paid; do NOT mark it fully paid or validate tickets.
        if ($order->installment_agreement_id) {
            $mandate = $paymentIntent->customer
                ? ($paymentIntent->customer . ($paymentIntent->payment_method ? '|' . $paymentIntent->payment_method : ''))
                : null;
            app(\App\Services\Installments\InstallmentAgreementService::class)
                ->handleDownPaymentCallback($order, [
                    'mandate_reference' => $mandate,
                    'transaction_id' => $paymentIntent->id,
                ]);
            Log::info('Marketplace Stripe webhook: installment down payment confirmed', [
                'order_id' => $order->id,
                'agreement_id' => $order->installment_agreement_id,
                'has_mandate' => (bool) $mandate,
            ]);
            return;
        }

        DB::transaction(function () use ($order, $paymentIntent) {
            $order->update([
                'status'             => 'completed',
                'payment_status'     => 'paid',
                'paid_at'            => now(),
                'payment_reference'  => $paymentIntent->id,
                'payment_processor'  => 'stripe',
            ]);

            // Flip ticket rows pending → valid. DB::table avoids triggering
            // observers (matches the Netopia callback pattern in
            // PaymentController) and is naturally idempotent on retries.
            DB::table('tickets')
                ->where('order_id', $order->id)
                ->where('status', 'pending')
                ->update(['status' => 'valid', 'updated_at' => now()]);

            // Confirm any held seats (held → sold). Best-effort: log and
            // continue on failure so the order stays paid; manual reconciliation
            // is preferable to a stuck order.
            $seatedItems = $order->meta['seated_items'] ?? [];
            if (!empty($seatedItems)) {
                $seatHoldService = app(\App\Services\Seating\SeatHoldService::class);
                foreach ($seatedItems as $seatedItem) {
                    try {
                        $seatHoldService->confirmPurchase(
                            $seatedItem['event_seating_id'],
                            $seatedItem['seat_uids'],
                            'stripe-webhook-confirmed',
                            (int) ($order->total * 100),
                        );
                    } catch (\Throwable $e) {
                        Log::warning('Marketplace Stripe webhook: seat confirm failed', [
                            'order_id'         => $order->id,
                            'event_seating_id' => $seatedItem['event_seating_id'] ?? null,
                            'error'            => $e->getMessage(),
                        ]);
                    }
                }
            }
        });

        Log::info('Marketplace Stripe webhook: order marked paid', [
            'order_id'               => $order->id,
            'order_number'           => $order->order_number,
            'payment_intent_id'      => $paymentIntent->id,
            'marketplace_client_id'  => $client->id,
        ]);
    }

    protected function handlePaymentFailed(MarketplaceClient $client, $paymentIntent): void
    {
        $order = $this->findOrderForPaymentIntent($client, $paymentIntent);
        if (!$order) {
            return;
        }

        // Defensive: never downgrade an already-paid order on a late failure
        // event (Stripe shouldn't send this but quirky retries happen).
        if ($order->payment_status === 'paid') {
            return;
        }

        $errorMessage = $paymentIntent->last_payment_error->message ?? 'unknown';

        $order->update([
            'payment_status'    => 'failed',
            'payment_error'     => mb_substr($errorMessage, 0, 1000),
            'payment_processor' => 'stripe',
        ]);

        Log::info('Marketplace Stripe webhook: order marked failed', [
            'order_id'           => $order->id,
            'payment_intent_id'  => $paymentIntent->id,
            'error'              => $errorMessage,
        ]);
    }

    protected function handleChargeRefunded(MarketplaceClient $client, $charge): void
    {
        $piId = $charge->payment_intent ?? null;
        if (!$piId) {
            Log::info('Marketplace Stripe webhook: refund without payment_intent — skipping', [
                'charge_id' => $charge->id,
            ]);
            return;
        }

        // For refunds Stripe gives us the Charge, not the PaymentIntent. The
        // charge carries `payment_intent`, which is what we stored on the
        // order originally → simple lookup by payment_reference scoped to the
        // marketplace so a misrouted webhook can't touch another marketplace's
        // orders.
        $order = Order::query()
            ->where('marketplace_client_id', $client->id)
            ->where('payment_reference', $piId)
            ->first();

        if (!$order) {
            Log::warning('Marketplace Stripe webhook: order not found for refund', [
                'charge_id'              => $charge->id,
                'payment_intent_id'      => $piId,
                'marketplace_client_id'  => $client->id,
            ]);
            return;
        }

        // Stripe sends amounts in the smallest currency unit (cents/bani).
        $totalCents    = (int) ($charge->amount ?? 0);
        $refundedCents = (int) ($charge->amount_refunded ?? 0);
        $isFullRefund  = $totalCents > 0 && $refundedCents >= $totalCents;

        $order->update([
            'payment_status'   => $isFullRefund ? 'refunded' : 'partially_refunded',
            'refunded_at'      => now(),
            'refunded_amount'  => $refundedCents / 100,
            'refund_status'    => 'completed',
        ]);

        Log::info('Marketplace Stripe webhook: order refunded', [
            'order_id'           => $order->id,
            'charge_id'          => $charge->id,
            'payment_intent_id'  => $piId,
            'refund_type'        => $isFullRefund ? 'full' : 'partial',
            'refunded_amount'    => $refundedCents / 100,
        ]);
    }

    /**
     * Two-step lookup so we can tolerate either:
     *   - metadata.order_id set by CheckoutController on PaymentIntent create
     *   - payment_reference = PI id stored at order creation time
     *
     * Marketplace_client_id is always part of the WHERE clause so a misrouted
     * webhook (wrong URL → wrong client id) can never touch another
     * marketplace's data.
     */
    protected function findOrderForPaymentIntent(MarketplaceClient $client, $paymentIntent): ?Order
    {
        $metadata = isset($paymentIntent->metadata)
            ? (array) $paymentIntent->metadata->toArray()
            : [];

        $orderId = $metadata['order_id'] ?? null;
        if ($orderId) {
            $order = Order::query()
                ->where('marketplace_client_id', $client->id)
                ->where('id', $orderId)
                ->first();
            if ($order) {
                return $order;
            }
        }

        return Order::query()
            ->where('marketplace_client_id', $client->id)
            ->where('payment_reference', $paymentIntent->id)
            ->first();
    }
}
