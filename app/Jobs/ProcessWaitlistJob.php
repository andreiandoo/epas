<?php

namespace App\Jobs;

use App\Models\WaitlistEntry;
use App\Events\WaitlistPositionAvailable;
use App\Notifications\WaitlistPositionNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessWaitlistJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public int $eventId,
        public int $availableQuantity
    ) {}

    public function handle(): void
    {
        $entries = WaitlistEntry::where('event_id', $this->eventId)
            ->where('status', 'waiting')
            ->orderBy('position')
            ->get();

        $remaining = $this->availableQuantity;

        foreach ($entries as $entry) {
            if ($remaining < $entry->quantity) {
                break;
            }

            $entry->update([
                'status' => 'notified',
                'notified_at' => now(),
                'expires_at' => now()->addHours(config('waitlist.offer_expiry_hours', 24)),
            ]);

            $remaining -= $entry->quantity;

            event(new WaitlistPositionAvailable($entry));

            if ($entry->customer) {
                $entry->customer->notify(new WaitlistPositionNotification($entry));
            }
        }
    }
}
