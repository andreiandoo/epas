<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpireTicketSales extends Command
{
    protected $signature = 'ticket-types:expire-sales';

    protected $description = 'Auto-disable sale discounts on ticket types where sales_end_at has passed';

    public function handle(): int
    {
        $expired = DB::table('ticket_types')
            ->whereNotNull('sale_price_cents')
            ->where('sale_price_cents', '>', 0)
            ->whereNotNull('sales_end_at')
            ->where('sales_end_at', '<=', now())
            ->update([
                'sale_price_cents' => null,
                'sales_start_at' => null,
                'sales_end_at' => null,
                'sale_stock' => null,
                'sale_stock_sold' => null,
                'updated_at' => now(),
            ]);

        if ($expired > 0) {
            $this->info("Expired sale discounts on {$expired} ticket type(s)");
        } else {
            $this->info('No ticket sales to expire');
        }

        return Command::SUCCESS;
    }
}
