<?php

namespace App\Notifications\Booking;

use App\Models\ArtistBookingRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Trimisă către artist când un guest a trimis o nouă cerere de booking.
 */
class BookingRequestReceivedNotification extends Notification implements ShouldQueue
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
        $url = rtrim(config('app.marketplace_url') ?? config('app.url'), '/') . '/artist/cont/extended-artist/booking?request=' . $r->id;
        $feeFmt = number_format($r->proposed_fee_ron, 0, ',', '.');

        return (new MailMessage)
            ->subject('🎤 Cerere nouă de booking — ' . $r->event_city . ', ' . $r->event_date->translatedFormat('j M Y'))
            ->greeting('Salut!')
            ->line('Ai primit o cerere nouă de booking de la **' . e($r->guest_name) . '**'
                . ($r->guest_company ? ' (' . e($r->guest_company) . ')' : '') . '.')
            ->line('**Eveniment:** ' . $r->event_date->translatedFormat('j M Y')
                . ($r->event_time ? ' la ' . $r->event_time : '')
                . ' · ' . e($r->event_venue_name ?: $r->event_city)
                . ' · ' . ucfirst(e($r->event_type)))
            ->line('**Buget propus:** ' . $feeFmt . ' RON pentru ' . $r->proposed_set_length_min . ' min')
            ->line('**Mesaj:**' . "\n\n" . e(mb_substr($r->initial_message, 0, 500)))
            ->action('Vezi cererea în Inbox', $url)
            ->line('Răspunde rapid — un răspuns sub 24h crește rata de acceptare.');
    }
}
