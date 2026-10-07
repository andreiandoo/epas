<?php

namespace App\Console\Commands\Activities;

use App\Models\MarketplaceOrganizer;
use App\Services\Activities\ActivityCurrency;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Recalculates, for every product, the currency of its cheapest price and that price's value in euro.
 *
 * The euro value is what a marketplace with several currencies filters and sorts by, and it moves with the exchange
 * rate, so this runs once a day after the rates are fetched. It is also the back-fill after the migration that adds
 * the columns, and the repair after an operator's currency is changed outside the admin.
 *
 *   php artisan activities:refresh-eur-prices
 *   php artisan activities:refresh-eur-prices --marketplace=4
 *   php artisan activities:refresh-eur-prices --organizer=123     also gives the operator's variants his currency
 */
class RefreshActivityEurPricesCommand extends Command
{
    protected $signature = 'activities:refresh-eur-prices
        {--marketplace= : Only the products of one marketplace_client_id}
        {--organizer= : Only one operator; his price variants are first given his currency}';

    protected $description = 'Recalculate the currency and the euro value of each product\'s cheapest price.';

    public function handle(): int
    {
        if (! ActivityCurrency::ready()) {
            $this->warn('The currency columns are not there yet: run the migrations first.');

            return self::SUCCESS;
        }

        if ($organizerId = (int) $this->option('organizer')) {
            $organizer = MarketplaceOrganizer::find($organizerId);
            if (! $organizer) {
                $this->error("Operator {$organizerId} not found.");

                return self::FAILURE;
            }
            $count = ActivityCurrency::applyToOrganizer($organizer);
            $this->info("Operator {$organizerId} sells in " . ActivityCurrency::forOrganizer($organizer) . ": {$count} products refreshed.");

            return self::SUCCESS;
        }

        $query = DB::table('activities')->select('id');
        if ($marketplace = (int) $this->option('marketplace')) {
            $query->where('marketplace_client_id', $marketplace);
        }
        $count = 0;
        $query->orderBy('id')->chunkById(500, function ($rows) use (&$count) {
            foreach ($rows as $row) {
                ActivityCurrency::refresh((int) $row->id);
                $count++;
            }
        });
        $this->info("{$count} products refreshed.");

        return self::SUCCESS;
    }
}
