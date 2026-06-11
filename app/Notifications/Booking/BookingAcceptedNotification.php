<?php

namespace App\Notifications\Booking;

use App\Models\ArtistBookingRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Trimisă ambelor părți când booking-ul e confirmat.
 */
class BookingAcceptedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly ArtistBookingRequest $request,
        public readonly string $audience, // 'artist' | 'guest'
    ) {
    }

    public function via(): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $r = $this->request;
        $terms = $r->final_terms ?? [];
        $artistName = $r->artist?->name ?? 'Artist';
        if (is_array($artistName)) {
            $artistName = $artistName['ro'] ?? $artistName['en'] ?? reset($artistName) ?: 'Artist';
        }

        $url = $this->audience === 'artist'
            ? rtrim(config('app.marketplace_url') ?? config('app.url'), '/') . '/artist/cont/extended-artist/booking?tab=contracts'
            : rtrim(config('app.marketplace_url') ?? config('app.url'), '/') . '/booking/conversation/' . $r->guest_token;

        $msg = (new MailMessage)
            ->subject('🎉 Booking confirmat — ' . $artistName . ' · ' . $r->event_city . ' · ' . $r->event_date->translatedFormat('j M Y'))
            ->greeting($this->audience === 'artist' ? 'Felicitări!' : 'Salut, ' . e($r->guest_name) . '!')
            ->line($this->audience === 'artist'
                ? 'Cererea de booking de la **' . e($r->guest_name) . '** a fost confirmată.'
                : 'Cererea ta de booking pentru **' . e($artistName) . '** a fost confirmată.')
            ->line('**Termeni finali:**')
            ->line('· Data: **' . $r->event_date->translatedFormat('j M Y') . ($r->event_time ? ', ' . $r->event_time : '') . '**')
            ->line('· Locație: **' . e($r->event_venue_name ?: $r->event_city) . '**')
            ->line('· Buget: **' . number_format((int) ($terms['fee_ron'] ?? $r->proposed_fee_ron), 0, ',', '.') . ' RON**')
            ->line('· Lungime set: **' . ($terms['set_length_min'] ?? $r->proposed_set_length_min) . ' min**')
            ->line('Plata se face în afara platformei conform contractului dintre părți. Tixello servește doar ca intermediar pentru organizare.')
            ->action('Vezi detaliile', $url);

        return $msg;
    }
}
