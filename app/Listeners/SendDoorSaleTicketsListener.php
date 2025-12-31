<?php

namespace App\Listeners;

use App\Events\DoorSaleCompleted;
use App\Jobs\SendDoorSaleTicketsJob;

class SendDoorSaleTicketsListener
{
    public function handle(DoorSaleCompleted $event): void
    {
        if ($event->doorSale->customer_email) {
            SendDoorSaleTicketsJob::dispatch($event->doorSale);
        }
    }
}
