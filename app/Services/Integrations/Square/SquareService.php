<?php

namespace App\Services\Integrations\Square;

use App\Models\Integrations\Square\SquareConnection;
use App\Models\Integrations\Square\SquareLocation;
use App\Models\Integrations\Square\SquareCatalogItem;
use App\Models\Integrations\Square\SquareOrder;
use App\Models\Integrations\Square\SquarePayment;
use App\Models\Integrations\Square\SquareWebhook;
use App\Models\Integrations\Square\SquareWebhookEvent;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Collection;

class SquareService
{
    protected string $apiBaseUrl = 'https://connect.squareup.com/v2';
    protected string $sandboxUrl = 'https://connect.squareupsandbox.com/v2';
    protected string $oauthUrl = 'https://connect.squareup.com/oauth2';

    // ==========================================
    // OAUTH FLOW
    // ==========================================

    public function getAuthorizationUrl(int $tenantId, array $scopes = []): string
    {
        $defaultScopes = [
            'MERCHANT_PROFILE_READ',
            'PAYMENTS_READ',
            'PAYMENTS_WRITE',
            'ORDERS_READ',
            'ORDERS_WRITE',
            'ITEMS_READ',
            'ITEMS_WRITE',
        ];
        $scopes = array_unique(array_merge($defaultScopes, $scopes));

        $params = [
            'client_id' => config('services.square.client_id'),
            'scope' => implode(' ', $scopes),
            'session' => false,
            'state' => encrypt(['tenant_id' => $tenantId]),
        ];

        return $this->oauthUrl . '/authorize?' . http_build_query($params);
    }

