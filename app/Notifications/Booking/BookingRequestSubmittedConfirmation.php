<?php

namespace App\Notifications\Booking;

use App\Models\ArtistBookingRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Trimisă către guest după submit cerere — confirmare + link signed la conversație.
 */
class BookingRequestSubmittedConfirmation extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly ArtistBookingRequest $request)
    {
    }

    public function via(): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $r = $this->request;
        $artistName = $r->artist?->name ?? 'artist';
        if (is_array($artistName)) {
            $artistName = $artistName['ro'] ?? $artistName['en'] ?? reset($artistName) ?: 'artist';
        }
        $url = rtrim(config('app.marketplace_url') ?? config('app.url'), '/') . '/booking/conversation/' . $r->guest_token;

        return (new MailMessage)
            ->subject('✓ Cererea ta de booking a fost trimisă către ' . $artistName)
            ->greeting('Salut, ' . e($r->guest_name) . '!')
            ->line('Cererea ta de booking pentru **' . e($artistName) . '** a fost trimisă cu succes.')
            ->line('**Eveniment:** ' . $r->event_date->translatedFormat('j M Y')
                . ' · ' . e($r->event_venue_name ?: $r->event_city)
                . ' · ' . number_format($r->proposed_fee_ron, 0, ',', '.') . ' RON')
            ->line('Folosește link-ul de mai jos ca să urmărești conversația și să răspunzi când artistul revine cu un răspuns. Salvează-l — funcționează și fără cont.')
            ->action('Vezi conversația', $url)
            ->line('Artistul răspunde de obicei în 24 de ore.');
    }
}
