<?php

namespace App\Console\Commands\Marketplace;

use App\Models\Marketplace\MarketplaceOrganizer;
use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RefreshOrganizerStats extends Command
{
    protected $signature = 'marketplace:refresh-stats
                            {--organizer= : Specific organizer ID to refresh}
                            {--marketplace= : Refresh all organizers in a marketplace}';

    protected $description = 'Refresh organizer statistics (events, orders, revenue, pending payout)';

    public function handle(): int
    {
        $this->info('Refreshing organizer statistics...');

        $query = MarketplaceOrganizer::query();

        if ($this->option('organizer')) {
            $query->where('id', $this->option('organizer'));
        }

        if ($this->option('marketplace')) {
            $query->where('tenant_id', $this->option('marketplace'));
        }

        $organizers = $query->get();

        if ($organizers->isEmpty()) {
            $this->warn('No organizers found.');
            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($organizers->count());
        $bar->start();

        foreach ($organizers as $organizer) {
            $this->refreshOrganizerStats($organizer);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Refreshed stats for {$organizers->count()} organizers.");

        return self::SUCCESS;
    }

    protected function refreshOrganizerStats(MarketplaceOrganizer $organizer): void
    {
        // Total events
        $totalEvents = $organizer->events()->count();

        // Total orders
        $totalOrders = Order::where('organizer_id', $organizer->id)->count();

        // Total revenue (from paid orders)
        $totalRevenue = Order::where('organizer_id', $organizer->id)
            ->whereIn('status', ['paid', 'completed'])
            ->sum('organizer_revenue');

        // Total paid out
        $totalPaidOut = Order::where('organizer_id', $organizer->id)
            ->whereNotNull('payout_id')
            ->whereHas('payout', function ($q) {
                $q->where('status', 'completed');
            })
            ->sum('organizer_revenue');

        // Pending payout
        $pendingPayout = Order::where('organizer_id', $organizer->id)
            ->whereIn('status', ['paid', 'completed'])
            ->whereNull('payout_id')
            ->sum('organizer_revenue');

        // Update organizer
        $organizer->update([
            'total_events' => $totalEvents,
            'total_orders' => $totalOrders,
            'total_revenue' => $totalRevenue,
            'total_paid_out' => $totalPaidOut,
            'pending_payout' => $pendingPayout,
        ]);
    }
}
