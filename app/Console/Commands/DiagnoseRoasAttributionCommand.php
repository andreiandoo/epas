<?php

namespace App\Console\Commands;

use App\Models\MarketplaceOrganizer;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Show how many paid orders in a window can be attributed to Facebook
 * via each available signal: orders.meta JSON, core_customer_events,
 * core_customers.last_fbclid. Helps explain why ROAS shows 0.
 *
 *   php artisan capi:diagnose-attribution --organizer=341 --days=30
 */
class DiagnoseRoasAttributionCommand extends Command
{
    protected $signature = 'capi:diagnose-attribution {--organizer= : marketplace_organizer_id} {--marketplace= : marketplace_client_id} {--days=30}';
    protected $description = 'Audit which Facebook attribution signal carries the most paid orders in a window';

    public function handle(): int
    {
        $start = now()->subDays((int) $this->option('days'));
        $end = now();

        $base = DB::table('orders')
            ->whereBetween('created_at', [$start, $end])
            ->whereIn('status', ['paid', 'confirmed', 'completed']);

        if ($org = $this->option('organizer')) {
            $base->where('marketplace_organizer_id', (int) $org);
            $name = MarketplaceOrganizer::find((int) $org)?->name ?? "Org#{$org}";
            $this->info("Organizer: {$name} (id={$org})");
        }
        if ($mp = $this->option('marketplace')) {
            $base->where('marketplace_client_id', (int) $mp);
        }
        $this->info("Window: {$start->toDateString()} → {$end->toDateString()}");
        $this->line('');

        $total = (clone $base)->count();
        $totalRevenue = (float) (clone $base)->sum('total');
        $this->line("Total paid orders: <fg=cyan>{$total}</> · Revenue: <fg=cyan>" . number_format($totalRevenue, 2) . " RON</>");
        $this->line('');

        // Signal 1 — orders.meta contains fbclid or fbc
        $metaCount = (clone $base)
            ->whereRaw("(meta::text LIKE '%fbclid%' OR meta::text LIKE '%fbc%')")
            ->count();
        $metaRevenue = (float) (clone $base)
            ->whereRaw("(meta::text LIKE '%fbclid%' OR meta::text LIKE '%fbc%')")
            ->sum('total');
        $this->line("Signal 1 — orders.meta JSON contains fbclid/fbc:");
        $this->line("  count: " . $this->pct($metaCount, $total) . " · revenue: " . number_format($metaRevenue, 2));

        // Signal 2 — core_customer_events has fbclid for this order_id
        $eventsCount = (clone $base)
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                  ->from('core_customer_events')
                  ->whereColumn('core_customer_events.order_id', 'orders.id')
                  ->whereNotNull('fbclid');
            })
            ->count();
        $eventsRevenue = (float) (clone $base)
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                  ->from('core_customer_events')
                  ->whereColumn('core_customer_events.order_id', 'orders.id')
                  ->whereNotNull('fbclid');
            })
            ->sum('total');
        $this->line("Signal 2 — core_customer_events.fbclid set for the order:");
        $this->line("  count: " . $this->pct($eventsCount, $total) . " · revenue: " . number_format($eventsRevenue, 2));

        // Signal 3 — core_customers.last_fbclid set (joined by email, case-insensitive)
        $customerCount = (clone $base)
            ->whereNotNull('customer_email')
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                  ->from('core_customers')
                  ->whereRaw('LOWER(core_customers.email) = LOWER(orders.customer_email)')
                  ->whereNotNull('last_fbclid');
            })
            ->count();
        $customerRevenue = (float) (clone $base)
            ->whereNotNull('customer_email')
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                  ->from('core_customers')
                  ->whereRaw('LOWER(core_customers.email) = LOWER(orders.customer_email)')
                  ->whereNotNull('last_fbclid');
            })
            ->sum('total');
        $this->line("Signal 3 — core_customers.last_fbclid (joined by email):");
        $this->line("  count: " . $this->pct($customerCount, $total) . " · revenue: " . number_format($customerRevenue, 2));

        // Combined (union)
        $combined = (clone $base)
            ->where(function ($q) {
                $q->whereRaw("(meta::text LIKE '%fbclid%' OR meta::text LIKE '%fbc%')")
                  ->orWhereExists(function ($s) {
                      $s->select(DB::raw(1))
                        ->from('core_customer_events')
                        ->whereColumn('core_customer_events.order_id', 'orders.id')
                        ->whereNotNull('fbclid');
                  })
                  ->orWhereExists(function ($s) {
                      $s->select(DB::raw(1))
                        ->from('core_customers')
                        ->whereRaw('LOWER(core_customers.email) = LOWER(orders.customer_email)')
                        ->whereNotNull('last_fbclid');
                  });
            });
        $combinedCount = $combined->count();
        $combinedRevenue = (float) $combined->sum('total');
        $this->line('');
        $this->line("<fg=green>Combined (any signal): " . $this->pct($combinedCount, $total) . " · revenue: " . number_format($combinedRevenue, 2) . "</>");
        $this->line('');

        // If the window has zero orders at all, dump a 12-month
        // distribution so it's obvious whether the organizer ever sold
        // anything (wrong filter) vs hasn't sold lately (just empty).
        if ($total === 0 && ($this->option('organizer') || $this->option('marketplace'))) {
            $this->warn('Window contains zero paid orders. Wider 12-month distribution:');
            $allBase = DB::table('orders')
                ->whereIn('status', ['paid', 'confirmed', 'completed'])
                ->where('created_at', '>=', now()->subMonths(12));
            if ($org = $this->option('organizer')) {
                $allBase->where('marketplace_organizer_id', (int) $org);
            }
            if ($mp = $this->option('marketplace')) {
                $allBase->where('marketplace_client_id', (int) $mp);
            }
            $rows = $allBase
                ->selectRaw("to_char(created_at, 'YYYY-MM') as month, COUNT(*) as c, SUM(total) as rev")
                ->groupBy('month')
                ->orderBy('month')
                ->get();
            if ($rows->isEmpty()) {
                $this->line('  No paid orders in the last 12 months either.');
            } else {
                foreach ($rows as $r) {
                    $this->line(sprintf('  %s: %d orders · %s RON', $r->month, $r->c, number_format((float) $r->rev, 2)));
                }
            }
            $this->line('');
        }

        // Quick sample to help see WHY signal 1 is empty if it is
        if ($metaCount === 0 && $total > 0) {
            $sample = (clone $base)->select('id', 'order_number', 'customer_email', 'meta', 'created_at')
                ->orderByDesc('id')->take(2)->get();
            $this->warn('Signal 1 returned zero. Sample order.meta values:');
            foreach ($sample as $row) {
                $meta = $row->meta ? mb_substr((string) $row->meta, 0, 200) : '(null)';
                $this->line("  #{$row->id} ({$row->order_number}) {$row->customer_email}: meta = {$meta}");
            }
        }

        return self::SUCCESS;
    }

    protected function pct(int $count, int $total): string
    {
        if ($total === 0) return "0";
        $p = round($count / $total * 100, 1);
        return "{$count}/{$total} ({$p}%)";
    }
}
