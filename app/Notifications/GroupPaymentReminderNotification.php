<?php

namespace App\Notifications;

use App\Models\GroupBookingMember;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class GroupPaymentReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public GroupBookingMember $member) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $booking = $this->member->groupBooking;
        $event = $booking->event;

        return (new MailMessage)
            ->subject("Payment Reminder - {$booking->group_name}")
            ->greeting("Hello {$this->member->name}!")
            ->line("This is a reminder to complete your payment for {$event->name}.")
            ->line("Group: {$booking->group_name}")
            ->line("Amount Due: \${$this->member->amount_due}")
            ->action('Complete Payment', $this->member->payment_link)
            ->line('Please complete your payment to secure your spot.');
    }
}
