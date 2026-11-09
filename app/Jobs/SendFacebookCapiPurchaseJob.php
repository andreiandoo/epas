<?php

namespace App\Jobs;

use App\Models\MarketplaceClient;
use App\Models\Order;
use App\Support\WhitelabelOrder;
use App\Services\Integrations\FacebookCapi\FacebookCapiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendFacebookCapiPurchaseJob implements ShouldQueue
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

    public function handle(FacebookCapiService $capi): void
    {
        $order = Order::with(['customer', 'tickets'])->find($this->orderId);
        if (!$order) {
            return;
        }
        if (!$order->marketplace_organizer_id) {
            return;
        }

        $connection = $capi->getConnectionForOrganizer($order->marketplace_organizer_id);
        if (!$connection) {
            return;
        }

        $userData = $this->buildUserData($order);
        $customData = $this->buildCustomData($order);
        // Same id as the browser Purchase: purchase_{id}, or purchase_{id}_whitelabel
        // for orders placed on an organizer's whitelabel site.
        $eventId = WhitelabelOrder::purchaseEventId($order);
        $sourceUrl = $this->resolveThankYouUrl($order);

        try {
            $capi->sendEvent($connection, 'Purchase', $userData, $customData, [
                'event_id' => $eventId,
                'event_source_url' => $sourceUrl,
                'action_source' => 'website',
                'correlation_type' => 'order',
                'correlation_id' => (string) $order->id,
            ]);
        } catch (\Throwable $e) {
            Log::warning('FB CAPI Purchase send failed', [
                'order_id' => $order->id,
                'organizer_id' => $order->marketplace_organizer_id,
                'connection_id' => $connection->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    protected function buildUserData(Order $order): array
    {
        $name = trim((string) ($order->customer_name ?? ''));
        $first = '';
        $last = '';
        if ($name !== '') {
            $parts = preg_split('/\s+/', $name, 2);
            $first = $parts[0] ?? '';
            $last = $parts[1] ?? '';
        }

        $meta = $order->meta ?? [];
        $isWhitelabel = WhitelabelOrder::is($order);

        $fbc = $meta['fbc'] ?? null;
        if (!$fbc && !empty($meta['fbclid'])) {
            $fbcTime = isset($meta['fbclid_at']) ? (int) $meta['fbclid_at'] : time();
            $fbc = sprintf('fb.1.%d.%s', $fbcTime, $meta['fbclid']);
        }

        return array_filter([
            'em' => $order->customer_email ?: null,
            'ph' => $order->customer_phone ?: null,
            'fn' => $first ?: null,
            'ln' => $last ?: null,
            // Same id the browser funnel events (ViewContent, AddToCart, InitiateCheckout)
            // send, so Meta can tie the purchase to them; order id only as a fallback
            // (not on whitelabel orders, where it would never match the browser's id).
            'external_id' => !empty($meta['visitor_id'])
                ? (string) $meta['visitor_id']
                : ($isWhitelabel ? null : (string) $order->id),
            // Whitelabel orders reach core through the site's server: the plain
            // ip_address / user_agent keys are that server, not the buyer.
            'client_ip_address' => $isWhitelabel
                ? ($meta['client_ip'] ?? null)
                : ($meta['client_ip'] ?? $meta['ip'] ?? $meta['ip_address'] ?? null),
            'client_user_agent' => $isWhitelabel
                ? ($meta['client_user_agent'] ?? null)
                : ($meta['client_user_agent'] ?? $meta['user_agent'] ?? $meta['ua'] ?? null),
            'fbp' => $meta['fbp'] ?? null,
            'fbc' => $fbc,
        ], fn ($v) => $v !== null && $v !== '');
    }

    protected function buildCustomData(Order $order): array
    {
        $contentIds = [];
        $contents = [];
        $numItems = 0;
        try {
            $tickets = $order->tickets;
            if ($tickets) {
                $numItems = $tickets->count();

                // Whitelabel pages report the event (show) id as content id, AmBilet
                // the ticket type; the server follows the page so both match.
                $contentKey = WhitelabelOrder::is($order)
                    ? (fn ($t) => $t->event_id ?? $t->marketplace_event_id ?? $order->event_id)
                    : (fn ($t) => $t->ticket_type_id ?? $t->marketplace_ticket_type_id);

                $contentIds = WhitelabelOrder::is($order)
                    ? $tickets->map($contentKey)->filter()->unique()->values()->all()
                    : $tickets->pluck('ticket_type_id')->filter()->unique()->values()->all();

                // Per-ticket-type quantities (+ unit price) so Meta receives HOW
                // MANY tickets of each type were bought, not just the order total.
                // Marketplace tickets may store the type in marketplace_ticket_type_id.
                foreach ($tickets->groupBy($contentKey) as $cid => $group) {
                    if (!$cid) {
                        continue;
                    }
                    $entry = [
                        'id' => (string) $cid,
                        'quantity' => $group->count(),
                    ];
                    $avgPrice = $group->avg('price');
                    if ($avgPrice !== null && $avgPrice > 0) {
                        $entry['item_price'] = round((float) $avgPrice, 2);
                    }
                    $contents[] = $entry;
                }
            }
        } catch (\Throwable $e) {
            // tickets relation/data unavailable — fall back to header values
        }

        $value = $order->marketplace_client_id
            ? (float) $order->total
            : (($order->total_cents ?? 0) / 100);

        $data = [
            'value' => round($value, 2),
            'currency' => $order->currency ?? 'RON',
            'content_type' => 'product',
            'order_id' => (string) $order->id,
        ];

        if (!empty($contents)) {
            $data['contents'] = $contents;
        }
        if (!empty($contentIds)) {
            $data['content_ids'] = array_map('strval', $contentIds);
        }
        if ($numItems > 0) {
            $data['num_items'] = $numItems;
        }

        return $data;
    }

    protected function resolveThankYouUrl(Order $order): ?string
    {
        if (!$order->marketplace_client_id) {
            return null;
        }

        if ($whitelabelUrl = WhitelabelOrder::returnUrl($order)) {
            return $whitelabelUrl;
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
