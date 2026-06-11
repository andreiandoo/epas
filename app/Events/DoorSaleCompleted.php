<?php

namespace App\Events;

use App\Models\DoorSale;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DoorSaleCompleted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public DoorSale $doorSale) {}
}
