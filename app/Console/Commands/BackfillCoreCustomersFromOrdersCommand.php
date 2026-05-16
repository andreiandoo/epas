<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\Platform\CoreCustomer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Historical fix: marketplace buyers never had a core_customers row
 * because the tracking pipeline only created CoreCustomer entries by
 * visitor_id, and the order's email was never propagated through
 * tracking events. As a result, ROAS Signal 3
 * (orders.customer_email ↔ core_customers.last_fbclid) returned zero
 * matches even for organizers where every event in the funnel had
 * fbclid stored on it.
 *
 * This backfill:
 *   1. Iterates paid orders in a lookback window
 *   2. For each order's customer_email, finds or creates a CoreCustomer
 *   3. If we can identify an fbclid-bearing tracking event from the
 *      same IP within 60 days of the purchase, sets last_fbclid on the
 *      customer (best-effort IP-based attribution).
 *
 *   php artisan capi:backfill-customers --days=365
 *   php artisan capi:backfill-customers --days=90 --organizer=340
 */
class BackfillCoreCustomersFromOrdersCommand extends Command
{
    protected $signature = 'capi:backfill-customers
        {--days=365 : Lookback window for paid orders}
        {--organizer= : Limit to one marketplace_organizer_id}
        {--dry : Report counts without writing}';

    protected $description = 'Create / enrich core_customers rows from order data, recovering fbclid attribution via IP match';

    public function handle(): int
    {
        $start = now()->subDays((int) $this->option('days'));

        $query = Order::query()
            ->whereIn('status', ['paid', 'confirmed', 'completed'])
            ->whereNotNull('customer_email')
            ->where('created_at', '>=', $start);

        if ($org = $this->option('organizer')) {
            $query->where('marketplace_organizer_id', (int) $org);
        }

        $total = (clone $query)->count();
        $this->info("Found {$total} paid orders to process.");

        if ($total === 0 || $this->option('dry')) {
            return self::SUCCESS;
        }

        $created = 0;
        $enriched = 0;
        $linkedFbclid = 0;
        $skipped = 0;

        $query->select(['id', 'customer_email', 'customer_name', 'customer_phone', 'meta', 'paid_at', 'created_at'])
            ->orderBy('id')
            ->chunk(500, function ($orders) use (&$created, &$enriched, &$linkedFbclid, &$skipped) {
                foreach ($orders as $order) {
                    $email = mb_strtolower(trim((string) $order->customer_email));
                    if ($email === '') { $skipped++; continue; }

                    $meta = $order->meta ?? [];
                    $ip = is_array($meta) ? ($meta['ip_address'] ?? null) : null;
                    $purchasedAt = $order->paid_at ?? $order->created_at;

                    $customer = CoreCustomer::where('email', $email)->first();

                    if (!$customer) {
                        $first = null; $last = null;
                        if (!empty($order->customer_name)) {
                            $parts = preg_split('/\s+/', trim((string) $order->customer_name), 2);
                            $first = $parts[0] ?? null;
                            $last = $parts[1] ?? null;
                        }
                        $customer = CoreCustomer::create([
                            'email' => $email,
                            'first_name' => $first ? mb_substr($first, 0, 100) : null,
                            'last_name' => $last ? mb_substr($last, 0, 100) : null,
                            'phone' => $order->customer_phone ? mb_substr((string) $order->customer_phone, 0, 50) : null,
                            'ip_address' => $ip,
                            'first_seen_at' => $purchasedAt,
                            'last_seen_at' => $purchasedAt,
                            'first_purchase_at' => $purchasedAt,
                            'last_purchase_at' => $purchasedAt,
                        ]);
                        $created++;
                    } else {
                        $patch = [];
                        if (!$customer->phone && $order->customer_phone) {
                            $patch['phone'] = mb_substr((string) $order->customer_phone, 0, 50);
                        }
                        if (!$customer->ip_address && $ip) {
                            $patch['ip_address'] = $ip;
                        }
                        if (!empty($patch)) {
                            $customer->update($patch);
                            $enriched++;
                        }
                    }

                    if (!$customer->last_fbclid && $ip && $purchasedAt) {
                        $event = DB::table('core_customer_events')
                            ->where('ip_address', $ip)
                            ->whereNotNull('fbclid')
                            ->where('occurred_at', '<=', $purchasedAt)
                            ->where('occurred_at', '>=', $purchasedAt->copy()->subDays(60))
                            ->orderByDesc('occurred_at')
                            ->first(['fbclid', 'visitor_id', 'occurred_at']);

                        if ($event) {
                            $patch = ['last_fbclid' => mb_substr((string) $event->fbclid, 0, 255)];
                            if (!$customer->visitor_id && $event->visitor_id) {
                                $patch['visitor_id'] = $event->visitor_id;
                            }
                            $customer->update($patch);
                            $linkedFbclid++;
                        }
                    }
                }
                $this->line(sprintf(
                    '  processed batch — created %d / enriched %d / linked-fbclid %d / skipped %d so far',
                    $created, $enriched, $linkedFbclid, $skipped
                ));
            });

        $this->info("Done.");
        $this->line("  created core_customer rows: {$created}");
        $this->line("  enriched existing rows:     {$enriched}");
        $this->line("  rows now carrying fbclid:   {$linkedFbclid}");
        $this->line("  skipped (no email):         {$skipped}");

        return self::SUCCESS;
    }
}
