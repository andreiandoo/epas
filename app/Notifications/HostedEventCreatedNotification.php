<?php

namespace App\Notifications;

use App\Models\Event;
use App\Models\Venue;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class HostedEventCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Event $event,
        public Venue $venue
    ) {}

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        $eventTitle = $this->event->getTranslation('title', 'en');
        $venueName = $this->venue->getTranslation('name', 'en');
        $organizerName = $this->event->tenant?->public_name ?? $this->event->tenant?->name ?? 'Unknown';
        $eventDate = $this->event->event_date?->format('F d, Y') ?? $this->event->range_start_date?->format('F d, Y') ?? 'TBD';

        return (new MailMessage)
            ->subject("New Event at Your Venue: {$eventTitle}")
            ->greeting('New Event Created!')
            ->line("A new event has been created at your venue **{$venueName}**.")
            ->line("**Event:** {$eventTitle}")
            ->line("**Organizer:** {$organizerName}")
            ->line("**Date:** {$eventDate}")
            ->action('View Event Details', url("/tenant/events/{$this->event->id}/view"))
            ->line('You can view all events at your venues from the Venue Usage page.');
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'hosted_event_created',
            'event_id' => $this->event->id,
            'event_title' => $this->event->getTranslation('title', 'en'),
            'venue_id' => $this->venue->id,
            'venue_name' => $this->venue->getTranslation('name', 'en'),
            'organizer_id' => $this->event->tenant_id,
            'organizer_name' => $this->event->tenant?->public_name ?? $this->event->tenant?->name,
            'event_date' => $this->event->event_date?->toIso8601String(),
        ];
    }
}
