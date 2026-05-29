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
        // Carbon comparisons (greaterThan, isPast, ...) operate on absolute UTC
        // instants regardless of display TZ. getEffectiveEndDatetime() now
        // parses end_time in the event's marketplace TZ, so a plain now() here
        // produces the correct boundary across marketplaces with different TZs.
        $now = Carbon::now();
        $count = 0;

        // Eager-load marketplaceClient so getEffectiveEndDatetime() doesn't
        // trigger an N+1 to resolve the TZ for each event.
        $events = Event::with('marketplaceClient:id,timezone')
            ->where('is_published', true)
            ->where('status', '!=', 'archived')
            ->where(function ($q) {
                $q->where('is_cancelled', false)->orWhereNull('is_cancelled');
            })
            ->get();

        foreach ($events as $event) {
            $effectiveEnd = $event->getEffectiveEndDatetime();

            if ($effectiveEnd && $now->greaterThan($effectiveEnd)) {
                // Only set status to archived — keep is_published true
                // so the event page remains accessible (shows "Încheiat" badge)
                $event->update([
                    'status' => 'archived',
                ]);
                $count++;
            }
        }

        $this->info("Marked {$count} events as archived (ended).");

        return self::SUCCESS;
    }
}