    public function handleCallback(string $code, string $state): SquareConnection
    {
        $stateData = decrypt($state);
        $tenantId = $stateData['tenant_id'];

        $response = Http::post($this->oauthUrl . '/token', [
            'client_id' => config('services.square.client_id'),
            'client_secret' => config('services.square.client_secret'),
            'code' => $code,
            'grant_type' => 'authorization_code',
        ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to exchange code for token');
        }

        $data = $response->json();

        $connection = SquareConnection::updateOrCreate(
            ['tenant_id' => $tenantId, 'merchant_id' => $data['merchant_id']],
            [
                'access_token' => $data['access_token'],
                'refresh_token' => $data['refresh_token'],
                'token_expires_at' => now()->parse($data['expires_at']),
                'environment' => 'production',
                'status' => 'active',
                'connected_at' => now(),
            ]
        );

        // Sync locations
        $this->syncLocations($connection);

        return $connection->fresh();
    }

    public function refreshToken(SquareConnection $connection): bool
    {
        $response = Http::post($this->oauthUrl . '/token', [
            'client_id' => config('services.square.client_id'),
            'client_secret' => config('services.square.client_secret'),
            'refresh_token' => $connection->refresh_token,
            'grant_type' => 'refresh_token',
        ]);

        if (!$response->successful()) {
            return false;
        }

        $data = $response->json();

        $connection->update([
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'],
            'token_expires_at' => now()->parse($data['expires_at']),
        ]);

        return true;
    }

    // ==========================================
    // LOCATIONS
    // ==========================================

    public function syncLocations(SquareConnection $connection): Collection
    {
        $response = $this->makeRequest($connection, 'GET', '/locations');

        $locations = collect($response['locations'] ?? []);

        foreach ($locations as $index => $locationData) {
            SquareLocation::updateOrCreate(
                ['connection_id' => $connection->id, 'location_id' => $locationData['id']],
                [
                    'name' => $locationData['name'],
                    'status' => $locationData['status'],
                    'type' => $locationData['type'] ?? null,
                    'address' => $locationData['address'] ?? null,
                    'timezone' => $locationData['timezone'] ?? null,
                    'currency' => $locationData['currency'] ?? null,
                    'is_primary' => $index === 0,
                    'capabilities' => $locationData['capabilities'] ?? null,
                ]
            );
        }

        $connection->update([
            'location_ids' => $locations->pluck('id')->toArray(),
        ]);

        return $connection->locations()->get();
    }

    // ==========================================
    // CATALOG
    // ==========================================

    public function syncCatalog(SquareConnection $connection): Collection
    {
        $cursor = null;
        $allItems = [];

        do {
            $params = ['types' => 'ITEM,ITEM_VARIATION,CATEGORY'];
            if ($cursor) {
                $params['cursor'] = $cursor;
            }

            $response = $this->makeRequest($connection, 'GET', '/catalog/list', $params);

            foreach ($response['objects'] ?? [] as $object) {
                $this->processCatalogObject($connection, $object);
                $allItems[] = $object;
            }

            $cursor = $response['cursor'] ?? null;
        } while ($cursor);

        return $connection->catalogItems()->get();
    }

    protected function processCatalogObject(SquareConnection $connection, array $object): void
    {
        $type = $object['type'];

        if ($type === 'ITEM') {
            SquareCatalogItem::updateOrCreate(
                ['connection_id' => $connection->id, 'catalog_object_id' => $object['id']],
                [
                    'type' => 'ITEM',
                    'name' => $object['item_data']['name'] ?? '',
                    'description' => $object['item_data']['description'] ?? null,
                    'category_id' => $object['item_data']['category_id'] ?? null,
                    'is_deleted' => $object['is_deleted'] ?? false,
                ]
            );

            // Process variations
            foreach ($object['item_data']['variations'] ?? [] as $variation) {
                $this->processCatalogObject($connection, $variation);
            }
        } elseif ($type === 'ITEM_VARIATION') {
            $priceMoney = $object['item_variation_data']['price_money'] ?? null;

            SquareCatalogItem::updateOrCreate(
                ['connection_id' => $connection->id, 'catalog_object_id' => $object['id']],
                [
                    'type' => 'ITEM_VARIATION',
                    'name' => $object['item_variation_data']['name'] ?? '',
                    'price_cents' => $priceMoney['amount'] ?? null,
                    'currency' => $priceMoney['currency'] ?? null,
                    'sku' => $object['item_variation_data']['sku'] ?? null,
                    'is_deleted' => $object['is_deleted'] ?? false,
                ]
            );
        }
    }

    public function createCatalogItem(
        SquareConnection $connection,
        string $name,
        int $priceCents,
        string $currency = 'USD',
        ?string $description = null
    ): array {
        $idempotencyKey = uniqid('item_', true);

        $response = $this->makeRequest($connection, 'POST', '/catalog/object', [
            'idempotency_key' => $idempotencyKey,
            'object' => [
                'type' => 'ITEM',
                'id' => '#' . $idempotencyKey,
                'item_data' => [
                    'name' => $name,
                    'description' => $description,
                    'variations' => [
                        [
                            'type' => 'ITEM_VARIATION',
                            'id' => '#variation_' . $idempotencyKey,
                            'item_variation_data' => [
                                'name' => 'Regular',
                                'pricing_type' => 'FIXED_PRICING',
                                'price_money' => [
                                    'amount' => $priceCents,
                                    'currency' => $currency,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        if (isset($response['catalog_object'])) {
            $this->processCatalogObject($connection, $response['catalog_object']);
        }

        return $response;
    }

    // ==========================================
    // ORDERS
    // ==========================================

    public function createOrder(
        SquareConnection $connection,
        string $locationId,
        array $lineItems,
        array $options = []
    ): SquareOrder {
        $idempotencyKey = $options['idempotency_key'] ?? uniqid('order_', true);

        $orderData = [
            'location_id' => $locationId,
            'line_items' => array_map(function ($item) {
                return [
                    'name' => $item['name'],
                    'quantity' => (string) ($item['quantity'] ?? 1),
                    'base_price_money' => [
                        'amount' => $item['price_cents'],
                        'currency' => $item['currency'] ?? 'USD',
                    ],
                ];
            }, $lineItems),
        ];

        if (isset($options['customer_id'])) {
            $orderData['customer_id'] = $options['customer_id'];
        }

        $response = $this->makeRequest($connection, 'POST', '/orders', [
            'idempotency_key' => $idempotencyKey,
            'order' => $orderData,
        ]);

        $order = $response['order'];

        return SquareOrder::create([
            'connection_id' => $connection->id,
            'order_id' => $order['id'],
            'location_id' => $order['location_id'],
            'state' => $order['state'],
            'total_money_cents' => $order['total_money']['amount'],
            'currency' => $order['total_money']['currency'],
            'line_items' => $order['line_items'],
            'source' => $order['source']['name'] ?? 'API',
            'local_type' => $options['local_type'] ?? null,
            'local_id' => $options['local_id'] ?? null,
            'created_at_square' => now()->parse($order['created_at']),
        ]);
    }

    public function getOrder(SquareConnection $connection, string $orderId): ?array
    {
        $response = $this->makeRequest($connection, 'GET', "/orders/{$orderId}");
        return $response['order'] ?? null;
    }

    public function syncOrders(SquareConnection $connection, ?string $locationId = null, array $options = []): Collection
    {
        $query = [
            'location_ids' => $locationId ? [$locationId] : $connection->location_ids,
        ];

        if (isset($options['start_at'])) {
            $query['query']['filter']['date_time_filter']['created_at']['start_at'] = $options['start_at'];
        }

        $response = $this->makeRequest($connection, 'POST', '/orders/search', $query);

        foreach ($response['orders'] ?? [] as $orderData) {
            SquareOrder::updateOrCreate(
                ['connection_id' => $connection->id, 'order_id' => $orderData['id']],
                [
                    'location_id' => $orderData['location_id'],
                    'state' => $orderData['state'],
                    'total_money_cents' => $orderData['total_money']['amount'],
                    'currency' => $orderData['total_money']['currency'],
                    'line_items' => $orderData['line_items'] ?? null,
                    'fulfillments' => $orderData['fulfillments'] ?? null,
                    'source' => $orderData['source']['name'] ?? null,
                    'created_at_square' => now()->parse($orderData['created_at']),
                    'closed_at' => isset($orderData['closed_at']) ? now()->parse($orderData['closed_at']) : null,
                ]
            );
        }

        return $connection->orders()->get();
    }

    // ==========================================
    // PAYMENTS
    // ==========================================

    public function createPayment(
        SquareConnection $connection,
        string $sourceId,
        int $amountCents,
        string $currency = 'USD',
        array $options = []
    ): SquarePayment {
        $idempotencyKey = $options['idempotency_key'] ?? uniqid('payment_', true);

        $paymentData = [
            'source_id' => $sourceId,
            'amount_money' => [
                'amount' => $amountCents,
                'currency' => $currency,
            ],
            'idempotency_key' => $idempotencyKey,
            'location_id' => $options['location_id'] ?? $connection->locations()->where('is_primary', true)->first()?->location_id,
        ];

        if (isset($options['order_id'])) {
            $paymentData['order_id'] = $options['order_id'];
        }

        if (isset($options['customer_id'])) {
            $paymentData['customer_id'] = $options['customer_id'];
        }

        $response = $this->makeRequest($connection, 'POST', '/payments', $paymentData);

        $payment = $response['payment'];

        $squareOrder = isset($payment['order_id'])
            ? SquareOrder::where('order_id', $payment['order_id'])->first()
            : null;

        return SquarePayment::create([
            'connection_id' => $connection->id,
            'square_order_id' => $squareOrder?->id,
            'payment_id' => $payment['id'],
            'order_id' => $payment['order_id'] ?? null,
            'location_id' => $payment['location_id'],
            'amount_cents' => $payment['amount_money']['amount'],
            'currency' => $payment['amount_money']['currency'],
            'status' => $payment['status'],
            'source_type' => $payment['source_type'] ?? null,
            'card_details' => $payment['card_details'] ?? null,
            'receipt_url' => $payment['receipt_url'] ?? null,
            'created_at_square' => now()->parse($payment['created_at']),
        ]);
    }

    public function syncPayments(SquareConnection $connection, array $options = []): Collection
    {
        $params = [
            'location_id' => $options['location_id'] ?? $connection->locations()->where('is_primary', true)->first()?->location_id,
        ];

        if (isset($options['begin_time'])) {
            $params['begin_time'] = $options['begin_time'];
        }

        $response = $this->makeRequest($connection, 'GET', '/payments', $params);

        foreach ($response['payments'] ?? [] as $paymentData) {
            $squareOrder = isset($paymentData['order_id'])
                ? SquareOrder::where('order_id', $paymentData['order_id'])->first()
                : null;

            SquarePayment::updateOrCreate(
                ['connection_id' => $connection->id, 'payment_id' => $paymentData['id']],
                [
                    'square_order_id' => $squareOrder?->id,
                    'order_id' => $paymentData['order_id'] ?? null,
                    'location_id' => $paymentData['location_id'],
                    'amount_cents' => $paymentData['amount_money']['amount'],
                    'currency' => $paymentData['amount_money']['currency'],
                    'status' => $paymentData['status'],
                    'source_type' => $paymentData['source_type'] ?? null,
                    'card_details' => $paymentData['card_details'] ?? null,
                    'receipt_url' => $paymentData['receipt_url'] ?? null,
                    'created_at_square' => now()->parse($paymentData['created_at']),
                ]
            );
        }

        return $connection->payments()->get();
    }

    // ==========================================
    // WEBHOOKS
    // ==========================================

    public function registerWebhook(SquareConnection $connection, array $eventTypes): SquareWebhook
    {
        return SquareWebhook::create([
            'connection_id' => $connection->id,
            'signature_key' => config('services.square.webhook_signature_key'),
            'event_types' => $eventTypes,
            'is_active' => true,
        ]);
    }

    public function processWebhook(array $payload, string $signature): void
    {
        $merchantId = $payload['merchant_id'] ?? null;
        if (!$merchantId) {
            return;
        }

        $connection = SquareConnection::where('merchant_id', $merchantId)->first();
        if (!$connection) {
            return;
        }

        $eventType = $payload['type'] ?? 'unknown';
        $eventId = $payload['event_id'] ?? uniqid();

        $event = SquareWebhookEvent::create([
            'connection_id' => $connection->id,
            'event_id' => $eventId,
            'event_type' => $eventType,
            'payload' => $payload,
            'status' => 'pending',
        ]);

        try {
            $data = $payload['data']['object'] ?? [];

            switch ($eventType) {
                case 'payment.completed':
                case 'payment.updated':
                    $this->handlePaymentWebhook($connection, $data);
                    break;

                case 'order.created':
                case 'order.updated':
                    $this->handleOrderWebhook($connection, $data);
                    break;
            }

            $event->markAsProcessed();
        } catch (\Exception $e) {
            $event->markAsFailed($e->getMessage());
        }
    }

    protected function handlePaymentWebhook(SquareConnection $connection, array $data): void
    {
        if (!isset($data['payment'])) {
            return;
        }

        $paymentData = $data['payment'];

        $squareOrder = isset($paymentData['order_id'])
            ? SquareOrder::where('order_id', $paymentData['order_id'])->first()
            : null;

        SquarePayment::updateOrCreate(
            ['connection_id' => $connection->id, 'payment_id' => $paymentData['id']],
            [
                'square_order_id' => $squareOrder?->id,
                'order_id' => $paymentData['order_id'] ?? null,
                'location_id' => $paymentData['location_id'],
                'amount_cents' => $paymentData['amount_money']['amount'],
                'currency' => $paymentData['amount_money']['currency'],
                'status' => $paymentData['status'],
                'source_type' => $paymentData['source_type'] ?? null,
                'card_details' => $paymentData['card_details'] ?? null,
                'receipt_url' => $paymentData['receipt_url'] ?? null,
                'created_at_square' => now()->parse($paymentData['created_at']),
            ]
        );
    }

    protected function handleOrderWebhook(SquareConnection $connection, array $data): void
    {
        if (!isset($data['order'])) {
            return;
        }

        $orderData = $data['order'];

        SquareOrder::updateOrCreate(
            ['connection_id' => $connection->id, 'order_id' => $orderData['id']],
            [
                'location_id' => $orderData['location_id'],
                'state' => $orderData['state'],
                'total_money_cents' => $orderData['total_money']['amount'],
                'currency' => $orderData['total_money']['currency'],
                'line_items' => $orderData['line_items'] ?? null,
                'fulfillments' => $orderData['fulfillments'] ?? null,
                'source' => $orderData['source']['name'] ?? null,
                'created_at_square' => now()->parse($orderData['created_at']),
                'closed_at' => isset($orderData['closed_at']) ? now()->parse($orderData['closed_at']) : null,
            ]
        );
    }

    // ==========================================
    // HELPER METHODS
    // ==========================================

    protected function makeRequest(
        SquareConnection $connection,
        string $method,
        string $endpoint,
        array $params = []
    ): array {
        // Refresh token if expired
        if ($connection->token_expires_at && $connection->token_expires_at->isPast()) {
            $this->refreshToken($connection);
            $connection->refresh();
        }

        $baseUrl = $connection->isProduction() ? $this->apiBaseUrl : $this->sandboxUrl;
        $url = $baseUrl . $endpoint;

        $request = Http::withToken($connection->access_token)
            ->withHeaders(['Square-Version' => '2024-01-18']);

        $response = match (strtoupper($method)) {
            'GET' => $request->get($url, $params),
            'POST' => $request->post($url, $params),
            'PUT' => $request->put($url, $params),
            'DELETE' => $request->delete($url, $params),
            default => throw new \InvalidArgumentException("Unsupported HTTP method: {$method}"),
        };

        if (!$response->successful()) {
            $errors = $response->json('errors') ?? [];
            $errorMessage = $errors[0]['detail'] ?? 'Square API request failed';
            throw new \Exception($errorMessage, $response->status());
        }

        $connection->update(['last_used_at' => now()]);

        return $response->json() ?? [];
    }

    public function getConnection(int $tenantId): ?SquareConnection
    {
        return SquareConnection::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->first();
    }
}
