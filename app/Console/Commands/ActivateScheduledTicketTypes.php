<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ActivateScheduledTicketTypes extends Command
{
    protected $signature = 'ticket-types:activate-scheduled';
    protected $description = 'Auto-activate hidden ticket types whose scheduled_at datetime has arrived';

    public function handle(): int
    {
        $now = now('Europe/Bucharest');

        $count = DB::table('ticket_types')
            ->where('status', 'hidden')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', $now)
            ->update([
                'status' => 'active',
                'scheduled_at' => null,
                'updated_at' => $now,
            ]);

        if ($count > 0) {
            $this->info("Auto-activated {$count} ticket type(s) (scheduled_at reached).");
            \Log::info("Auto-activated {$count} ticket type(s) (scheduled_at reached)");
        }

        return self::SUCCESS;
    }
}
