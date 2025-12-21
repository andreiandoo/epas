<?php

namespace App\Observers;

use App\Models\Order;
use App\Models\Platform\CoreCustomer;
use App\Notifications\Marketplace\NewOrganizerOrder;
use App\Services\Marketplace\CommissionService;
use App\Services\Platform\PlatformTrackingService;
use Illuminate\Support\Facades\Log;

class OrderObserver
{
    protected PlatformTrackingService $trackingService;
    protected CommissionService $commissionService;

    public function __construct(
        PlatformTrackingService $trackingService,
        CommissionService $commissionService
    ) {
        $this->trackingService = $trackingService;
        $this->commissionService = $commissionService;
    }

    /**
     * Handle the Order "created" event.
     */
    public function created(Order $order): void
    {
        // Track new orders if they're already paid/confirmed
        if (in_array($order->status, ['paid', 'confirmed', 'completed'])) {
            $this->trackPurchaseConversion($order);

            // Calculate commission for marketplace orders created as paid
            $this->calculateMarketplaceCommission($order);
        }
    }

    /**
     * Handle the Order "updated" event.
     */
    public function updated(Order $order): void
    {
        // Check if status changed to a conversion-triggering status
        if ($order->isDirty('status')) {
            $newStatus = $order->status;
            $oldStatus = $order->getOriginal('status');

            // Only track when transitioning TO a paid/confirmed status
            // Not when already in that status
            if (in_array($newStatus, ['paid', 'confirmed', 'completed']) &&
                !in_array($oldStatus, ['paid', 'confirmed', 'completed'])) {
                $this->trackPurchaseConversion($order);

                // Calculate commission for marketplace orders
                $this->calculateMarketplaceCommission($order);
            }
        }
    }

    /**
     * Calculate and store commission for marketplace orders.
     */
    protected function calculateMarketplaceCommission(Order $order): void
    {
        // Only process marketplace orders (orders with an organizer)
        if (!$order->isMarketplaceOrder()) {
            return;
        }

        try {
            // Calculate and store commission breakdown
            $breakdown = $this->commissionService->calculateForOrder($order);

            // Notify organizer of new order
            $organizer = $order->organizer;
            if ($organizer) {
                // Notify all admin users of the organizer
                $adminUsers = $organizer->adminUsers()->get();
                foreach ($adminUsers as $user) {
                    $user->notify(new NewOrganizerOrder($order));
                }

                // Refresh organizer statistics
                $organizer->refreshStatistics();
            }

            Log::info('Marketplace order commission calculated', [
                'order_id' => $order->id,
                'organizer_id' => $order->organizer_id,
                'breakdown' => $breakdown,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to calculate marketplace commission', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Track a purchase conversion for dual-tracking
     */
    protected function trackPurchaseConversion(Order $order): void
    {
        try {
            // Find the CoreCustomer associated with this order
            $coreCustomer = $this->findOrCreateCoreCustomer($order);

            // Build tracking data from order and customer
            $trackingData = $this->buildTrackingData($order, $coreCustomer);

            // Track the purchase (this handles dual-tracking to all platforms)
            $this->trackingService->trackPurchase($trackingData, $order);

            Log::info('Order conversion tracked for dual-tracking', [
                'order_id' => $order->id,
                'tenant_id' => $order->tenant_id,
                'total' => $order->total_cents / 100,
                'core_customer_id' => $coreCustomer?->id,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to track order conversion', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Find or create CoreCustomer from Order
     */
    protected function findOrCreateCoreCustomer(Order $order): ?CoreCustomer
    {
        if (!$order->customer_email) {
            return null;
        }

        // Try to find existing CoreCustomer by email
        $coreCustomer = CoreCustomer::findByEmail($order->customer_email);

        if ($coreCustomer) {
            return $coreCustomer;
        }

        // Create new CoreCustomer from order data
        $customer = $order->customer;

        return CoreCustomer::create([
            'tenant_id' => $order->tenant_id,
            'email' => $order->customer_email,
            'email_hash' => hash('sha256', strtolower(trim($order->customer_email))),
            'first_name' => $customer?->first_name ?? $order->meta['first_name'] ?? null,
            'last_name' => $customer?->last_name ?? $order->meta['last_name'] ?? null,
            'phone' => $customer?->phone ?? $order->meta['phone'] ?? null,
            'phone_hash' => isset($order->meta['phone'])
                ? hash('sha256', preg_replace('/[^0-9]/', '', $order->meta['phone']))
                : null,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'total_orders' => 1,
            'total_spent' => $order->total_cents / 100,
            'first_order_at' => now(),
            'last_order_at' => now(),
        ]);
    }

    /**
     * Build tracking data from order and customer
     */
    protected function buildTrackingData(Order $order, ?CoreCustomer $coreCustomer): array
    {
        $data = [
            'tenant_id' => $order->tenant_id,
            'email' => $order->customer_email,
            'order_id' => $order->id,
            'order_total' => $order->total_cents / 100,
            'currency' => $order->meta['currency'] ?? 'USD',
            'ticket_count' => $order->tickets()->count(),
            'event_data' => [
                'order_source' => 'backend',
                'order_status' => $order->status,
            ],
        ];

        // Include tracking click IDs if available from CoreCustomer
        if ($coreCustomer) {
            $data['visitor_id'] = $coreCustomer->visitor_id;
            $data['session_token'] = 'order_' . $order->id;

            // Use last click IDs for attribution (last-click model)
            if ($coreCustomer->last_gclid) {
                $data['gclid'] = $coreCustomer->last_gclid;
            }
            if ($coreCustomer->last_fbclid) {
                $data['fbclid'] = $coreCustomer->last_fbclid;
            }
            if ($coreCustomer->last_ttclid) {
                $data['ttclid'] = $coreCustomer->last_ttclid;
            }
            if ($coreCustomer->last_li_fat_id) {
                $data['li_fat_id'] = $coreCustomer->last_li_fat_id;
            }

            // Include UTM data
            if ($coreCustomer->last_utm_source) {
                $data['utm_source'] = $coreCustomer->last_utm_source;
            }
            if ($coreCustomer->last_utm_medium) {
                $data['utm_medium'] = $coreCustomer->last_utm_medium;
            }
            if ($coreCustomer->last_utm_campaign) {
                $data['utm_campaign'] = $coreCustomer->last_utm_campaign;
            }

            // Customer info
            $data['first_name'] = $coreCustomer->first_name;
            $data['last_name'] = $coreCustomer->last_name;
            $data['phone'] = $coreCustomer->phone;
        }

        // Also check order meta for tracking info (in case it was stored during checkout)
        $meta = $order->meta ?? [];
        if (!empty($meta['gclid'])) {
            $data['gclid'] = $meta['gclid'];
        }
        if (!empty($meta['fbclid'])) {
            $data['fbclid'] = $meta['fbclid'];
        }
        if (!empty($meta['ttclid'])) {
            $data['ttclid'] = $meta['ttclid'];
        }
        if (!empty($meta['li_fat_id'])) {
            $data['li_fat_id'] = $meta['li_fat_id'];
        }
        if (!empty($meta['utm_source'])) {
            $data['utm_source'] = $meta['utm_source'];
        }
        if (!empty($meta['utm_medium'])) {
            $data['utm_medium'] = $meta['utm_medium'];
        }
        if (!empty($meta['utm_campaign'])) {
            $data['utm_campaign'] = $meta['utm_campaign'];
        }

        return $data;
    }

    /**
     * Handle the Order "deleted" event.
     */
    public function deleted(Order $order): void
    {
        // Could track refund/cancellation for negative conversions
    }
}
