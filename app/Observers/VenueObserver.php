<?php

namespace App\Observers;

use App\Models\Venue;

class VenueObserver
{
    public function updated(Venue $venue): void
    {
        // If address changed, propagate to all linked events
        if ($venue->isDirty('address')) {
            $venue->events()->update(['address' => $venue->address]);
        }
    }
}
