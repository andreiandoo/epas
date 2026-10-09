<?php

namespace App\Console\Commands;

use App\Models\TicketType;
use App\Support\AutomatedActivity;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
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
            'sale_stock_sold' => 0,
            'updated_at' => $now,
        ];

        $this->logStartedDiscounts($now);

        // 1. Expire by end date
        $expiredByDate = $this->expire(
            DB::table('ticket_types')
                ->whereNotNull('sale_price_cents')
                ->where('sale_price_cents', '>', 0)
                ->whereNotNull('sales_end_at')
                ->where('sales_end_at', '<=', $now),
            $clearFields,
            AutomatedActivity::TICKET_DISCOUNT_ENDED
        );

        // 2. Expire by depleted sale stock
        $expiredByStock = $this->expire(
            DB::table('ticket_types')
                ->whereNotNull('sale_price_cents')
                ->where('sale_price_cents', '>', 0)
                ->whereNotNull('sale_stock')
                ->where('sale_stock', '>', 0)
                ->whereColumn('sale_stock_sold', '>=', 'sale_stock'),
            $clearFields,
            AutomatedActivity::TICKET_DISCOUNT_STOCK_DEPLETED
        );

        $total = $expiredByDate + $expiredByStock;

        if ($total > 0) {
            $this->info("Expired sale discounts: {$expiredByDate} by date, {$expiredByStock} by stock depletion");
        } else {
            $this->info('No ticket sales to expire');
        }

        return Command::SUCCESS;
    }

    /**
     * Clear the discount on every matching ticket type and record it in the
     * activity log. Raw rows are read on purpose: the sale_price_cents accessor
     * on the model already returns null once the sale window has closed.
     */
    protected function expire(Builder $query, array $clearFields, string $action): int
    {
        $rows = $query->get(['id', 'event_id', 'price_cents', 'sale_price_cents', 'sales_end_at', 'sale_stock', 'sale_stock_sold']);

        $count = 0;

        foreach ($rows as $row) {
            $updated = DB::table('ticket_types')
                ->where('id', $row->id)
                ->whereNotNull('sale_price_cents')
                ->update($clearFields);

            if ($updated === 0) {
                continue;
            }

            $count++;

            AutomatedActivity::log(
                (new TicketType)->newFromBuilder(['id' => $row->id, 'event_id' => $row->event_id]),
                $action,
                ['sale_price_cents' => (int) $row->sale_price_cents],
                ['sale_price_cents' => null],
                [
                    'price_cents' => $row->price_cents,
                    'sales_end_at' => $row->sales_end_at,
                    'sale_stock' => $row->sale_stock,
                    'sale_stock_sold' => $row->sale_stock_sold,
                ],
                'tenant'
            );
        }

        return $count;
    }

    /**
     * A scheduled discount starts on its own when sales_start_at arrives.
     * Nothing changes in the database at that moment, so record it once.
     * The short look-back keeps a discount saved with a start date already in
     * the past (started by hand, not by the clock) out of the log.
     */
    protected function logStartedDiscounts(Carbon $now): void
    {
        $rows = DB::table('ticket_types')
            ->whereNotNull('sale_price_cents')
            ->where('sale_price_cents', '>', 0)
            ->whereNotNull('sales_start_at')
            ->where('sales_start_at', '<=', $now)
            ->where('sales_start_at', '>', $now->copy()->subMinutes(15))
            ->where(function ($q) use ($now) {
                $q->whereNull('sales_end_at')->orWhere('sales_end_at', '>', $now);
            })
            ->get(['id', 'event_id', 'price_cents', 'sale_price_cents', 'sales_start_at', 'sales_end_at']);

        foreach ($rows as $row) {
            $ticketType = (new TicketType)->newFromBuilder(['id' => $row->id, 'event_id' => $row->event_id]);

            // created_at is stored in UTC, hence the plain now() here.
            if (AutomatedActivity::alreadyLogged($ticketType, AutomatedActivity::TICKET_DISCOUNT_STARTED, now()->subDay())) {
                continue;
            }

            AutomatedActivity::log(
                $ticketType,
                AutomatedActivity::TICKET_DISCOUNT_STARTED,
                ['sale_price_cents' => null],
                ['sale_price_cents' => (int) $row->sale_price_cents],
                [
                    'price_cents' => $row->price_cents,
                    'sales_start_at' => $row->sales_start_at,
                    'sales_end_at' => $row->sales_end_at,
                ],
                'tenant'
            );
        }
    }
}
