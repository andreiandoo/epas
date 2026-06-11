<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\MarketplaceCustomer;
use Illuminate\Database\Seeder;

/**
 * Backfill marketplace_client_id on existing Customer records
 * by matching emails with MarketplaceCustomer records.
 *
 * Run: php artisan db:seed --class=BackfillCustomerMarketplaceSeeder
 */
class BackfillCustomerMarketplaceSeeder extends Seeder
{
    public function run(): void
    {
        $updated = 0;

        // Get all customers without marketplace_client_id
        Customer::whereNull('marketplace_client_id')
            ->chunkById(200, function ($customers) use (&$updated) {
                foreach ($customers as $customer) {
                    $mc = MarketplaceCustomer::where('email', $customer->email)->first();
                    if ($mc && $mc->marketplace_client_id) {
                        $customer->update(['marketplace_client_id' => $mc->marketplace_client_id]);
                        $updated++;
                    }
                }
            });

        $this->command->info("Backfilled marketplace_client_id on {$updated} customer(s).");
    }
}
