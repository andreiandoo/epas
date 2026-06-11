<?php

namespace App\Notifications;

use App\Models\DoorSale;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DoorSaleTicketsNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public DoorSale $doorSale) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $event = $this->doorSale->event;
        $ticketCount = $this->doorSale->getTotalTickets();

        $mail = (new MailMessage)
            ->subject("Your Tickets for {$event->name}")
            ->greeting('Thank you for your purchase!')
            ->line("You have purchased {$ticketCount} ticket(s) for {$event->name}.")
            ->line("Event Date: " . $event->start_date->format('M j, Y g:i A'))
            ->line("Order Total: {$this->doorSale->currency} {$this->doorSale->total}");

        // Add ticket details
        foreach ($this->doorSale->items as $item) {
            $mail->line("- {$item->quantity}x {$item->ticketType->name}");
        }

        $mail->action('View Tickets', url("/orders/{$this->doorSale->order_id}"))
            ->line('Please save this email as your purchase confirmation.');

        // In production, attach PDF tickets
        // foreach ($this->doorSale->order->tickets as $ticket) {
        //     $mail->attach($ticket->pdf_path);
        // }

        return $mail;
    }
}
