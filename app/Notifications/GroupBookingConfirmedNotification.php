<?php

namespace App\Notifications;

use App\Models\GroupBooking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class GroupBookingConfirmedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public GroupBooking $booking) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $event = $this->booking->event;

        return (new MailMessage)
            ->subject("Booking Confirmed - {$this->booking->group_name}")
            ->greeting('Booking Confirmed!')
            ->line("Your group booking for {$event->name} has been confirmed.")
            ->line("Group: {$this->booking->group_name}")
            ->line("Total Tickets: {$this->booking->total_tickets}")
            ->line("Total Paid: \${$this->booking->paid_amount}")
            ->action('View Booking', url("/group-bookings/{$this->booking->id}"))
            ->line('Tickets will be sent to all group members.');
    }
}
