<?php

namespace App\Listeners\Shop;

use App\Events\Shop\ShopProductViewed;
use App\Events\Shop\ShopItemAddedToCart;
use App\Events\Shop\ShopCheckoutStarted;
use App\Events\Shop\ShopOrderCreated;
use App\Events\Shop\ShopOrderCompleted;
use App\Events\Shop\ShopOrderRefunded;
use App\Models\Tenant;
use App\Services\Shop\ShopTrackingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class ShopTrackingListener implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(
        protected ShopTrackingService $trackingService
    ) {}

    public function handleProductViewed(ShopProductViewed $event): void
    {
        try {
            $tenant = Tenant::find($event->tenantId);
            if (!$tenant) {
                return;
            }

            $this->trackingService->trackProductView(
                $tenant,
                $event->product,
                array_merge($event->context, [
                    'customer_id' => $event->customerId,
                    'session_id' => $event->sessionId,
                ])
            );
        } catch (\Exception $e) {
            Log::error('ShopTrackingListener: Failed to track product view', [
                'product_id' => $event->product->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function handleItemAddedToCart(ShopItemAddedToCart $event): void
    {
        try {
            $tenant = Tenant::find($event->tenantId);
            if (!$tenant) {
                return;
            }

            $this->trackingService->trackAddToCart(
                $tenant,
                $event->cart,
                $event->item,
                $event->product,
                $event->context
            );
        } catch (\Exception $e) {
            Log::error('ShopTrackingListener: Failed to track add to cart', [
                'cart_id' => $event->cart->id,
                'product_id' => $event->product->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function handleCheckoutStarted(ShopCheckoutStarted $event): void
    {
        try {
            $tenant = Tenant::find($event->tenantId);
            if (!$tenant) {
                return;
            }

            $this->trackingService->trackCheckoutStarted(
                $tenant,
                $event->cart,
                array_merge($event->context, [
                    'customer_id' => $event->customerId,
                ])
            );
        } catch (\Exception $e) {
            Log::error('ShopTrackingListener: Failed to track checkout started', [
                'cart_id' => $event->cart->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function handleOrderCreated(ShopOrderCreated $event): void
    {
        try {
            // Order created but not yet paid - track as PlaceOrder
            Log::info('Shop order created', [
                'order_number' => $event->order->order_number,
                'tenant_id' => $event->tenantId,
            ]);
        } catch (\Exception $e) {
            Log::error('ShopTrackingListener: Failed to track order created', [
                'order_id' => $event->order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function handleOrderCompleted(ShopOrderCompleted $event): void
    {
        try {
            $tenant = Tenant::find($event->tenantId);
            if (!$tenant) {
                return;
            }

            $this->trackingService->trackPurchase(
                $tenant,
                $event->order,
                array_merge($event->context, [
                    'payment_data' => $event->paymentData,
                ])
            );
        } catch (\Exception $e) {
            Log::error('ShopTrackingListener: Failed to track order completed', [
                'order_id' => $event->order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function handleOrderRefunded(ShopOrderRefunded $event): void
    {
        try {
            $tenant = Tenant::find($event->tenantId);
            if (!$tenant) {
                return;
            }

            $this->trackingService->trackRefund(
                $tenant,
                $event->order,
                $event->refundAmountCents,
                array_merge($event->context, [
                    'reason' => $event->reason,
                ])
            );
        } catch (\Exception $e) {
            Log::error('ShopTrackingListener: Failed to track order refunded', [
                'order_id' => $event->order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Register the listeners for the subscriber.
     */
    public function subscribe($events): array
    {
        return [
            ShopProductViewed::class => 'handleProductViewed',
            ShopItemAddedToCart::class => 'handleItemAddedToCart',
            ShopCheckoutStarted::class => 'handleCheckoutStarted',
            ShopOrderCreated::class => 'handleOrderCreated',
            ShopOrderCompleted::class => 'handleOrderCompleted',
            ShopOrderRefunded::class => 'handleOrderRefunded',
        ];
    }
}
