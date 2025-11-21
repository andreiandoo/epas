<?php

namespace App\Notifications;

use App\Models\WaitlistEntry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WaitlistPositionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public WaitlistEntry $entry) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $event = $this->entry->event;
        $expiresAt = $this->entry->expires_at->format('M j, Y g:i A');

        return (new MailMessage)
            ->subject("Tickets Available - {$event->name}")
            ->greeting('Great news!')
            ->line("Tickets are now available for {$event->name}!")
            ->line("Your waitlist position ({$this->entry->position}) has been reached.")
            ->line("You have until {$expiresAt} to complete your purchase.")
            ->action('Buy Tickets Now', url("/events/{$event->id}/purchase?waitlist={$this->entry->id}"))
            ->line('This offer will expire if not claimed in time.');
    }
}
