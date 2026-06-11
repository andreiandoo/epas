<?php

namespace App\Console\Commands;

use App\Models\ServiceOrder;
use Illuminate\Console\Command;

/**
 * Daily sweep: find Ad Tracking service orders whose service_end_date has
 * passed and call complete() on them. ServiceOrder::complete() then runs
 * deactivateTracking() which disables the organizer's TrackingIntegration
 * rows for any platform no longer covered by another active order.
 *
 * Idempotent — already-completed orders are skipped by the status filter.
 * Pixel ID values are NOT wiped: settings.{id_field} stays so a renewal
 * just flips the toggle back on.
 */
class ExpireTrackingServices extends Command
{
    protected $signature = 'tracking:expire-services {--dry-run : Print what would change without writing}';

    protected $description = 'Mark Ad Tracking service orders as completed when their service_end_date has passed and tear down the organizer pixel toggles that are no longer covered.';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $today = now()->startOfDay();

        $orders = ServiceOrder::where('service_type', ServiceOrder::TYPE_TRACKING)
            ->where('status', ServiceOrder::STATUS_ACTIVE)
            ->whereNotNull('service_end_date')
            ->where('service_end_date', '<', $today)
            ->get();

        if ($orders->isEmpty()) {
            $this->info('No expired tracking orders found.');
            return self::SUCCESS;
        }

        $this->info(($dry ? '[dry-run] ' : '') . "Expiring {$orders->count()} tracking order(s):");

        foreach ($orders as $order) {
            $platforms = implode(', ', $order->config['platforms'] ?? []);
            $this->line("  - {$order->order_number} (organizer #{$order->marketplace_organizer_id}) platforms=[{$platforms}] ended={$order->service_end_date->toDateString()}");
            if (!$dry) {
                $order->complete();
            }
        }

        return self::SUCCESS;
    }
}
