<?php

namespace App\Observers\Seating;

use App\Models\Seating\EventSeat;
use App\Models\Seating\SeatingRow;
use App\Models\Seating\SeatingSeat;
use App\Models\Seating\SeatingSection;
use Illuminate\Support\Facades\Log;

/**
 * Keeps event_seats.section_name in sync with seating_sections.name.
 *
 * Section renames are rarer than row renames but produce larger fan-out
 * (every seat in every row of the section). Still done as a single
 * UPDATE — even a 500-seat section is fine in one statement.
 */
class SeatingSectionObserver
{
    public function updated(SeatingSection $section): void
    {
        if (!$section->wasChanged('name')) {
            return;
        }

        $rowIds = SeatingRow::where('section_id', $section->id)->pluck('id')->all();
        if (empty($rowIds)) {
            return;
        }
        $seatUids = SeatingSeat::whereIn('row_id', $rowIds)->pluck('seat_uid')->all();
        if (empty($seatUids)) {
            return;
        }

        $affected = EventSeat::whereIn('seat_uid', $seatUids)->update([
            'section_name' => (string) $section->name,
        ]);

        Log::info('SeatingSectionObserver: propagated section name change to event_seats', [
            'seating_section_id' => $section->id,
            'old_name' => $section->getOriginal('name'),
            'new_name' => $section->name,
            'event_seats_updated' => $affected,
        ]);
    }
}
