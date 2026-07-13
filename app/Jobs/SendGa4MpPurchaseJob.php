<?php

namespace App\Jobs;

use App\Models\MarketplaceClient;
use App\Models\Order;
use App\Models\TrackingIntegration;
use App\Services\Integrations\Ga4\Ga4MeasurementProtocolService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Layer C for GA4: server-side Purchase dispatcher.
 *
 * Fires from ServerSidePurchaseOrderObserver when an Order transitions
 * into a paid status. Cannot be blocked by browser measures — this is
 * the safety net that catches purchases when the thank-you page's JS
 * bridge (Layer B) doesn't run (adblocker on payment redirect, user
 * closed the tab, etc.).
 *
 * Dedupe with the browser gtag purchase (Layer A): GA4 dedupes purchases
 * by `transaction_id` so we always pass the order id there. If both
 * layers fire, GA4 shows only one purchase in ecommerce reports (though
 * page_view and other event params may double-count — a GA4 MP quirk,
 * see Ga4MeasurementProtocolService docblock).
 */
class SendGa4MpPurchaseJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;
    public int $timeout = 30;

    public function __construct(public int $orderId)
    {
    }

    public function handle(Ga4MeasurementProtocolService $ga4): void
    {
        $order = Order::with(['tickets'])->find($this->orderId);
        if (!$order) {
            return;
        }
        if (!$order->marketplace_client_id) {
            return;
        }

        $integration = TrackingIntegration::resolveForServerSide(
            'ga4',
            $order->marketplace_organizer_id ? (int) $order->marketplace_organizer_id : null,
            (int) $order->marketplace_client_id,
        );
        if (!$integration) {
            return;
        }

        $measurementId = $integration->getProviderId();
        $apiSecret = $integration->getServerSideCredential();

        $clientId = $this->resolveClientId($order);
        $params = $this->buildParams($order);

        try {
            $ga4->sendEvent(
                measurementId: $measurementId,
                apiSecret: $apiSecret,
                clientId: $clientId,
                eventName: 'purchase',
                params: $params,
                userProperties: array_filter([
                    'order_id' => (string) $order->id,
                ]),
            );
        } catch (\Throwable $e) {
            Log::warning('GA4 MP Purchase send failed', [
                'order_id' => $order->id,
                'integration_id' => $integration->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * GA4's client_id ties events to a "user" (device really). We reuse
     * the visitor_id / _ga cookie stored on the order meta when the
     * browser had it available; otherwise fall back to a stable hash
     * derived from the order id so retries don't split the same user.
     */
    protected function resolveClientId(Order $order): string
    {
        $meta = $order->meta ?? [];
        if (!empty($meta['ga_client_id'])) {
            return (string) $meta['ga_client_id'];
        }
        if (!empty($meta['visitor_id'])) {
            return (string) $meta['visitor_id'];
        }
        // Deterministic fallback: retries and layer-B dispatches for the
        // same order all resolve to the same client_id. Format matches
        // GA4's expected shape ({randomNumber}.{timestamp}).
        return 'order_' . $order->id . '.' . (int) $order->created_at?->timestamp;
    }

    protected function buildParams(Order $order): array
    {
        $value = $order->marketplace_client_id
            ? (float) $order->total
            : (($order->total_cents ?? 0) / 100);

        $items = [];
        try {
            foreach ($order->tickets ?? [] as $ticket) {
                $items[] = array_filter([
                    'item_id' => $ticket->ticket_type_id ? (string) $ticket->ticket_type_id : null,
                    'item_name' => $ticket->ticket_type?->name ?? null,
                    'item_category' => 'ticket',
                    'price' => isset($ticket->price) ? (float) $ticket->price : null,
                    'quantity' => 1,
                ]);
            }
        } catch (\Throwable $e) {
            // Ticket relation unavailable — send purchase without items.
            // GA4 still records the transaction (transaction_id is the
            // ecommerce dedup key, not items).
        }

        return array_filter([
            'transaction_id' => (string) $order->id,
            'value' => round($value, 2),
            'currency' => $order->currency ?? 'RON',
            'page_location' => $this->resolveThankYouUrl($order),
            'items' => !empty($items) ? array_values($items) : null,
        ], fn ($v) => $v !== null && $v !== '');
    }

    protected function resolveThankYouUrl(Order $order): ?string
    {
        if (!$order->marketplace_client_id) {
            return null;
        }
        $client = $order->marketplaceClient
            ?? MarketplaceClient::find($order->marketplace_client_id);
        $domain = $client?->domain;
        if (!$domain) {
            return null;
        }
        $base = str_starts_with($domain, 'http') ? $domain : 'https://' . $domain;
        $orderRef = $order->order_number ?? $order->id;
        return rtrim($base, '/') . '/thank-you?order=' . urlencode((string) $orderRef);
    }
}
