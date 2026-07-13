<?php

namespace App\Observers;

use App\Jobs\SendGa4MpPurchaseJob;
use App\Jobs\SendTiktokEventsApiPurchaseJob;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Layer C for GA4 Measurement Protocol + TikTok Events API.
 *
 * Mirrors FacebookCapiOrderObserver: hooks Order status transitions
 * into a paid state and dispatches server-side Purchase events for GA4
 * and TikTok. The two jobs each apply their own organizer→marketplace
 * fallback so an Order can end up firing GA4 through the organizer's
 * property and TikTok through the marketplace's pixel, or any
 * combination — decided per-provider.
 *
 * Dedup with the browser layer (Layer B via /marketplace-tracking/track
 * + the thank-you page's EPASTracking.trackPurchase call) is handled by
 * each provider's native mechanism:
 *   - GA4: dedupes purchases by transaction_id = order_id.
 *   - TikTok: dedupes by event_id = 'purchase_{order_id}'.
 *   - (Meta CAPI already handled by FacebookCapiOrderObserver, not here.)
 */
class ServerSidePurchaseOrderObserver
{
    private const PAID_STATUSES = ['paid', 'confirmed', 'completed'];
    private const SKIP_SOURCES = ['legacy_import', 'external_import'];

    public function created(Order $order): void
    {
        if (in_array($order->source ?? '', self::SKIP_SOURCES, true)) {
            return;
        }
        if (!in_array($order->status, self::PAID_STATUSES, true)) {
            return;
        }
        $this->dispatchAfterCommit($order);
    }

    public function updated(Order $order): void
    {
        if (!$order->isDirty('status')) {
            return;
        }
        $newStatus = $order->status;
        $oldStatus = $order->getOriginal('status');

        if (!in_array($newStatus, self::PAID_STATUSES, true)) {
            return;
        }
        if (in_array($oldStatus, self::PAID_STATUSES, true)) {
            return;
        }
        $this->dispatchAfterCommit($order);
    }

    protected function dispatchAfterCommit(Order $order): void
    {
        $orderId = $order->id;
        DB::afterCommit(function () use ($orderId) {
            try {
                SendGa4MpPurchaseJob::dispatch($orderId);
            } catch (\Throwable $e) {
                Log::warning('Server-side Purchase: failed to dispatch GA4 MP job', [
                    'order_id' => $orderId,
                    'error' => $e->getMessage(),
                ]);
            }

            try {
                SendTiktokEventsApiPurchaseJob::dispatch($orderId);
            } catch (\Throwable $e) {
                Log::warning('Server-side Purchase: failed to dispatch TikTok EAPI job', [
                    'order_id' => $orderId,
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }
}
