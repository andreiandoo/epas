<?php

namespace App\Jobs;

use App\Models\DoorSale;
use App\Notifications\DoorSaleTicketsNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;

class SendDoorSaleTicketsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(public DoorSale $doorSale) {}

    public function handle(): void
    {
        if (!$this->doorSale->customer_email) {
            return;
        }

        $this->doorSale->load(['order.tickets', 'event', 'items.ticketType']);

        Notification::route('mail', $this->doorSale->customer_email)
            ->notify(new DoorSaleTicketsNotification($this->doorSale));
    }
}
