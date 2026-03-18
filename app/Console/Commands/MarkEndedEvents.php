<?php

namespace App\Console\Commands;

use App\Models\Event;
use Carbon\Carbon;
use Illuminate\Console\Command;

class MarkEndedEvents extends Command
{
    protected $signature = 'events:mark-ended';

    protected $description = 'Mark events as ended when their effective end datetime has passed';

    public function handle(): int
    {
        $now = Carbon::now();
        $count = 0;

        // Get all published, non-cancelled, non-ended events
        $events = Event::where('is_published', true)
            ->where(function ($q) {
                $q->where('is_cancelled', false)->orWhereNull('is_cancelled');
            })
            ->where(function ($q) {
                $q->where('is_ended', false)->orWhereNull('is_ended');
            })
            ->get();

        foreach ($events as $event) {
            $effectiveEnd = $event->getEffectiveEndDatetime();

            if ($effectiveEnd && $effectiveEnd->isPast()) {
                $event->update(['is_ended' => true]);
                $count++;
            }
        }

        $this->info("Marked {$count} events as ended.");

        return self::SUCCESS;
    }
}
