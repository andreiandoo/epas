<?php

namespace App\Services\Shop;

use App\Models\Shop\ShopOrder;
use App\Models\Shop\ShopCart;
use App\Models\Shop\ShopProduct;
use App\Models\Shop\ShopCartItem;
use App\Models\Tenant;
use App\Services\Integrations\GoogleAds\GoogleAdsService;
use App\Services\Integrations\FacebookCapi\FacebookCapiService;
use App\Services\Integrations\TikTokAds\TikTokAdsService;
use App\Services\Analytics\AnalyticsService;
use App\Services\Tracking\TrackingEventBus;
use Illuminate\Support\Facades\Log;

class ShopTrackingService
{
    public function __construct(
        protected GoogleAdsService $googleAdsService,
        protected FacebookCapiService $facebookCapiService,
        protected TikTokAdsService $tikTokAdsService,
        protected AnalyticsService $analyticsService
    ) {}

    // ==========================================
    // PRODUCT VIEW TRACKING
    // ==========================================

    public function trackProductView(
        Tenant $tenant,
        ShopProduct $product,
        array $context = []
    ): void {
        $language = $context['language'] ?? $tenant->language ?? 'en';
        $currency = $tenant->settings['currency'] ?? 'RON';

        $productData = $this->formatProductForTracking($product, $language);

        // Track to analytics
        $this->trackToAnalytics($tenant, 'shop_product_view', [
            'product_id' => $product->id,
            'product_title' => $productData['item_name'],
            'product_category' => $productData['item_category'],
            'value' => $productData['price'],
            'currency' => $currency,
        ], $context);

        // Track to Facebook CAPI (ViewContent)
        $this->trackToFacebook($tenant, 'ViewContent', [
            'content_ids' => [$product->id],
            'content_name' => $productData['item_name'],
            'content_category' => $productData['item_category'],
            'content_type' => 'product',
            'value' => $productData['price'],
            'currency' => $currency,
        ], $context);

        // Track to TikTok (ViewContent)
        $this->trackToTikTok($tenant, 'ViewContent', [
            'contents' => [
                [
                    'content_id' => $product->id,
                    'content_name' => $productData['item_name'],
                    'content_category' => $productData['item_category'],
                    'price' => $productData['price'],
                    'quantity' => 1,
                ],
            ],
            'value' => $productData['price'],
            'currency' => $currency,
        ], $context);

        // Emit frontend tracking event
        $this->emitFrontendEvent('view_item', [
            'value' => $productData['price'],
            'currency' => $currency,
            'items' => [$productData],
        ]);
    }

    // ==========================================
    // ADD TO CART TRACKING
    // ==========================================

    public function trackAddToCart(
        Tenant $tenant,
        ShopCart $cart,
        ShopCartItem $item,
        ShopProduct $product,
        array $context = []
    ): void {
        $language = $context['language'] ?? $tenant->language ?? 'en';
        $currency = $cart->currency ?? $tenant->settings['currency'] ?? 'RON';

        $productData = $this->formatCartItemForTracking($item, $product, $language);
        $value = $item->total_cents / 100;

        // Track to analytics
        $this->trackToAnalytics($tenant, 'shop_add_to_cart', [
            'product_id' => $product->id,
            'product_title' => $productData['item_name'],
            'variant_id' => $item->variant_id,
            'quantity' => $item->quantity,
            'value' => $value,
            'currency' => $currency,
        ], $context);

        // Track to Google Ads
        $this->trackToGoogleAds($tenant, 'AddToCart', [
            'value' => $value,
            'currency' => $currency,
            'items' => [$productData],
        ], $context);

        // Track to Facebook CAPI
        $this->trackToFacebook($tenant, 'AddToCart', [
            'content_ids' => [$product->id],
            'content_name' => $productData['item_name'],
            'content_type' => 'product',
            'value' => $value,
            'currency' => $currency,
            'num_items' => $item->quantity,
        ], $context);

        // Track to TikTok
        $this->trackToTikTok($tenant, 'AddToCart', [
            'contents' => [
                [
                    'content_id' => $product->id,
                    'content_name' => $productData['item_name'],
                    'price' => $productData['price'],
                    'quantity' => $item->quantity,
                ],
            ],
            'value' => $value,
            'currency' => $currency,
        ], $context);

        // Emit frontend tracking event
        $this->emitFrontendEvent('add_to_cart', [
            'value' => $value,
            'currency' => $currency,
            'items' => [$productData],
        ]);
    }

