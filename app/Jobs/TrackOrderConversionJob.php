<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\Platform\CoreCustomer;
use App\Services\Platform\PlatformTrackingService;
use App\Services\Analytics\MilestoneAttributionService;
use App\Services\Analytics\RealTimeAnalyticsService;
use App\Services\OrganizerNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class TrackOrderConversionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [30, 60, 120];

    public function __construct(
        public int $orderId,
        public bool $notifyOrganizer = false
    ) {
        $this->onQueue('analytics');
    }

    public function handle(
        PlatformTrackingService $trackingService,
        MilestoneAttributionService $attributionService,
        RealTimeAnalyticsService $realTimeService
    ): void {
        $order = Order::find($this->orderId);
        if (!$order) {
            return;
        }

        try {
            // 1. Find or create CoreCustomer
            $coreCustomer = $this->findCoreCustomer($order);

            // 2. Track the purchase conversion
            $trackingData = $this->buildTrackingData($order, $coreCustomer);
            $trackingService->trackPurchase($trackingData, $order);

            Log::info('Order conversion tracked via job', [
                'order_id' => $order->id,
                'tenant_id' => $order->tenant_id,
                'total' => $order->total_cents / 100,
            ]);

            // 3. Track organizer analytics (milestone attribution + real-time)
            if ($order->marketplace_event_id) {
                $milestone = $attributionService->attributePurchase($order);
                $realTimeService->trackPurchaseCompleted($order, $milestone);

                if ($milestone) {
                    Log::info('Order attributed to milestone', [
                        'order_id' => $order->id,
                        'milestone_id' => $milestone->id,
                    ]);
                }
            }

            // 4. Notify organizer
            if ($this->notifyOrganizer && $order->marketplace_organizer_id) {
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
            Log::error('TrackOrderConversionJob failed', [
                'order_id' => $this->orderId,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    protected function findCoreCustomer(Order $order): ?CoreCustomer
    {
        if (!$order->customer_email) {
            return null;
        }

        return CoreCustomer::findByEmail($order->customer_email);
    }

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

        if ($coreCustomer) {
            $data['visitor_id'] = $coreCustomer->visitor_id;
            $data['session_token'] = 'order_' . $order->id;

            if ($coreCustomer->last_gclid) $data['gclid'] = $coreCustomer->last_gclid;
            if ($coreCustomer->last_fbclid) $data['fbclid'] = $coreCustomer->last_fbclid;
            if ($coreCustomer->last_ttclid) $data['ttclid'] = $coreCustomer->last_ttclid;
            if ($coreCustomer->last_li_fat_id) $data['li_fat_id'] = $coreCustomer->last_li_fat_id;
            if ($coreCustomer->last_utm_source) $data['utm_source'] = $coreCustomer->last_utm_source;
            if ($coreCustomer->last_utm_medium) $data['utm_medium'] = $coreCustomer->last_utm_medium;
            if ($coreCustomer->last_utm_campaign) $data['utm_campaign'] = $coreCustomer->last_utm_campaign;

            $data['first_name'] = $coreCustomer->first_name;
            $data['last_name'] = $coreCustomer->last_name;
            $data['phone'] = $coreCustomer->phone;
        }

        $meta = $order->meta ?? [];
        foreach (['gclid', 'fbclid', 'ttclid', 'li_fat_id', 'utm_source', 'utm_medium', 'utm_campaign'] as $key) {
            if (!empty($meta[$key])) {
                $data[$key] = $meta[$key];
            }
        }

        return $data;
    }
}
