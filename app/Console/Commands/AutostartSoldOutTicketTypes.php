<?php

namespace App\Console\Commands;

use App\Models\TicketType;
use Illuminate\Console\Command;

class AutostartSoldOutTicketTypes extends Command
{
    protected $signature = 'ticket-types:autostart-sold-out';
    protected $description = 'Auto-activate hidden ticket types when the previous ticket type (by sort_order) is sold out';

    public function handle(): int
    {
        // Delegates to the shared resolver on the model, which also treats a
        // manually flagged (is_sold_out) previous ticket type as "sold out".
        $count = TicketType::autostartHiddenAfterSoldOut();

        if ($count > 0) {
            $this->info("Auto-activated {$count} ticket type(s) (previous sold out)");
            \Log::info("Auto-activated {$count} ticket type(s) (previous sold out)");
        }

        return self::SUCCESS;
    }
}
