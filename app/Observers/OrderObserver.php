<?php

namespace App\Observers;

use App\Models\MarketplaceCustomer;
use App\Models\Order;
use App\Models\Platform\CoreCustomer;
use App\Services\Platform\PlatformTrackingService;
use App\Services\Analytics\MilestoneAttributionService;
use App\Services\Analytics\RealTimeAnalyticsService;
use App\Services\OrganizerNotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderObserver
{
    protected PlatformTrackingService $trackingService;
    protected MilestoneAttributionService $attributionService;
    protected RealTimeAnalyticsService $realTimeService;

    public function __construct(
        PlatformTrackingService $trackingService,
        MilestoneAttributionService $attributionService,
        RealTimeAnalyticsService $realTimeService
    ) {
        $this->trackingService = $trackingService;
        $this->attributionService = $attributionService;
        $this->realTimeService = $realTimeService;
    }

    /**
     * Auto-fill marketplace_organizer_id from event if missing.
     */
    public function creating(Order $order): void
    {
        if (!$order->marketplace_organizer_id && $order->event_id) {
            $event = \App\Models\Event::find($order->event_id);
            if ($event?->marketplace_organizer_id) {
                $order->marketplace_organizer_id = $event->marketplace_organizer_id;
            }
        }
    }

    /**
     * Handle the Order "created" event.
     *
     * Tracking is deferred to afterCommit so its DB writes can never poison
     * the outer checkout transaction. If the observer runs outside any
     * transaction, afterCommit fires immediately.
     */
    public function created(Order $order): void
    {
        // Skip tracking for imported orders (legacy/external)
        if (in_array($order->source, ['legacy_import', 'external_import'])) {
            return;
        }

        // Track new orders if they're already paid/confirmed
        if (in_array($order->status, ['paid', 'confirmed', 'completed'])) {
            DB::afterCommit(fn () => $this->trackPurchaseConversion($order));
        }

        // Cached aggregates on the marketplace customer
        if (in_array($order->status, MarketplaceCustomer::SUCCESS_ORDER_STATUSES, true)) {
            DB::afterCommit(fn () => $this->refreshCustomerStats($order->marketplace_customer_id));
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
                DB::afterCommit(fn () => $this->trackPurchaseConversion($order));
            }

            // Status moved into / out of the success set on the same customer
            $oldSuccess = in_array($oldStatus, MarketplaceCustomer::SUCCESS_ORDER_STATUSES, true);
            $newSuccess = in_array($newStatus, MarketplaceCustomer::SUCCESS_ORDER_STATUSES, true);
            if ($oldSuccess !== $newSuccess) {
                DB::afterCommit(fn () => $this->refreshCustomerStats($order->marketplace_customer_id));
            }
        }

        // Order moved between customers (rare — usually via OrderTransferService,
        // which already calls updateStats; this is a defensive net for any
        // direct ->update(['marketplace_customer_id' => ...]) elsewhere).
        if ($order->isDirty('marketplace_customer_id')) {
            $oldCustomerId = $order->getOriginal('marketplace_customer_id');
            $newCustomerId = $order->marketplace_customer_id;
            DB::afterCommit(function () use ($oldCustomerId, $newCustomerId) {
                $this->refreshCustomerStats($oldCustomerId);
                $this->refreshCustomerStats($newCustomerId);
            });
        }

        // Surface payment failures into the system_errors dashboard.
        if ($order->isDirty('payment_status')) {
            $newPayment = $order->payment_status;
            $oldPayment = $order->getOriginal('payment_status');
            if (in_array($newPayment, ['failed', 'declined', 'refused'], true)
                && !in_array($oldPayment, ['failed', 'declined', 'refused'], true)) {
                try {
                    /** @var \App\Logging\SystemErrorRecorder $recorder */
                    $recorder = app(\App\Logging\SystemErrorRecorder::class);
                    $recorder->record([
                        'level' => 400,
                        'channel' => 'marketplace',
                        'source' => 'order_status',
                        'message' => sprintf(
                            'Order %s payment_status: %s → %s',
                            $order->order_number ?? ('#' . $order->id),
                            $oldPayment ?? '(null)',
                            $newPayment
                        ),
                        'context' => [
                            'order_id' => $order->id,
                            'order_number' => $order->order_number ?? null,
                            'marketplace_client_id' => $order->marketplace_client_id ?? null,
                            'marketplace_organizer_id' => $order->marketplace_organizer_id ?? null,
                            'previous' => $oldPayment,
                            'current' => $newPayment,
                            'total' => $order->total ?? null,
                            'currency' => $order->currency ?? null,
                        ],
                    ]);
                } catch (\Throwable $e) {
                    // intentionally swallowed — error mirroring must not break checkout
                }
            }
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

            // Track for organizer analytics (milestone attribution + real-time)
            $this->trackOrganizerAnalytics($order, $coreCustomer);

            // Send notification to organizer for marketplace orders
            if ($order->marketplace_organizer_id) {
                try {
                    OrganizerNotificationService::notifySale($order);
                } catch (\Exception $e) {
                    Log::warning('Failed to send sale notification', [
                        'order_id' => $order->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

        } catch (\Exception $e) {
            Log::error('Failed to track order conversion', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Track purchase for organizer analytics (milestone attribution + real-time updates)
     */
    protected function trackOrganizerAnalytics(Order $order, ?CoreCustomer $coreCustomer): void
    {
        // Only process marketplace orders with event association
        if (!$order->marketplace_event_id) {
            return;
        }

        try {
            // 1. Attribute purchase to milestone (if matching campaign/milestone found)
            $attributedMilestone = $this->attributionService->attributePurchase($order);

            if ($attributedMilestone) {
                Log::info('Order attributed to milestone', [
                    'order_id' => $order->id,
                    'event_id' => $order->marketplace_event_id,
                    'milestone_id' => $attributedMilestone->id,
                    'milestone_type' => $attributedMilestone->type,
                ]);
            }

            // 2. Update real-time analytics for the event
            $this->realTimeService->trackPurchaseCompleted($order, $attributedMilestone);

        } catch (\Exception $e) {
            Log::error('Failed to track organizer analytics', [
                'order_id' => $order->id,
                'event_id' => $order->marketplace_event_id,
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

        // Use correct amount: marketplace orders use `total` (decimal), tenant orders use `total_cents`
        $orderAmount = $order->marketplace_client_id
            ? (float) $order->total
            : ($order->total_cents ?? 0) / 100;

        return CoreCustomer::create([
            'email' => $order->customer_email,
            'first_name' => $customer?->first_name ?? $order->meta['first_name'] ?? null,
            'last_name' => $customer?->last_name ?? $order->meta['last_name'] ?? null,
            'phone' => $customer?->phone ?? $order->meta['phone'] ?? null,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'first_purchase_at' => now(),
            'last_purchase_at' => now(),
            'currency' => $order->currency ?? ($order->marketplace_client_id ? 'RON' : 'EUR'),
        ]);
    }

    /**
     * Build tracking data from order and customer
     */
    protected function buildTrackingData(Order $order, ?CoreCustomer $coreCustomer): array
    {
        // Use correct amount: marketplace orders use `total` (decimal), tenant orders use `total_cents`
        $orderTotal = $order->marketplace_client_id
            ? (float) $order->total
            : ($order->total_cents ?? 0) / 100;

        $data = [
            'tenant_id' => $order->tenant_id,
            'email' => $order->customer_email,
            'order_id' => $order->id,
            'order_total' => $orderTotal,
            'currency' => $order->currency ?? $order->meta['currency'] ?? 'EUR',
            'ticket_count' => $order->tickets()->count(),
            'event_data' => [
                'order_source' => 'backend',
                'order_status' => $order->status,
            ],
        ];

        // Include tracking click IDs if available from CoreCustomer
        if ($coreCustomer) {
            $data['visitor_id'] = $coreCustomer->visitor_id;
            $data['session_token'] = $coreCustomer->session_id ?? \Illuminate\Support\Str::uuid()->toString();

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
        // Refresh cached aggregates after the order disappears
        DB::afterCommit(fn () => $this->refreshCustomerStats($order->marketplace_customer_id));
    }

    /**
     * Recompute cached total_orders/total_spent on a marketplace customer.
     * Tolerates null id and missing customer rows.
     */
    protected function refreshCustomerStats(?int $customerId): void
    {
        if (!$customerId) {
            return;
        }

        try {
            MarketplaceCustomer::find($customerId)?->updateStats();
        } catch (\Throwable $e) {
            Log::warning('Failed to refresh marketplace customer stats', [
                'marketplace_customer_id' => $customerId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
