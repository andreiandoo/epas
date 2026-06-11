<?php

namespace App\Events;

use App\Models\GroupBooking;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GroupBookingConfirmed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public GroupBooking $booking) {}
}
