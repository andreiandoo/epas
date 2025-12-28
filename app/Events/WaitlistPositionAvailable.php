<?php

namespace App\Events;

use App\Models\WaitlistEntry;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WaitlistPositionAvailable
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public WaitlistEntry $entry) {}
}
