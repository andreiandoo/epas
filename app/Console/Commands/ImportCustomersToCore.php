<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\MarketplaceCustomer;
use App\Models\Order;
use App\Models\Platform\CoreCustomer;
use App\Models\Platform\CoreCustomerEvent;
use App\Models\Platform\CoreSession;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ImportCustomersToCore extends Command
{
    protected $signature = 'core-customers:import
        {--source=all : Source to import from (marketplace|tenant|all)}
        {--marketplace-client= : Specific marketplace client ID}
        {--dry-run : Show what would be imported without saving}
        {--skip-metrics : Skip metrics calculation (faster import)}';

    protected $description = 'Import customers from MarketplaceCustomer and Customer models into CoreCustomer';

    private int $created = 0;
    private int $enriched = 0;
    private int $skipped = 0;

    public function handle(): int
    {
        $source = $this->option('source');
        $dryRun = $this->option('dry-run');
        $skipMetrics = $this->option('skip-metrics');

        $this->info('CoreCustomer Import' . ($dryRun ? ' (DRY RUN)' : ''));
        $this->newLine();

        if (in_array($source, ['all', 'marketplace'])) {
            $this->importMarketplaceCustomers($dryRun, $skipMetrics);
        }

        if (in_array($source, ['all', 'tenant'])) {
            $this->importTenantCustomers($dryRun, $skipMetrics);
        }

        $this->newLine();
        $this->info("Results: Created {$this->created}, Enriched {$this->enriched}, Skipped {$this->skipped}");

        return self::SUCCESS;
    }

    protected function importMarketplaceCustomers(bool $dryRun, bool $skipMetrics): void
    {
        $query = MarketplaceCustomer::query()
            ->whereNotNull('email')
            ->where('email', '!=', '');

        $clientId = $this->option('marketplace-client');
        if ($clientId) {
            $query->where('marketplace_client_id', $clientId);
        }

        $total = $query->count();
        $this->info("Importing {$total} marketplace customers...");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->chunkById(200, function ($customers) use ($dryRun, $skipMetrics, $bar) {
            foreach ($customers as $mpCustomer) {
                try {
                    $this->importSingleMarketplaceCustomer($mpCustomer, $dryRun, $skipMetrics);
                } catch (\Exception $e) {
                    // Log but don't stop
                    Log::warning('Failed to import marketplace customer', [
                        'id' => $mpCustomer->id,
                        'email' => $mpCustomer->email,
                        'error' => $e->getMessage(),
                    ]);
                }
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
    }

    protected function importSingleMarketplaceCustomer(MarketplaceCustomer $mpCustomer, bool $dryRun, bool $skipMetrics): void
    {
        $email = strtolower(trim($mpCustomer->email));
        if (empty($email) || !str_contains($email, '@')) {
            $this->skipped++;
            return;
        }

        $coreCustomer = CoreCustomer::findByEmail($email);
        $isNew = false;

        if (!$coreCustomer) {
            if ($dryRun) {
                $this->created++;
                return;
            }

            $coreCustomer = CoreCustomer::create([
                'email' => $email,
                'first_name' => ($mpCustomer->first_name && $mpCustomer->first_name !== '-') ? $mpCustomer->first_name : null,
                'last_name' => ($mpCustomer->last_name && $mpCustomer->last_name !== '-') ? $mpCustomer->last_name : null,
                'phone' => $mpCustomer->phone,
                'gender' => $mpCustomer->gender,
                'birth_date' => $mpCustomer->birth_date,
                'city' => $mpCustomer->city,
                'country_code' => $mpCustomer->country ? strtoupper(substr($mpCustomer->country, 0, 2)) : null,
                'region' => $mpCustomer->state,
                'postal_code' => $mpCustomer->postal_code,
                'language' => $mpCustomer->locale ?? 'ro',
                'currency' => 'RON',
                'first_seen_at' => $mpCustomer->created_at,
                'last_seen_at' => $mpCustomer->last_login_at ?? $mpCustomer->updated_at,
                'email_subscribed' => $mpCustomer->accepts_marketing ?? false,
                'marketing_consent' => $mpCustomer->accepts_marketing ?? false,
                'consent_updated_at' => $mpCustomer->marketing_consent_at,
            ]);

            $isNew = true;
            $this->created++;
        } else {
            if ($dryRun) {
                $this->enriched++;
                return;
            }

            // Enrich missing profile fields from marketplace customer
            $updates = [];
            if (!$coreCustomer->first_name && $mpCustomer->first_name && $mpCustomer->first_name !== '-') {
                $updates['first_name'] = $mpCustomer->first_name;
            }
            if (!$coreCustomer->last_name && $mpCustomer->last_name && $mpCustomer->last_name !== '-') {
                $updates['last_name'] = $mpCustomer->last_name;
            }
            if (!$coreCustomer->phone && $mpCustomer->phone) {
                $updates['phone'] = $mpCustomer->phone;
            }
            if (!$coreCustomer->gender && $mpCustomer->gender) {
                $updates['gender'] = $mpCustomer->gender;
            }
            if (!$coreCustomer->birth_date && $mpCustomer->birth_date) {
                $updates['birth_date'] = $mpCustomer->birth_date;
            }
            if (!$coreCustomer->city && $mpCustomer->city) {
                $updates['city'] = $mpCustomer->city;
            }
            if (!$coreCustomer->country_code && $mpCustomer->country) {
                $updates['country_code'] = strtoupper(substr($mpCustomer->country, 0, 2));
            }
            if (!$coreCustomer->language && $mpCustomer->locale) {
                $updates['language'] = $mpCustomer->locale;
            }

            if (!empty($updates)) {
                $coreCustomer->update($updates);
                $this->enriched++;
            } else {
                $this->skipped++;
            }
        }

        // Link marketplace client
        $coreCustomer->addMarketplaceClient($mpCustomer->marketplace_client_id);

        // Sync metrics from orders
        if (!$skipMetrics) {
            $this->syncMetricsForCustomer($coreCustomer, $email);
        }
    }

    protected function importTenantCustomers(bool $dryRun, bool $skipMetrics): void
    {
        $total = Customer::whereNotNull('email')->where('email', '!=', '')->count();
        $this->info("Importing {$total} tenant customers...");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        Customer::whereNotNull('email')
            ->where('email', '!=', '')
            ->chunkById(200, function ($customers) use ($dryRun, $skipMetrics, $bar) {
                foreach ($customers as $customer) {
                    try {
                        $this->importSingleTenantCustomer($customer, $dryRun, $skipMetrics);
                    } catch (\Exception $e) {
                        Log::warning('Failed to import tenant customer', [
                            'id' => $customer->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                    $bar->advance();
                }
            });

        $bar->finish();
        $this->newLine();
    }

    protected function importSingleTenantCustomer(Customer $customer, bool $dryRun, bool $skipMetrics): void
    {
        $email = strtolower(trim($customer->email));
        if (empty($email) || !str_contains($email, '@')) {
            $this->skipped++;
            return;
        }

        $coreCustomer = CoreCustomer::findByEmail($email);

        if (!$coreCustomer) {
            if ($dryRun) {
                $this->created++;
                return;
            }

            $coreCustomer = CoreCustomer::create([
                'email' => $email,
                'first_name' => $customer->first_name,
                'last_name' => $customer->last_name,
                'phone' => $customer->phone,
                'city' => $customer->city,
                'country_code' => $customer->country ? strtoupper(substr($customer->country, 0, 2)) : null,
                'currency' => 'EUR',
                'first_seen_at' => $customer->created_at,
                'last_seen_at' => $customer->updated_at,
            ]);

            $this->created++;
        } else {
            if ($dryRun) {
                $this->enriched++;
                return;
            }

            // Enrich missing fields
            $updates = [];
            if (!$coreCustomer->first_name && $customer->first_name) {
                $updates['first_name'] = $customer->first_name;
            }
            if (!$coreCustomer->last_name && $customer->last_name) {
                $updates['last_name'] = $customer->last_name;
            }
            if (!$coreCustomer->phone && $customer->phone) {
                $updates['phone'] = $customer->phone;
            }

            if (!empty($updates)) {
                $coreCustomer->update($updates);
                $this->enriched++;
            } else {
                $this->skipped++;
            }
        }

        // Link tenant
        if ($customer->tenant_id) {
            $coreCustomer->addTenant($customer->tenant_id);
        }

        // Also link all tenants from customer_tenant pivot
        if (method_exists($customer, 'tenants')) {
            foreach ($customer->tenants as $tenant) {
                $coreCustomer->addTenant($tenant->id);
            }
        }

        if ($customer->primary_tenant_id && !$coreCustomer->primary_tenant_id) {
            $coreCustomer->update(['primary_tenant_id' => $customer->primary_tenant_id]);
        }

        // Sync metrics
        if (!$skipMetrics) {
            $this->syncMetricsForCustomer($coreCustomer, $email);
        }
    }

    protected function syncMetricsForCustomer(CoreCustomer $customer, string $email): void
    {
        // Find orders by email
        $orders = Order::where('customer_email', $email)
            ->whereIn('status', ['paid', 'confirmed', 'completed'])
            ->get();

        if ($orders->isEmpty()) {
            // Fallback: find by CoreCustomerEvent purchases
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

        $totalOrders = $orders->count();
        $totalSpent = 0;
        $totalTickets = 0;
        $firstPurchaseAt = null;
        $lastPurchaseAt = null;
        $tenantIds = $customer->tenant_ids ?? [];
        $marketplaceClientIds = $customer->marketplace_client_ids ?? [];

        foreach ($orders as $order) {
            // Correct amount based on order type
            $amount = $order->marketplace_client_id
                ? (float) $order->total
                : ($order->total_cents ?? 0) / 100;
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

            // Collect tenant/marketplace IDs from orders
            if ($order->tenant_id && !in_array($order->tenant_id, $tenantIds)) {
                $tenantIds[] = $order->tenant_id;
            }
            if ($order->marketplace_client_id && !in_array($order->marketplace_client_id, $marketplaceClientIds)) {
                $marketplaceClientIds[] = $order->marketplace_client_id;
            }
        }

        // Session/event metrics
        $sessionStats = CoreSession::where('customer_id', $customer->id)
            ->selectRaw("
                COUNT(*) as total_sessions,
                SUM(pageviews) as total_pageviews,
                SUM(duration_seconds) as total_duration,
                AVG(duration_seconds) as avg_duration,
                SUM(CASE WHEN is_bounce = 1 THEN 1 ELSE 0 END) as bounces
            ")
            ->first();

        // Fallback: visitor_id
        if (($sessionStats->total_sessions ?? 0) == 0 && $customer->visitor_id) {
            $sessionStats = CoreSession::where('visitor_id', $customer->visitor_id)
                ->selectRaw("
                    COUNT(*) as total_sessions,
                    SUM(pageviews) as total_pageviews,
                    SUM(duration_seconds) as total_duration,
                    AVG(duration_seconds) as avg_duration,
                    SUM(CASE WHEN is_bounce = 1 THEN 1 ELSE 0 END) as bounces
                ")
                ->first();
        }

        $totalSessions = (int) ($sessionStats->total_sessions ?? 0);
        $totalPageviews = (int) ($sessionStats->total_pageviews ?? 0);

        // total_visits = max of sessions OR orders (each order implies at least 1 visit)
        $totalVisits = max($totalSessions, $totalOrders, $customer->total_visits ?? 0);
        if ($totalVisits === 0 && ($totalOrders > 0 || $customer->email)) {
            $totalVisits = 1;
        }

        $avgOrderValue = $totalOrders > 0 ? round($totalSpent / $totalOrders, 2) : 0;
        $currency = $orders->first()?->currency ?? $customer->currency ?? 'RON';

        // Determine primary tenant/marketplace from most orders
        if ($orders->isNotEmpty()) {
            $mpClientCounts = $orders->whereNotNull('marketplace_client_id')
                ->groupBy('marketplace_client_id')
                ->map->count()
                ->sortDesc();

            $tenantCounts = $orders->whereNotNull('tenant_id')
                ->groupBy('tenant_id')
                ->map->count()
                ->sortDesc();

            $primaryMpClientId = $mpClientCounts->keys()->first();
            $primaryTenantId = $tenantCounts->keys()->first();
        }

        // Build updates
        $updates = [
            'total_orders' => $totalOrders,
            'total_tickets' => $totalTickets,
            'total_spent' => round($totalSpent, 2),
            'average_order_value' => $avgOrderValue,
            'lifetime_value' => round($totalSpent, 2),
            'currency' => $currency,
            'total_visits' => $totalVisits,
            'total_pageviews' => $totalPageviews,
            'total_sessions' => $totalSessions,
            'total_time_spent_seconds' => (int) ($sessionStats->total_duration ?? 0),
            'avg_session_duration_seconds' => (int) ($sessionStats->avg_duration ?? 0),
            'bounce_rate' => $totalSessions > 0
                ? round(($sessionStats->bounces ?? 0) / $totalSessions * 100, 2)
                : 0,
        ];

        if ($firstPurchaseAt) $updates['first_purchase_at'] = $firstPurchaseAt;
        if ($lastPurchaseAt) {
            $updates['last_purchase_at'] = $lastPurchaseAt;
            $updates['days_since_last_purchase'] = (int) $lastPurchaseAt->diffInDays(now());
        }

        if ($totalOrders >= 2 && $firstPurchaseAt && $lastPurchaseAt) {
            $daysBetween = $firstPurchaseAt->diffInDays($lastPurchaseAt);
            $updates['purchase_frequency_days'] = $daysBetween > 0
                ? (int) round($daysBetween / ($totalOrders - 1))
                : 0;
        }

        // Cross-tenant/marketplace IDs
        if (!empty($tenantIds)) {
            $updates['tenant_ids'] = array_values(array_unique($tenantIds));
            $updates['tenant_count'] = count($updates['tenant_ids']);
        }
        if (!empty($marketplaceClientIds)) {
            $updates['marketplace_client_ids'] = array_values(array_unique($marketplaceClientIds));
            $updates['marketplace_client_count'] = count($updates['marketplace_client_ids']);
        }

        if (!empty($primaryMpClientId)) {
            $updates['primary_marketplace_client_id'] = $primaryMpClientId;
        }
        if (!empty($primaryTenantId) && !$customer->primary_tenant_id) {
            $updates['primary_tenant_id'] = $primaryTenantId;
        }
        if (!$customer->first_tenant_id && !empty($tenantIds)) {
            $updates['first_tenant_id'] = $tenantIds[0];
        }

        // Determine first/last seen from multiple sources
        $firstSeenAt = $customer->first_seen_at;
        $lastSeenAt = $customer->last_seen_at;

        if ($firstPurchaseAt && (!$firstSeenAt || $firstPurchaseAt < $firstSeenAt)) {
            $updates['first_seen_at'] = $firstPurchaseAt;
        }
        if ($lastPurchaseAt && (!$lastSeenAt || $lastPurchaseAt > $lastSeenAt)) {
            $updates['last_seen_at'] = $lastPurchaseAt;
        }

        $customer->update($updates);

        // Recalculate segment & RFM
        try {
            $customer->refresh();
            $customer->updateSegment();
            $customer->calculateRfmScores();
        } catch (\Exception $e) {
            // Non-critical
        }
    }
}
