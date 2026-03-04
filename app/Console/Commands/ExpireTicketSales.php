<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpireTicketSales extends Command
{
    protected $signature = 'ticket-types:expire-sales';

    protected $description = 'Auto-disable sale discounts on ticket types where sales_end_at has passed or sale_stock is depleted';

    public function handle(): int
    {
        // Use Europe/Bucharest because DateTimePicker saves dates in local timezone
        $now = now('Europe/Bucharest');

        $clearFields = [
            'sale_price_cents' => null,
            'sales_start_at' => null,
            'sales_end_at' => null,
            'sale_stock' => null,
            'sale_stock_sold' => null,
            'updated_at' => $now,
        ];

        // 1. Expire by end date
        $expiredByDate = DB::table('ticket_types')
            ->whereNotNull('sale_price_cents')
            ->where('sale_price_cents', '>', 0)
            ->whereNotNull('sales_end_at')
            ->where('sales_end_at', '<=', $now)
            ->update($clearFields);

        // 2. Expire by depleted sale stock
        $expiredByStock = DB::table('ticket_types')
            ->whereNotNull('sale_price_cents')
            ->where('sale_price_cents', '>', 0)
            ->whereNotNull('sale_stock')
            ->where('sale_stock', '>', 0)
            ->whereColumn('sale_stock_sold', '>=', 'sale_stock')
            ->update($clearFields);

        $total = $expiredByDate + $expiredByStock;

        if ($total > 0) {
            $this->info("Expired sale discounts: {$expiredByDate} by date, {$expiredByStock} by stock depletion");
        } else {
            $this->info('No ticket sales to expire');
        }

        return Command::SUCCESS;
    }
}
