<?php

namespace App\Console\Commands\Marketplace;

use App\Models\Marketplace\MarketplaceOrganizer;
use App\Models\Tenant;
use App\Services\Marketplace\PayoutService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class GeneratePayouts extends Command
{
    protected $signature = 'marketplace:generate-payouts
                            {--marketplace= : Specific marketplace ID to process}
                            {--organizer= : Specific organizer ID to process}
                            {--period-start= : Period start date (Y-m-d)}
                            {--period-end= : Period end date (Y-m-d)}
                            {--dry-run : Show what would be generated without creating payouts}';

    protected $description = 'Generate payouts for marketplace organizers';

    public function handle(PayoutService $payoutService): int
    {
        $this->info('Starting payout generation...');

        $periodEnd = $this->option('period-end')
            ? Carbon::parse($this->option('period-end'))
            : Carbon::yesterday()->endOfDay();

        $periodStart = $this->option('period-start')
            ? Carbon::parse($this->option('period-start'))
            : null;

        $dryRun = $this->option('dry-run');

        // Get marketplaces
        $marketplacesQuery = Tenant::where('tenant_type', Tenant::TYPE_MARKETPLACE);

        if ($this->option('marketplace')) {
            $marketplacesQuery->where('id', $this->option('marketplace'));
        }

        $marketplaces = $marketplacesQuery->get();

        if ($marketplaces->isEmpty()) {
            $this->warn('No marketplaces found.');
            return self::SUCCESS;
        }

        $totalPayouts = 0;
        $totalAmount = 0;

        foreach ($marketplaces as $marketplace) {
            $this->info("Processing marketplace: {$marketplace->name}");

            // Get organizers
            $organizersQuery = MarketplaceOrganizer::where('tenant_id', $marketplace->id)
                ->active();

            if ($this->option('organizer')) {
                $organizersQuery->where('id', $this->option('organizer'));
            }

            $organizers = $organizersQuery->get();

            foreach ($organizers as $organizer) {
                // Check if organizer has pending payable orders
                $pendingAmount = $organizer->pending_payout ?? 0;

                if ($pendingAmount < ($organizer->minimum_payout ?? 50)) {
                    $this->line("  - {$organizer->name}: Below minimum payout ({$pendingAmount} < {$organizer->minimum_payout})");
                    continue;
                }

                // Check payout frequency
                if (!$this->shouldGeneratePayout($organizer, $periodEnd)) {
                    $this->line("  - {$organizer->name}: Not due for payout yet");
                    continue;
                }

                if ($dryRun) {
                    $this->info("  - {$organizer->name}: Would generate payout of {$pendingAmount}");
                    $totalPayouts++;
                    $totalAmount += $pendingAmount;
                    continue;
                }

                try {
                    $payout = $payoutService->generatePayout(
                        $organizer,
                        $periodStart,
                        $periodEnd
                    );

                    if ($payout) {
                        $this->info("  - {$organizer->name}: Generated payout {$payout->reference} ({$payout->total_amount})");
                        $totalPayouts++;
                        $totalAmount += $payout->total_amount;
                    } else {
                        $this->line("  - {$organizer->name}: No orders to payout");
                    }
                } catch (\Exception $e) {
                    $this->error("  - {$organizer->name}: Error - " . $e->getMessage());
                }
            }
        }

        $this->newLine();
        $this->info("Summary:");
        $this->info("  - Payouts " . ($dryRun ? "would be " : "") . "generated: {$totalPayouts}");
        $this->info("  - Total amount: " . number_format($totalAmount, 2) . " RON");

        return self::SUCCESS;
    }

    protected function shouldGeneratePayout(MarketplaceOrganizer $organizer, Carbon $date): bool
    {
        $lastPayout = $organizer->payouts()->latest()->first();

        if (!$lastPayout) {
            return true;
        }

        $frequency = $organizer->payout_frequency ?? 'monthly';
        $lastPayoutDate = $lastPayout->created_at;

        return match ($frequency) {
            'weekly' => $lastPayoutDate->addWeek()->lte($date),
            'biweekly' => $lastPayoutDate->addWeeks(2)->lte($date),
            'monthly' => $lastPayoutDate->addMonth()->lte($date),
            default => $lastPayoutDate->addMonth()->lte($date),
        };
    }
}