    // ==========================================
    // CHECKOUT STARTED TRACKING
    // ==========================================

    public function trackCheckoutStarted(
        Tenant $tenant,
        ShopCart $cart,
        array $context = []
    ): void {
        $language = $context['language'] ?? $tenant->language ?? 'en';
        $currency = $cart->currency ?? $tenant->settings['currency'] ?? 'RON';

        $items = $this->formatCartItemsForTracking($cart, $language);
        $value = $cart->getSubtotalCents() / 100;

        // Track to analytics
        $this->trackToAnalytics($tenant, 'shop_checkout_started', [
            'cart_id' => $cart->id,
            'item_count' => $cart->getItemCount(),
            'value' => $value,
            'currency' => $currency,
        ], $context);

        // Track to Google Ads
        $this->trackToGoogleAds($tenant, 'BeginCheckout', [
            'value' => $value,
            'currency' => $currency,
            'items' => $items,
        ], $context);

        // Track to Facebook CAPI
        $this->trackToFacebook($tenant, 'InitiateCheckout', [
            'content_ids' => $cart->items->pluck('product_id')->toArray(),
            'content_type' => 'product',
            'value' => $value,
            'currency' => $currency,
            'num_items' => $cart->getItemCount(),
        ], $context);

        // Track to TikTok
        $this->trackToTikTok($tenant, 'InitiateCheckout', [
            'contents' => $items,
            'value' => $value,
            'currency' => $currency,
        ], $context);

        // Emit frontend tracking event
        $this->emitFrontendEvent('begin_checkout', [
            'value' => $value,
            'currency' => $currency,
            'items' => $items,
        ]);
    }

    // ==========================================
    // ORDER COMPLETED (PURCHASE) TRACKING
    // ==========================================

    public function trackPurchase(
        Tenant $tenant,
        ShopOrder $order,
        array $context = []
    ): void {
        $language = $context['language'] ?? $tenant->language ?? 'en';
        $currency = $order->currency ?? $tenant->settings['currency'] ?? 'RON';

        $items = $this->formatOrderItemsForTracking($order, $language);
        $value = $order->total_cents / 100;

        $userData = [
            'email' => $order->customer_email,
            'phone' => $order->customer_phone,
        ];

        if ($order->billing_address) {
            $userData = array_merge($userData, [
                'first_name' => $order->billing_address['first_name'] ?? $order->billing_address['name'] ?? null,
                'last_name' => $order->billing_address['last_name'] ?? null,
                'city' => $order->billing_address['city'] ?? null,
                'state' => $order->billing_address['state'] ?? $order->billing_address['region'] ?? null,
                'zip' => $order->billing_address['postal_code'] ?? $order->billing_address['zip'] ?? null,
                'country' => $order->billing_address['country'] ?? null,
            ]);
        }

        // Track to analytics
        $this->trackToAnalytics($tenant, 'shop_order_completed', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'customer_email' => $order->customer_email,
            'item_count' => $order->items->count(),
            'subtotal' => $order->subtotal_cents / 100,
            'discount' => $order->discount_cents / 100,
            'shipping' => $order->shipping_cents / 100,
            'tax' => $order->tax_cents / 100,
            'value' => $value,
            'currency' => $currency,
            'coupon_code' => $order->coupon_code,
            'has_digital_products' => $order->hasDigitalProducts(),
            'has_physical_products' => $order->hasPhysicalProducts(),
        ], $context);

        // Track to Google Ads (Purchase)
        $this->trackToGoogleAds($tenant, 'Purchase', [
            'transaction_id' => $order->order_number,
            'value' => $value,
            'currency' => $currency,
            'items' => $items,
            'user_data' => $userData,
        ], $context);

