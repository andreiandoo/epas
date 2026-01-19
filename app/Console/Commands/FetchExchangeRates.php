<?php

namespace App\Console\Commands;

use App\Services\ExchangeRateService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class FetchExchangeRates extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'exchange-rates:fetch
                            {--date= : Specific date to fetch (Y-m-d)}
                            {--backfill= : Number of days to backfill}';

    /**
     * The console command description.
     */
    protected $description = 'Fetch and store daily exchange rates (EUR/RON)';

    /**
     * Execute the console command.
     */
    public function handle(ExchangeRateService $service): int
    {
        $this->info('Fetching exchange rates...');

        // Backfill mode
        if ($backfillDays = $this->option('backfill')) {
            $endDate = now();
            $startDate = now()->subDays((int) $backfillDays);

            $this->info("Backfilling rates from {$startDate->toDateString()} to {$endDate->toDateString()}");

            $count = $service->backfillRates($startDate, $endDate);

            $this->info("Backfilled {$count} exchange rates.");
            return Command::SUCCESS;
        }

        // Specific date or today
        $date = $this->option('date')
            ? Carbon::parse($this->option('date'))
            : now();

        $this->info("Fetching rates for: {$date->toDateString()}");

        if ($service->fetchAndStoreRates($date)) {
            $rate = \App\Models\ExchangeRate::getLatestRate('EUR', 'RON');
            $this->info("✓ Exchange rate stored: 1 EUR = {$rate} RON");
            return Command::SUCCESS;
        }

        $this->error('Failed to fetch exchange rates.');
        return Command::FAILURE;
    }
}
