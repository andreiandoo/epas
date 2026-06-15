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

        // Invalidate organizer-level breakdown cache on every new order so
        // the stats tab picks the row up at next render.
        DB::afterCommit(fn () => $this->bustOrganizerBreakdownCache($order));

        // PERF P2/8 — bust the per-event aggregate stats cache so the
        // organizer scan-app dashboard and the mobile app see the new
        // sale on next refresh (otherwise stale up to 60s). Tickets
        // attached to this order will also fire TicketObserver::created,
        // but we forget proactively here in case ticket creation lags.
        if ($order->event_id) {
            DB::afterCommit(fn () => \App\Services\EventStatsCache::forget($order->event_id));
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

                // Real-time broadcast — mobile dashboards subscribed to
                // event.{id}.sales refresh their counters instantly.
                // This covers EVERY web sale path that flows through
                // Eloquent updates (Stripe webhook, manual confirm, etc.).
                // POS-create paths use raw DB::table()->update() and fire
                // the event inline; everyone else lands here.
                DB::afterCommit(function () use ($order) {
                    try {
                        $eventId = $order->event_id ?? $order->marketplace_event_id;
                        if (!$eventId) {
                            return;
                        }
                        event(new \App\Events\Sales\OrderConfirmed(
                            (int) $eventId,
                            (int) $order->id,
                            (string) ($order->source ?? 'unknown'),
                            $order->tickets()->count(),
                        ));
                    } catch (\Throwable $e) {
                        Log::warning('OrderConfirmed broadcast (observer) failed', [
                            'order_id' => $order->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                });
            }

            // Status moved into / out of the success set on the same customer
            $oldSuccess = in_array($oldStatus, MarketplaceCustomer::SUCCESS_ORDER_STATUSES, true);
            $newSuccess = in_array($newStatus, MarketplaceCustomer::SUCCESS_ORDER_STATUSES, true);
            if ($oldSuccess !== $newSuccess) {
                DB::afterCommit(fn () => $this->refreshCustomerStats($order->marketplace_customer_id));
            }

            // Bust cached organizer breakdowns whenever the status crosses a
            // boundary that changes the breakdown service's output (it filters
            // by status IN paid/confirmed/completed). 5-min TTL covers other
            // edge cases on its own.
            DB::afterCommit(fn () => $this->bustOrganizerBreakdownCache($order));

            // PERF P2/8 — bust the per-event aggregate stats cache too.
            // Revenue depends on Order::sum(discount_amount) which is
            // status-filtered, so a status transition (refund, void) can
            // change the cached revenue figure.
            if ($order->event_id) {
                DB::afterCommit(fn () => \App\Services\EventStatsCache::forget($order->event_id));
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
        // Severity depends on the *previous* state:
        //   - pending/processing/null → failed: card declined / 3DS reject /
        //     insufficient funds. Business-as-usual; log at NOTICE so it
        //     stays visible for audits without polluting the error bucket.
        //   - paid/confirmed/completed → failed: a *successful* payment got
        //     undone. Genuinely alarming, log at ERROR.
        if ($order->isDirty('payment_status')) {
            $newPayment = $order->payment_status;
            $oldPayment = $order->getOriginal('payment_status');
            if (in_array($newPayment, ['failed', 'declined', 'refused'], true)
                && !in_array($oldPayment, ['failed', 'declined', 'refused'], true)) {
                $isRegression = in_array($oldPayment, ['paid', 'confirmed', 'completed'], true);
                $level = $isRegression ? 400 : 250; // ERROR vs NOTICE (Monolog)
                try {
                    /** @var \App\Logging\SystemErrorRecorder $recorder */
                    $recorder = app(\App\Logging\SystemErrorRecorder::class);
                    $recorder->record([
                        'level' => $level,
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
                            'is_regression' => $isRegression,
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
                } catch (\Throwable $e) {
                    // Catch \Throwable, not \Exception — PHP \Error (e.g.
                    // "Call to a member function sum() on null") does NOT
                    // extend \Exception, so the previous catch let it
                    // propagate up into PaymentController, which broke the
                    // ticket activation step right after the order update.
                    Log::warning('Failed to send sale notification', [
                        'order_id' => $order->id,
                        'error' => $e->getMessage(),
                        'error_class' => get_class($e),
                    ]);
                }
            }

            // Newsletter purchase attribution. Strict path: order carries a
            // newsletter_attribution_id set by the marketplace JS layer from
            // the `nl=` URL param / localStorage. Loose fallback: if no
            // attribution arrived through that path, look up the customer
            // email against recent (default 14d) click events and credit
            // the most recent matching newsletter — covers in-app browsers,
            // cross-device flows, cleared localStorage.
            if (!$order->newsletter_attribution_id) {
                try {
                    $this->tryEmailMatchAttribution($order);
                } catch (\Throwable $e) {
                    Log::warning('Newsletter email-match attribution failed', [
                        'order_id' => $order->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            if ($order->newsletter_attribution_id) {
                try {
                    $this->creditNewsletterAttribution($order);
                } catch (\Throwable $e) {
                    Log::warning('Newsletter attribution credit failed', [
                        'order_id' => $order->id,
                        'newsletter_id' => $order->newsletter_attribution_id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

        } catch (\Throwable $e) {
            Log::error('Failed to track order conversion', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
            ]);
        }
    }

    /**
     * Loose email-match attribution: when an order completes without a
     * newsletter_attribution_id (URL flow missed — in-app browser, cross-
     * device purchase, cleared localStorage), look up the customer email
     * against recent newsletter recipients who clicked a tracked link.
     * Credit the most recent matching campaign (last-touch attribution)
     * and tag the order with attribution_method='email_match' so reports
     * can split strict vs loose conversions.
     *
     * Defaults to a 14-day lookback. Skips orders that already have an
     * attribution_method set (won't overwrite the URL-param path).
     */
    protected function tryEmailMatchAttribution(Order $order): void
    {
        if ($order->newsletter_attribution_id) return;
        if ($order->attribution_method) return;

        $email = strtolower(trim((string) ($order->customer_email ?? '')));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) return;
        if (!$order->marketplace_client_id) return;

        $lookbackDays = (int) config('newsletter.email_match_lookback_days', 14);
        if ($lookbackDays <= 0) return;
        $cutoff = now()->subDays($lookbackDays);

        // Pick the most recent click from a recipient whose email matches
        // this order's customer_email, scoped to the same marketplace so
        // a click on Ambilet doesn't credit a Tics newsletter. Returns
        // (newsletter_id, last_click) for the winning row.
        $match = DB::table('marketplace_newsletter_link_events as e')
            ->join('marketplace_newsletter_recipients as r', 'r.id', '=', 'e.recipient_id')
            ->join('marketplace_newsletters as n', 'n.id', '=', 'e.newsletter_id')
            ->where('n.marketplace_client_id', $order->marketplace_client_id)
            ->where('e.event_type', \App\Models\MarketplaceNewsletterLinkEvent::TYPE_CLICK)
            ->where('e.created_at', '>=', $cutoff)
            ->whereRaw('LOWER(TRIM(r.email)) = ?', [$email])
            ->where('e.created_at', '<=', $order->created_at ?? now())
            ->orderByDesc('e.created_at')
            ->select('e.newsletter_id', 'e.created_at as click_at')
            ->first();

        if (!$match) return;

        // Persist quietly so we don't re-trigger the observer's updated()
        // logic and double-credit ourselves.
        $order->newsletter_attribution_id = (int) $match->newsletter_id;
        $order->attribution_method = 'email_match';
        $order->saveQuietly();

        Log::info('Newsletter email-match attribution applied', [
            'order_id' => $order->id,
            'newsletter_id' => $order->newsletter_attribution_id,
            'customer_email' => $email,
            'click_at' => $match->click_at,
        ]);
    }

    /**
     * Credit a newsletter campaign for a paid order. Inserts a
     * TYPE_PURCHASE row into marketplace_newsletter_link_events and
     * increments purchase_count + purchase_amount_cents on the
     * MarketplaceNewsletter so the EditNewsletter stats panel reflects
     * the conversion + revenue.
     *
     * Idempotent: if a row for this (newsletter, order) already exists
     * (e.g. status transition fires the observer twice on
     * paid → completed), we exit early so revenue isn't double-counted.
     */
    protected function creditNewsletterAttribution(Order $order): void
    {
        $newsletterId = (int) $order->newsletter_attribution_id;
        if ($newsletterId <= 0) return;

        $newsletter = \App\Models\MarketplaceNewsletter::find($newsletterId);
        if (!$newsletter) return;

        $existing = \App\Models\MarketplaceNewsletterLinkEvent::where('newsletter_id', $newsletterId)
            ->where('event_type', \App\Models\MarketplaceNewsletterLinkEvent::TYPE_PURCHASE)
            ->where('dest_url', (string) $order->id)
            ->first();
        if ($existing) return;

        \App\Models\MarketplaceNewsletterLinkEvent::create([
            'newsletter_id' => $newsletterId,
            'event_type' => \App\Models\MarketplaceNewsletterLinkEvent::TYPE_PURCHASE,
            'dest_url' => (string) $order->id, // reuse field as the order key
            'recipient_id' => null,
        ]);

        // total_cents is a real DB column but isn't always populated on
        // marketplace orders — CheckoutController writes only `total`
        // (the RON float), leaving total_cents=0. ?? returns the left
        // operand on 0 too (it only short-circuits on null), so the
        // previous form floored revenue at 0 even when total was 83.74.
        // Pick the first NON-ZERO source instead.
        $amountCents = (int) ($order->total_cents ?: round(((float) ($order->total ?? 0)) * 100));
        if ($amountCents < 0) $amountCents = 0;

        $newsletter->increment('purchase_count');
        if ($amountCents > 0) {
            $newsletter->increment('purchase_amount_cents', $amountCents);
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

        } catch (\Throwable $e) {
            // \Throwable so PHP \Error subclasses can't escape this observer
            // and break the caller (PaymentController).
            Log::error('Failed to track organizer analytics', [
                'order_id' => $order->id,
                'event_id' => $order->marketplace_event_id,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
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
        DB::afterCommit(fn () => $this->bustOrganizerBreakdownCache($order));
    }

    /**
     * Invalidate the OrganizerResource per-organizer breakdown cache so the
     * stats tab reflects the order change on next render. Falls back to a
     * silent no-op if the order has no marketplace_organizer_id (legacy /
     * orphan orders shouldn't crash the observer).
     */
    protected function bustOrganizerBreakdownCache(Order $order): void
    {
        $organizerId = $order->marketplace_organizer_id;
        if (!$organizerId) {
            return;
        }
        try {
            \Illuminate\Support\Facades\Cache::forget("organizer:{$organizerId}:breakdowns:v1");
        } catch (\Throwable $e) {
            // Never let cache hiccups block order persistence.
        }
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
