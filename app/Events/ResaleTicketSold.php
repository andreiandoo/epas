<?php

namespace App\Events;

use App\Models\ResaleListing;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ResaleTicketSold
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public ResaleListing $listing,
        public int $buyerCustomerId
    ) {}
}
