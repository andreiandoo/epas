<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\MarketplaceCustomer;
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

        // Get all paid/completed orders for this customer by email
        $email = $customer->email;
        if (!$email) {
            return 0;
        }

        // Find orders by customer email
        $orders = Order::where('customer_email', $email)
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
        // total_visits = max of sessions, orders, or existing value (each order implies at least 1 visit)
        $totalVisits = max($totalSessions, $totalOrders, $customer->total_visits ?? 0, 1);
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

        // Enrich profile from source models if missing
        $profileUpdates = $this->enrichProfileFromSources($customer, $email);
        $updates = array_merge($updates, $profileUpdates);

        // Cross-tenant and marketplace client tracking from orders
        $crossTenantUpdates = $this->buildCrossTenantData($customer, $orders);
        $updates = array_merge($updates, $crossTenantUpdates);

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

    protected function enrichProfileFromSources(CoreCustomer $customer, string $email): array
    {
        $updates = [];

        // Only enrich if profile data is missing
        $needsName = empty($customer->first_name);
        $needsPhone = empty($customer->phone);
        $needsLocation = empty($customer->city) && empty($customer->country_code);
        $needsGender = empty($customer->gender);

        if (!$needsName && !$needsPhone && !$needsLocation && !$needsGender) {
            return $updates;
        }

        // Try MarketplaceCustomer first (more likely to have full profile)
        $source = MarketplaceCustomer::where('email', $email)->first();
        if (!$source) {
            $source = Customer::where('email', $email)->first();
        }

        if (!$source) {
            return $updates;
        }

        if ($needsName) {
            $firstName = $source->first_name ?? null;
            $lastName = $source->last_name ?? null;
            if ($firstName && $firstName !== '-') $updates['first_name'] = $firstName;
            if ($lastName && $lastName !== '-') $updates['last_name'] = $lastName;
        }

        if ($needsPhone && !empty($source->phone)) {
            $updates['phone'] = $source->phone;
        }

        if ($needsLocation) {
            if (!empty($source->city)) $updates['city'] = $source->city;
            if (!empty($source->country)) {
                $updates['country_code'] = strtoupper(substr($source->country, 0, 2));
            }
            if ($source instanceof MarketplaceCustomer) {
                if (!empty($source->state)) $updates['region'] = $source->state;
                if (!empty($source->postal_code)) $updates['postal_code'] = $source->postal_code;
            }
        }

        if ($needsGender && $source instanceof MarketplaceCustomer) {
            if (!empty($source->gender)) $updates['gender'] = $source->gender;
            if (!empty($source->birth_date)) $updates['birth_date'] = $source->birth_date;
            if (!empty($source->locale)) $updates['language'] = $source->locale;
        }

        return $updates;
    }

    protected function buildCrossTenantData(CoreCustomer $customer, $orders): array
    {
        $updates = [];
        $tenantIds = $customer->tenant_ids ?? [];
        $marketplaceClientIds = $customer->marketplace_client_ids ?? [];

        foreach ($orders as $order) {
            if ($order->tenant_id && !in_array($order->tenant_id, $tenantIds)) {
                $tenantIds[] = $order->tenant_id;
            }
            if ($order->marketplace_client_id && !in_array($order->marketplace_client_id, $marketplaceClientIds)) {
                $marketplaceClientIds[] = $order->marketplace_client_id;
            }
        }

        $tenantIds = array_values(array_unique($tenantIds));
        $marketplaceClientIds = array_values(array_unique($marketplaceClientIds));

        if (!empty($tenantIds)) {
            $updates['tenant_ids'] = $tenantIds;
            $updates['tenant_count'] = count($tenantIds);
            if (!$customer->first_tenant_id) {
                $updates['first_tenant_id'] = $tenantIds[0];
            }
        }

        if (!empty($marketplaceClientIds)) {
            $updates['marketplace_client_ids'] = $marketplaceClientIds;
            $updates['marketplace_client_count'] = count($marketplaceClientIds);
        }

        // Set primary marketplace client from most frequent on orders
        if (!$customer->primary_marketplace_client_id && $orders->isNotEmpty()) {
            $mpClientCounts = $orders->whereNotNull('marketplace_client_id')
                ->groupBy('marketplace_client_id')
                ->map->count()
                ->sortDesc();

            if ($mpClientCounts->isNotEmpty()) {
                $updates['primary_marketplace_client_id'] = $mpClientCounts->keys()->first();
            }
        }

        // Set primary tenant from most frequent on orders
        if (!$customer->primary_tenant_id && $orders->isNotEmpty()) {
            $tenantCounts = $orders->whereNotNull('tenant_id')
                ->groupBy('tenant_id')
                ->map->count()
                ->sortDesc();

            if ($tenantCounts->isNotEmpty()) {
                $updates['primary_tenant_id'] = $tenantCounts->keys()->first();
            }
        }

        return $updates;
    }
}
