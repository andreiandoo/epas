<?php

namespace App\Console\Commands;

use App\Models\TicketType;
use App\Support\AutomatedActivity;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ActivateScheduledTicketTypes extends Command
{
    protected $signature = 'ticket-types:activate-scheduled';
    protected $description = 'Auto-activate hidden ticket types whose scheduled_at datetime has arrived';

    public function handle(): int
    {
        $now = now('Europe/Bucharest');

        $due = DB::table('ticket_types')
            ->where('status', 'hidden')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', $now)
            ->get(['id', 'event_id', 'scheduled_at']);

        $count = 0;

        foreach ($due as $row) {
            // Raw update on purpose: skips the model saved hooks, as before.
            $updated = DB::table('ticket_types')
                ->where('id', $row->id)
                ->where('status', 'hidden')
                ->update([
                    'status' => 'active',
                    'scheduled_at' => null,
                    'updated_at' => $now,
                ]);

            if ($updated === 0) {
                continue;
            }

            $count++;

            AutomatedActivity::log(
                (new TicketType)->newFromBuilder(['id' => $row->id, 'event_id' => $row->event_id]),
                AutomatedActivity::TICKET_SCHEDULED_ACTIVATION,
                ['status' => 'hidden'],
                ['status' => 'active'],
                ['scheduled_at' => $row->scheduled_at],
                'tenant'
            );
        }

        if ($count > 0) {
            $this->info("Auto-activated {$count} ticket type(s) (scheduled_at reached).");
            \Log::info("Auto-activated {$count} ticket type(s) (scheduled_at reached)");
        }

        return self::SUCCESS;
    }
}
