<?php

namespace App\Observers\Seating;

use App\Models\Seating\EventSeat;
use App\Models\Seating\SeatingSeat;
use Illuminate\Support\Facades\Log;

/**
 * Keeps event_seats.seat_label in sync with seating_seats.label.
 *
 * Individual seat re-labels are rare but should propagate just like
 * row / section renames — same seat_uid in every event_seat snapshot.
 */
class SeatingSeatObserver
{
    public function updated(SeatingSeat $seat): void
    {
        if (!$seat->wasChanged('label')) {
            return;
        }

        $affected = EventSeat::where('seat_uid', $seat->seat_uid)->update([
            'seat_label' => (string) $seat->label,
        ]);

        if ($affected > 0) {
            Log::info('SeatingSeatObserver: propagated seat label change to event_seats', [
                'seating_seat_id' => $seat->id,
                'seat_uid' => $seat->seat_uid,
                'old_label' => $seat->getOriginal('label'),
                'new_label' => $seat->label,
                'event_seats_updated' => $affected,
            ]);
        }
    }
}
