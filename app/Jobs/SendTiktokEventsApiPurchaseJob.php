<?php

namespace App\Jobs;

use App\Models\MarketplaceClient;
use App\Models\Order;
use App\Models\TrackingIntegration;
use App\Services\Integrations\TikTokEventsApi\TikTokEventsApiClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Layer C for TikTok: server-side Purchase (CompletePayment) dispatcher.
 *
 * Fires from ServerSidePurchaseOrderObserver when an Order transitions
 * into a paid status. Dedupes against the browser ttq.track('CompletePayment')
 * call via a shared event_id (`purchase_{order_id}`) — TikTok will
 * collapse the two into one event in Events Manager if both arrive.
 */
class SendTiktokEventsApiPurchaseJob implements ShouldQueue
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

    public function handle(TikTokEventsApiClient $client): void
    {
        $order = Order::with(['tickets'])->find($this->orderId);
        if (!$order) {
            return;
        }
        if (!$order->marketplace_client_id) {
            return;
        }

        $integration = TrackingIntegration::resolveForServerSide(
            'tiktok',
            $order->marketplace_organizer_id ? (int) $order->marketplace_organizer_id : null,
            (int) $order->marketplace_client_id,
        );
        if (!$integration) {
            return;
        }

        $pixelCode = $integration->getProviderId();
        $accessToken = $integration->getServerSideCredential();

        $userData = $this->buildUserData($order);
        $properties = $this->buildProperties($order);

        try {
            $client->sendEvent(
                pixelCode: $pixelCode,
                accessToken: $accessToken,
                eventName: 'CompletePayment',
                eventId: 'purchase_' . $order->id,
                userData: $userData,
                properties: $properties,
                page: $this->buildPage($order),
                testEventCode: null,
            );
        } catch (\Throwable $e) {
            Log::warning('TikTok EAPI Purchase send failed', [
                'order_id' => $order->id,
                'integration_id' => $integration->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    protected function buildUserData(Order $order): array
    {
        $meta = $order->meta ?? [];

        return array_filter([
            'email' => $order->customer_email ?: null,
            'phone' => $order->customer_phone ?: null,
            'external_id' => (string) $order->id,
            'ttclid' => $meta['ttclid'] ?? null,
            'ttp' => $meta['ttp'] ?? null,
            'ip' => $meta['client_ip'] ?? $meta['ip'] ?? $meta['ip_address'] ?? null,
            'user_agent' => $meta['user_agent'] ?? $meta['ua'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');
    }

    protected function buildProperties(Order $order): array
    {
        $value = $order->marketplace_client_id
            ? (float) $order->total
            : (($order->total_cents ?? 0) / 100);

        $contents = [];
        try {
            foreach ($order->tickets ?? [] as $ticket) {
                $contents[] = array_filter([
                    'content_id' => $ticket->ticket_type_id ? (string) $ticket->ticket_type_id : null,
                    'content_name' => $ticket->ticket_type?->name ?? null,
                    'content_type' => 'product',
                    'quantity' => 1,
                    'price' => isset($ticket->price) ? (float) $ticket->price : null,
                ]);
            }
        } catch (\Throwable $e) {
            // Tickets relation unavailable — Purchase still valid without contents.
        }

        return array_filter([
            'value' => round($value, 2),
            'currency' => $order->currency ?? 'RON',
            'order_id' => (string) $order->id,
            'content_type' => 'product',
            'contents' => !empty($contents) ? array_values($contents) : null,
        ], fn ($v) => $v !== null && $v !== '');
    }

    protected function buildPage(Order $order): array
    {
        if (!$order->marketplace_client_id) {
            return [];
        }
        $client = $order->marketplaceClient
            ?? MarketplaceClient::find($order->marketplace_client_id);
        $domain = $client?->domain;
        if (!$domain) {
            return [];
        }
        $base = str_starts_with($domain, 'http') ? $domain : 'https://' . $domain;
        $orderRef = $order->order_number ?? $order->id;

        return [
            'url' => rtrim($base, '/') . '/thank-you?order=' . urlencode((string) $orderRef),
        ];
    }
}
