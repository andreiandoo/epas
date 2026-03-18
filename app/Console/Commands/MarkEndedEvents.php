<?php

namespace App\Console\Commands;

use App\Models\Event;
use Carbon\Carbon;
use Illuminate\Console\Command;

class MarkEndedEvents extends Command
{
    protected $signature = 'events:mark-ended';

    protected $description = 'Mark events as archived when their effective end datetime has passed';

    public function handle(): int
    {
        $now = Carbon::now();
        $count = 0;

        // Get all published, non-cancelled events that are not yet archived
        $events = Event::where('is_published', true)
            ->where('status', '!=', 'archived')
            ->where(function ($q) {
                $q->where('is_cancelled', false)->orWhereNull('is_cancelled');
            })
            ->get();

        foreach ($events as $event) {
            $effectiveEnd = $event->getEffectiveEndDatetime();

            if ($effectiveEnd && $effectiveEnd->isPast()) {
                $event->update([
                    'status' => 'archived',
                    'is_published' => false,
                ]);
                $count++;
            }
        }

        $this->info("Marked {$count} events as archived (ended).");

        return self::SUCCESS;
    }
}
