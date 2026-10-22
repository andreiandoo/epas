<?php

namespace App\Jobs;

use App\Models\Event;
use App\Services\Marketplace\OrganizerFiscalDocuments;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * After a ticket type of an event changes, checks whether the event's cerere
 * de vizare is now outdated and tells the organizer (once per cerere).
 *
 * Runs a few minutes after the change, so a batch of edits on the same event
 * is checked once, against its final state.
 */
class CheckEventFiscalDocumentsJob implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public int $uniqueFor = 900;

    public function __construct(public int $eventId)
    {
    }

    public function uniqueId(): string
    {
        return (string) $this->eventId;
    }

    public static function schedule(?int $eventId): void
    {
        if (! $eventId || ! OrganizerFiscalDocuments::noticesAvailable()) {
            return;
        }

        try {
            static::dispatch($eventId)->delay(now()->addMinutes(3))->afterCommit();
        } catch (\Throwable $e) {
            Log::warning('CheckEventFiscalDocumentsJob: dispatch failed', ['event_id' => $eventId, 'error' => $e->getMessage()]);
        }
    }

    public function handle(OrganizerFiscalDocuments $documents): void
    {
        $event = Event::with(['marketplaceOrganizer', 'marketplaceClient'])->find($this->eventId);
        if (! $event || ! $event->marketplace_organizer_id) {
            return;
        }

        $documents->notifyIfNeeded($event);
    }
}
