<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\Platform\CoreCustomer;
use App\Models\Platform\CoreCustomerEvent;
use App\Models\Platform\CoreSession;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncCoreCustomerMetrics extends Command
{
    protected $signature = 'core-customers:sync-metrics {--customer= : Sync specific customer ID} {--dry-run : Show what would be updated without saving}';

    protected $description = 'Reconcile CoreCustomer metrics from orders and events data';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $customerId = $this->option('customer');

        $query = CoreCustomer::query();
        if ($customerId) {
            $query->where('id', $customerId);
        }

        $total = $query->count();
        $this->info("Syncing metrics for {$total} customers" . ($dryRun ? ' (DRY RUN)' : ''));

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $updated = 0;

        $query->chunkById(100, function ($customers) use ($dryRun, &$updated, $bar) {
            foreach ($customers as $customer) {
                $changes = $this->syncCustomer($customer, $dryRun);
                if ($changes > 0) {
                    $updated++;
                }
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info("Done. Updated {$updated} of {$total} customers.");

        return self::SUCCESS;
    }

    protected function syncCustomer(CoreCustomer $customer, bool $dryRun): int
    {
        $changes = 0;
        $updates = [];

        // Get all paid/completed orders for this customer (by email hash)
        $emailHash = $customer->email_hash;
        if (!$emailHash) {
            return 0;
        }

        // Find orders by customer email hash
        $orders = Order::where(function ($q) use ($emailHash, $customer) {
                $q->where('customer_email_hash', $emailHash);

                // Also try matching by decrypted email if hash column doesn't exist
                if ($customer->email) {
                    $q->orWhere('customer_email', $customer->email);
                }
            })
            ->whereIn('status', ['paid', 'confirmed', 'completed'])
            ->get();

        if ($orders->isEmpty()) {
            // Try matching by CoreCustomerEvent purchases
            $purchaseEvents = CoreCustomerEvent::where('customer_id', $customer->id)
                ->where('event_type', 'purchase')
                ->get();

            if ($purchaseEvents->isNotEmpty()) {
                $orderIds = $purchaseEvents->pluck('order_id')->filter()->unique();
                $orders = Order::whereIn('id', $orderIds)
                    ->whereIn('status', ['paid', 'confirmed', 'completed'])
                    ->get();
            }
        }

        // Calculate purchase metrics from orders
        $totalOrders = $orders->count();
        $totalSpent = 0;
        $totalTickets = 0;
        $firstPurchaseAt = null;
        $lastPurchaseAt = null;

        foreach ($orders as $order) {
            // Use the correct amount field
            if ($order->marketplace_client_id) {
                $amount = (float) $order->total;
            } else {
                $amount = ($order->total_cents ?? 0) / 100;
            }
            $totalSpent += $amount;

            // Count tickets
            $ticketCount = $order->tickets()->count();
            $totalTickets += $ticketCount > 0 ? $ticketCount : ($order->meta['ticket_count'] ?? 1);

            // Track dates
            $orderDate = $order->created_at;
            if (!$firstPurchaseAt || $orderDate->lt($firstPurchaseAt)) {
                $firstPurchaseAt = $orderDate;
            }
            if (!$lastPurchaseAt || $orderDate->gt($lastPurchaseAt)) {
                $lastPurchaseAt = $orderDate;
            }
        }

        $avgOrderValue = $totalOrders > 0 ? round($totalSpent / $totalOrders, 2) : 0;
        $currency = $orders->first()?->currency ?? $customer->currency ?? 'EUR';

        // Calculate engagement metrics from events
        $eventStats = CoreCustomerEvent::where('customer_id', $customer->id)
            ->selectRaw("
                COUNT(*) as total_events,
                SUM(CASE WHEN event_type = 'page_view' THEN 1 ELSE 0 END) as total_pageviews,
                SUM(CASE WHEN event_type = 'view_item' THEN 1 ELSE 0 END) as total_items_viewed,
                MIN(created_at) as first_event_at,
                MAX(created_at) as last_event_at
            ")
            ->first();

        // Session metrics
        $sessionStats = CoreSession::where('customer_id', $customer->id)
            ->selectRaw("
                COUNT(*) as total_sessions,
                SUM(pageviews) as total_pageviews_sessions,
                SUM(duration_seconds) as total_duration,
                AVG(duration_seconds) as avg_duration,
                SUM(CASE WHEN is_bounce = 1 THEN 1 ELSE 0 END) as bounces
            ")
            ->first();

        // Also count sessions by visitor_id if customer_id not linked
        if (($sessionStats->total_sessions ?? 0) == 0 && $customer->visitor_id) {
            $sessionStats = CoreSession::where('visitor_id', $customer->visitor_id)
                ->selectRaw("
                    COUNT(*) as total_sessions,
                    SUM(pageviews) as total_pageviews_sessions,
                    SUM(duration_seconds) as total_duration,
                    AVG(duration_seconds) as avg_duration,
                    SUM(CASE WHEN is_bounce = 1 THEN 1 ELSE 0 END) as bounces
                ")
                ->first();
        }

        $totalPageviews = max(
            (int) ($eventStats->total_pageviews ?? 0),
            (int) ($sessionStats->total_pageviews_sessions ?? 0)
        );

        $totalSessions = (int) ($sessionStats->total_sessions ?? 0);
        $totalVisits = max($totalSessions, 1); // At least 1 if they have data
        $totalTimeSpent = (int) ($sessionStats->total_duration ?? 0);
        $avgSessionDuration = (int) ($sessionStats->avg_duration ?? 0);
        $bounceRate = $totalSessions > 0
            ? round(($sessionStats->bounces ?? 0) / $totalSessions * 100, 2)
            : 0;

        // Determine first/last seen
        $firstSeenAt = $customer->first_seen_at;
        $lastSeenAt = $customer->last_seen_at;

        if ($eventStats->first_event_at && (!$firstSeenAt || $eventStats->first_event_at < $firstSeenAt)) {
            $firstSeenAt = $eventStats->first_event_at;
        }
        if ($firstPurchaseAt && (!$firstSeenAt || $firstPurchaseAt < $firstSeenAt)) {
            $firstSeenAt = $firstPurchaseAt;
        }
        if ($eventStats->last_event_at && (!$lastSeenAt || $eventStats->last_event_at > $lastSeenAt)) {
            $lastSeenAt = $eventStats->last_event_at;
        }
        if ($lastPurchaseAt && (!$lastSeenAt || $lastPurchaseAt > $lastSeenAt)) {
            $lastSeenAt = $lastPurchaseAt;
        }

        // Build update array
        $updates = [
            'total_orders' => $totalOrders,
            'total_tickets' => $totalTickets,
            'total_spent' => round($totalSpent, 2),
            'average_order_value' => $avgOrderValue,
            'lifetime_value' => round($totalSpent, 2),
            'currency' => $currency,
            'first_purchase_at' => $firstPurchaseAt,
            'last_purchase_at' => $lastPurchaseAt,
            'total_visits' => $totalVisits,
            'total_pageviews' => $totalPageviews,
            'total_sessions' => $totalSessions,
            'total_time_spent_seconds' => $totalTimeSpent,
            'avg_session_duration_seconds' => $avgSessionDuration,
            'bounce_rate' => $bounceRate,
            'total_events_viewed' => (int) ($eventStats->total_items_viewed ?? 0),
        ];

        if ($firstSeenAt) $updates['first_seen_at'] = $firstSeenAt;
        if ($lastSeenAt) $updates['last_seen_at'] = $lastSeenAt;

        // Purchase frequency
        if ($totalOrders >= 2 && $firstPurchaseAt && $lastPurchaseAt) {
            $daysBetween = $firstPurchaseAt->diffInDays($lastPurchaseAt);
            $updates['purchase_frequency_days'] = $daysBetween > 0
                ? (int) round($daysBetween / ($totalOrders - 1))
                : 0;
        }

        if ($lastPurchaseAt) {
            $updates['days_since_last_purchase'] = (int) $lastPurchaseAt->diffInDays(now());
        }

        // Check what actually changed
        foreach ($updates as $key => $value) {
            $current = $customer->getAttributeValue($key);
            if ($current == $value) {
                unset($updates[$key]);
            }
        }

        if (empty($updates)) {
            return 0;
        }

        if ($dryRun) {
            $this->line("  Customer #{$customer->id}: " . json_encode($updates));
            return count($updates);
        }

        // Save updates
        $customer->update($updates);

        // Recalculate segment and RFM
        try {
            $customer->updateSegment();
            $customer->calculateRfmScores();
        } catch (\Exception $e) {
            // Segment/RFM calculation might fail if methods aren't fully implemented
        }

        return count($updates);
    }
}
