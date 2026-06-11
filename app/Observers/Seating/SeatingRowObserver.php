<?php

namespace App\Observers\Seating;

use App\Models\Seating\EventSeat;
use App\Models\Seating\SeatingRow;
use App\Models\Seating\SeatingSeat;
use Illuminate\Support\Facades\Log;

/**
 * Keeps event_seats.row_label in sync with seating_rows.label.
 *
 * When an admin renames a row in the layout designer
 * (/admin/seating-layouts/{id}/designer), every event_seats snapshot
 * referencing seats in that row needs the new label — otherwise the
 * organizer-facing "Locuri Blocate" panel and PDFs show stale text.
 *
 * Done as a single UPDATE for performance (a row rename can touch 25–50
 * seats × N events using the layout; we don't want N×K save events).
 */
class SeatingRowObserver
{
    public function updated(SeatingRow $row): void
    {
        if (!$row->wasChanged('label')) {
            return;
        }

        $seatUids = SeatingSeat::where('row_id', $row->id)->pluck('seat_uid')->all();
        if (empty($seatUids)) {
            return;
        }

        $affected = EventSeat::whereIn('seat_uid', $seatUids)->update([
            'row_label' => (string) $row->label,
        ]);

        Log::info('SeatingRowObserver: propagated row label change to event_seats', [
            'seating_row_id' => $row->id,
            'old_label' => $row->getOriginal('label'),
            'new_label' => $row->label,
            'event_seats_updated' => $affected,
        ]);
    }
}
