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
        $candidates = TicketType::where('status', 'hidden')
            ->where('autostart_when_previous_sold_out', true)
            ->get();

        if ($candidates->isEmpty()) {
            return self::SUCCESS;
        }

        $count = 0;

        foreach ($candidates as $candidate) {
            // Find the previous ticket type by sort_order for the same event
            $previous = TicketType::where('event_id', $candidate->event_id)
                ->where('sort_order', '<', $candidate->sort_order)
                ->orderByDesc('sort_order')
                ->orderByDesc('id')
                ->first();

            if (! $previous) {
                continue;
            }

            // Unlimited stock — never sold out
            if ($previous->quota_total < 0) {
                continue;
            }

            // Count valid (non-cancelled) tickets for the previous ticket type
            $validTicketCount = $previous->tickets()
                ->where(function ($q) {
                    $q->where('is_cancelled', false)
                      ->orWhereNull('is_cancelled');
                })
                ->count();

            // Previous is sold out when valid tickets >= capacity
            if ($validTicketCount >= $previous->quota_total) {
                $candidate->update([
                    'status' => 'active',
                    'autostart_when_previous_sold_out' => false,
                ]);
                $count++;

                $this->info("Auto-activated '{$candidate->sku}' (previous '{$previous->sku}' sold out: {$validTicketCount}/{$previous->quota_total})");
            }
        }

        if ($count > 0) {
            \Log::info("Auto-activated {$count} ticket type(s) (previous sold out)");
        }

        return self::SUCCESS;
    }
}
