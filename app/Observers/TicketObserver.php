<?php

namespace App\Observers;

use App\Models\Ticket;
use App\Services\EventStatsCache;
use Illuminate\Support\Facades\DB;

/**
 * Invalidates the EventStatsCache when a ticket's status or check-in
 * changes — both are the events that affect the cached aggregate.
 *
 * Why DB::afterCommit: the observer otherwise fires INSIDE the calling
 * transaction (e.g. checkout creating 10 tickets) and a cache forget
 * before commit would race with subsequent reads inside the same
 * transaction. afterCommit also means forget is skipped if the outer
 * transaction rolls back — which is correct, because the data didn't
 * change.
 *
 * Why all hooks are wrapped in try/catch: the cache invalidation must
 * NEVER throw out of the observer, or it could break check-in /
 * checkout / refund flows. Worst case a stale 60s window — acceptable.
 *
 * Performance notes:
 * - Bulk operations (e.g. Ticket::whereIn(...)->update(...)) DO NOT fire
 *   per-row observers in Eloquent. So an import of 5000 tickets via mass
 *   update triggers zero forgets here. For mass operations that DO need
 *   to invalidate, call EventStatsCache::forget($eventId) explicitly.
 * - Single-row updates (the normal check-in / refund path) fire ONE
 *   forget per call which is microseconds against Redis.
 */
class TicketObserver
{
    public function created(Ticket $ticket): void
    {
        // A newly created ticket is born with status='valid' for paid orders
        // and contributes to total_sold immediately — must invalidate.
        $eventId = $ticket->event_id;
        DB::afterCommit(fn () => $this->safeForget($eventId));
    }

    public function updated(Ticket $ticket): void
    {
        // Only invalidate when something that affects the stats changed.
        // Avoid forgetting on cosmetic edits (note, internal flags).
        if (!$ticket->isDirty(['status', 'checked_in_at'])) {
            return;
        }
        $eventId = $ticket->event_id;
        DB::afterCommit(fn () => $this->safeForget($eventId));
    }

    public function deleted(Ticket $ticket): void
    {
        $eventId = $ticket->event_id;
        DB::afterCommit(fn () => $this->safeForget($eventId));
    }

    public function restored(Ticket $ticket): void
    {
        $eventId = $ticket->event_id;
        DB::afterCommit(fn () => $this->safeForget($eventId));
    }

    protected function safeForget(?int $eventId): void
    {
        try {
            EventStatsCache::forget($eventId);
        } catch (\Throwable $e) {
            // Swallow — stale stats for ≤60s is acceptable; broken
            // observer chain is not.
        }
    }
}
