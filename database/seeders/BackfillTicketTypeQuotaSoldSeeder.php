<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Backfill ticket_types.quota_sold from actual tickets count.
 *
 * For imported events (e.g., from Ambilet), tickets exist in the tickets
 * table but ticket_types.quota_sold was never updated. This seeder
 * reconciles the counts.
 *
 * Run: php artisan db:seed --class=BackfillTicketTypeQuotaSoldSeeder
 */
class BackfillTicketTypeQuotaSoldSeeder extends Seeder
{
    public function run(): void
    {
        // Count actual valid tickets per ticket_type_id
        $ticketCounts = DB::table('tickets')
            ->whereNotNull('ticket_type_id')
            ->where(function ($q) {
                $q->where('is_cancelled', false)
                  ->orWhereNull('is_cancelled');
            })
            ->groupBy('ticket_type_id')
            ->select('ticket_type_id', DB::raw('COUNT(*) as cnt'))
            ->get();

        $updated = 0;
        foreach ($ticketCounts as $row) {
            $current = DB::table('ticket_types')
                ->where('id', $row->ticket_type_id)
                ->value('quota_sold');

            // Only update if tickets count is higher than current quota_sold
            if ((int) $current < (int) $row->cnt) {
                DB::table('ticket_types')
                    ->where('id', $row->ticket_type_id)
                    ->update(['quota_sold' => $row->cnt]);
                $updated++;
            }
        }

        $this->command->info("Updated quota_sold on {$updated} ticket type(s).");
        $this->command->info("Total ticket types with tickets: {$ticketCounts->count()}");
    }
}