        // Track to Facebook CAPI (Purchase)
        $this->trackToFacebook($tenant, 'Purchase', [
            'content_ids' => $order->items->pluck('product_id')->toArray(),
            'content_type' => 'product',
            'value' => $value,
            'currency' => $currency,
            'num_items' => $order->items->sum('quantity'),
            'order_id' => $order->order_number,
            'user_data' => $userData,
        ], $context);

        // Track to TikTok (CompletePayment)
        $this->trackToTikTok($tenant, 'CompletePayment', [
            'contents' => array_map(function ($item) {
                return [
                    'content_id' => $item['item_id'],
                    'content_name' => $item['item_name'],
                    'price' => $item['price'],
                    'quantity' => $item['quantity'],
                ];
            }, $items),
            'value' => $value,
            'currency' => $currency,
            'order_id' => $order->order_number,
        ], $context);

        // Emit frontend tracking event
        $this->emitFrontendEvent('purchase', [
            'transaction_id' => $order->order_number,
            'value' => $value,
            'currency' => $currency,
            'tax' => $order->tax_cents / 100,
            'shipping' => $order->shipping_cents / 100,
            'coupon' => $order->coupon_code,
            'items' => $items,
        ]);
    }

    // ==========================================
    // REFUND TRACKING
    // ==========================================

    public function trackRefund(
        Tenant $tenant,
        ShopOrder $order,
        int $refundAmountCents,
        array $context = []
    ): void {
        $currency = $order->currency ?? $tenant->settings['currency'] ?? 'RON';
        $value = $refundAmountCents / 100;

        // Track to analytics
        $this->trackToAnalytics($tenant, 'shop_order_refunded', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'refund_amount' => $value,
            'currency' => $currency,
        ], $context);

        // Track refund to Google Ads (negative conversion)
        // Note: Google Ads handles refunds differently, typically through the API
        Log::info('Shop order refunded', [
            'order_number' => $order->order_number,
            'refund_amount' => $value,
        ]);
    }

    // ==========================================
    // HELPER METHODS - FORMATTING
    // ==========================================

    protected function formatProductForTracking(ShopProduct $product, string $language): array
    {
        return [
            'item_id' => $product->id,
            'item_name' => $product->getTranslation('title', $language),
            'item_category' => $product->category?->getTranslation('name', $language) ?? 'Uncategorized',
            'price' => $product->display_price_cents / 100,
            'quantity' => 1,
        ];
    }

    protected function formatCartItemForTracking(ShopCartItem $item, ShopProduct $product, string $language): array
    {
        $variant = $item->variant;

        return [
            'item_id' => $product->id,
            'item_name' => $product->getTranslation('title', $language),
            'item_category' => $product->category?->getTranslation('name', $language) ?? 'Uncategorized',
            'item_variant' => $variant?->getAttributeLabel(),
            'price' => $item->unit_price_cents / 100,
            'quantity' => $item->quantity,
        ];
    }

    protected function formatCartItemsForTracking(ShopCart $cart, string $language): array
    {
        $cart->load(['items.product.category', 'items.variant']);

        return $cart->items->map(function ($item) use ($language) {
            return $this->formatCartItemForTracking($item, $item->product, $language);
        })->toArray();
    }

    protected function formatOrderItemsForTracking(ShopOrder $order, string $language): array
    {
        return $order->items->map(function ($item) use ($language) {
            return [
                'item_id' => $item->product_id,
                'item_name' => $item->product_title,
                'item_variant' => $item->variant_title,
                'price' => $item->unit_price_cents / 100,
                'quantity' => $item->quantity,
            ];
        })->toArray();
    }

    // ==========================================
    // HELPER METHODS - TRACKING PROVIDERS
    // ==========================================

    protected function trackToAnalytics(Tenant $tenant, string $eventType, array $properties, array $context): void
    {
        try {
            $this->analyticsService->trackEvent([
                'tenant_id' => $tenant->id,
                'event_type' => $eventType,
                'properties' => $properties,
                'session_id' => $context['session_id'] ?? null,
                'user_agent' => $context['user_agent'] ?? null,
                'ip_address' => $context['ip_address'] ?? null,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to track shop event to analytics', [
                'event_type' => $eventType,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function trackToGoogleAds(Tenant $tenant, string $eventName, array $data, array $context): void
    {
        try {
            // Check if Google Ads integration is enabled
            $connection = $tenant->integrationConnections()
                ->where('provider', 'google_ads')
                ->where('status', 'connected')
                ->first();

            if (!$connection) {
                return;
            }

            $enabledConversions = $connection->settings['enabled_conversions'] ?? [];
            if (!in_array($eventName, $enabledConversions)) {
                return;
            }

            if ($eventName === 'Purchase') {
                $this->googleAdsService->trackPurchase(
                    $tenant->id,
                    $data['transaction_id'],
                    $data['value'],
                    $data['currency'],
                    $data['user_data'] ?? [],
                    $context
                );
            }
        } catch (\Exception $e) {
            Log::error('Failed to track shop event to Google Ads', [
                'event_name' => $eventName,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function trackToFacebook(Tenant $tenant, string $eventName, array $data, array $context): void
    {
        try {
            // Check if Facebook CAPI integration is enabled
            $connection = $tenant->integrationConnections()
                ->where('provider', 'facebook_capi')
                ->where('status', 'connected')
                ->first();

            if (!$connection) {
                return;
            }

            $enabledEvents = $connection->settings['enabled_events'] ?? [];
            if (!in_array($eventName, $enabledEvents)) {
                return;
            }

            $this->facebookCapiService->sendEvent(
                $tenant->id,
                $eventName,
                $data,
                $data['user_data'] ?? [],
                $context
            );
        } catch (\Exception $e) {
            Log::error('Failed to track shop event to Facebook CAPI', [
                'event_name' => $eventName,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function trackToTikTok(Tenant $tenant, string $eventName, array $data, array $context): void
    {
        try {
            // Check if TikTok Ads integration is enabled
            $connection = $tenant->integrationConnections()
                ->where('provider', 'tiktok_ads')
                ->where('status', 'connected')
                ->first();

            if (!$connection) {
                return;
            }

            $enabledEvents = $connection->settings['enabled_events'] ?? [];
            if (!in_array($eventName, $enabledEvents)) {
                return;
            }

            $this->tikTokAdsService->sendEvent(
                $tenant->id,
                $eventName,
                $data,
                $context
            );
        } catch (\Exception $e) {
            Log::error('Failed to track shop event to TikTok', [
                'event_name' => $eventName,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function emitFrontendEvent(string $eventName, array $data): void
    {
        // This creates JavaScript that can be injected into the frontend response
        // The actual emission happens on the client side
        // This method generates the tracking data that should be sent to the frontend
        TrackingEventBus::emit("tracking:{$eventName}", $data);
    }

    // ==========================================
    // GET TRACKING DATA FOR FRONTEND
    // ==========================================

    public function getProductTrackingData(ShopProduct $product, string $language, string $currency): array
    {
        return [
            'event' => 'view_item',
            'data' => [
                'value' => $product->display_price_cents / 100,
                'currency' => $currency,
                'items' => [$this->formatProductForTracking($product, $language)],
            ],
        ];
    }

    public function getCartTrackingData(ShopCart $cart, string $language): array
    {
        $currency = $cart->currency ?? 'RON';

        return [
            'event' => 'view_cart',
            'data' => [
                'value' => $cart->getSubtotalCents() / 100,
                'currency' => $currency,
                'items' => $this->formatCartItemsForTracking($cart, $language),
            ],
        ];
    }

    public function getPurchaseTrackingData(ShopOrder $order, string $language): array
    {
        return [
            'event' => 'purchase',
            'data' => [
                'transaction_id' => $order->order_number,
                'value' => $order->total_cents / 100,
                'currency' => $order->currency,
                'tax' => $order->tax_cents / 100,
                'shipping' => $order->shipping_cents / 100,
                'coupon' => $order->coupon_code,
                'items' => $this->formatOrderItemsForTracking($order, $language),
            ],
        ];
    }
}
