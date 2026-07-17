<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\MarketplaceClient;
use App\Models\Order;
use App\Services\Marketplace\SalesBreakdownService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Pre-computes the authoritative all-time marketplace revenue + commission
 * (the same SalesBreakdownService figures behind each event's Vânzări tab and
 * the payouts) and caches them, so the dashboard's all-time cards can read a
 * correct value without building every event on a web request.
 *
 * All-time = modern (non-legacy) sales valued via the service, PLUS the
 * migrated legacy_import history (which the service can't revalue, so we trust
 * the migrated commission_amount / total in the DB). Run on a schedule; the
 * dashboard falls back to the old approximate method until the cache is warm.
 */
class WarmDashboardAlltimeStats extends Command
{
    protected $signature = 'dashboard:warm-alltime-stats {--marketplace= : Only this marketplace_client id}';

    protected $description = 'Precompute authoritative all-time revenue + commission per marketplace into cache';

    public function handle(): int
    {
        $svc = app(SalesBreakdownService::class);

        $marketplaceIds = $this->option('marketplace')
            ? [(int) $this->option('marketplace')]
            : MarketplaceClient::query()->pluck('id')->all();

        foreach ($marketplaceIds as $mid) {
            $started = microtime(true);

            // Modern (non-legacy) events that ever had a paid sale, discovered
            // the same way as the daily report (event-linked orders).
            $eventSub = function ($sub) use ($mid) {
                $sub->select('id')->from('events')->where('marketplace_client_id', $mid);
            };
            $eventIds = Order::where(function ($q) use ($mid, $eventSub) {
                    $q->where('orders.marketplace_client_id', $mid)
                        ->orWhereIn('orders.marketplace_event_id', $eventSub)
                        ->orWhereIn('orders.event_id', $eventSub);
                })
                ->whereIn('orders.status', ['paid', 'confirmed', 'completed'])
                ->whereNotIn('orders.source', ['test_order', 'external_import', 'legacy_import'])
                ->where(function ($q) {
                    $q->whereNotNull('marketplace_event_id')->orWhereNotNull('event_id');
                })
                ->selectRaw('COALESCE(marketplace_event_id, event_id) as eid')
                ->groupBy('eid')
                ->pluck('eid');

            $commission = 0.0;
            $revenue = 0.0;

            foreach ($eventIds as $eid) {
                $event = Event::find((int) $eid);
                if (!$event) {
                    continue;
                }
                $bd = $svc->build($event);
                $kept = (float) ($bd['total_commission_kept_from_refunds'] ?? 0);
                $commission += (float) $bd['total_commission'] + $kept;
                $revenue += (float) $bd['total_revenue'] + $kept;
            }

            // Legacy migrated history — the service can't revalue it, so trust
            // the migrated figures. Keeps the all-time cards lifetime-complete.
            $legacy = Order::where('marketplace_client_id', $mid)
                ->whereIn('status', ['paid', 'confirmed', 'completed'])
                ->where('source', 'legacy_import')
                ->selectRaw('COALESCE(SUM(total), 0) as rev')
                ->selectRaw('COALESCE(SUM(COALESCE(commission_amount, 0)), 0) as comm')
                ->first();

            $commission += (float) ($legacy->comm ?? 0);
            $revenue += (float) ($legacy->rev ?? 0);

            Cache::put("mp_dash_alltime_auth_{$mid}", [
                'commission' => round($commission, 2),
                'revenue' => round($revenue, 2),
                'computed_at' => now()->toIso8601String(),
            ], now()->addHours(25));

            $secs = round(microtime(true) - $started, 1);
            $this->info("mp {$mid}: events=" . $eventIds->count() . " commission=" . round($commission, 2) . " revenue=" . round($revenue, 2) . " ({$secs}s)");
        }

        return self::SUCCESS;
    }
}
