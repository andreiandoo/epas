<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MarketplaceEventReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Order $order,
        public string $reminderType // '24h' or '1h'
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $event = $this->order->event ?? $this->order->marketplaceEvent;
        $eventName = $event?->title ?? $event?->name ?? 'Event';
        $ticketCount = $this->order->tickets()->where('status', 'valid')->count();

        $timeText = $this->reminderType === '24h' ? 'tomorrow' : 'in 1 hour';
        $subject = $this->reminderType === '24h'
            ? "Reminder: {$eventName} is tomorrow!"
            : "Starting Soon: {$eventName} begins in 1 hour!";

        $mail = (new MailMessage)
            ->subject($subject)
            ->greeting("Hello {$this->order->customer_name},");

        if ($this->reminderType === '24h') {
            $mail->line("This is a friendly reminder that your event is tomorrow!")
                ->line("**Event:** {$eventName}");
        } else {
            $mail->line("Get ready! Your event starts in just 1 hour!")
                ->line("**Event:** {$eventName}");
        }

        if ($event?->starts_at) {
            $mail->line("**Date & Time:** " . $event->starts_at->format('l, F j, Y \a\t g:i A'));
        }

        if ($event?->venue_name || $event?->location) {
            $venue = $event->venue_name ?? $event->location;
            $mail->line("**Location:** {$venue}");

            if ($event->venue_address) {
                $mail->line("**Address:** {$event->venue_address}");
            }
        }

        $mail->line("**Tickets:** {$ticketCount}");

        if ($this->reminderType === '24h') {
            $mail->line("Make sure to have your tickets ready - you can access them from your account or the link below.");
        } else {
            $mail->line("Have your tickets ready at the entrance. See you there!");
        }

        return $mail
            ->action('View Your Tickets', $this->getTicketsUrl())
            ->line("We hope you enjoy the event!");
    }

    protected function getTicketsUrl(): string
    {
        $client = $this->order->marketplaceClient;
        if ($client && $client->domain) {
            return rtrim($client->domain, '/') . '/orders/' . $this->order->order_number;
        }
        return config('app.url') . '/orders/' . $this->order->order_number;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'reminder_type' => $this->reminderType,
        ];
    }
}
