<?php

namespace App\Notifications\Booking;

use App\Models\ArtistBookingMessage;
use App\Models\ArtistBookingRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Trimisă către un party (artist sau guest) când celălalt a postat mesaj/contraofertă.
 * audience = 'artist' → recipientul e artistul; audience = 'guest' → recipientul e guest.
 */
class BookingMessageReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly ArtistBookingRequest $request,
        public readonly ArtistBookingMessage $message,
        public readonly string $audience,
    ) {
    }

    public function via(): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $r = $this->request;
        $m = $this->message;
        $isCounter = $m->type === ArtistBookingMessage::TYPE_COUNTER;

        if ($this->audience === 'artist') {
            $url = rtrim(config('app.marketplace_url') ?? config('app.url'), '/') . '/artist/cont/extended-artist/booking?request=' . $r->id;
            $subject = $isCounter
                ? '📝 Contrapropunere de la ' . $r->guest_name . ' — ' . $r->event_city
                : '💬 Mesaj nou de la ' . $r->guest_name . ' — ' . $r->event_city;
            $msg = (new MailMessage)
                ->subject($subject)
                ->greeting('Salut!')
                ->line('Ai un răspuns nou de la **' . e($r->guest_name) . '** pe cererea pentru ' . $r->event_date->translatedFormat('j M Y') . ', ' . e($r->event_city) . '.');
        } else {
            $artistName = $r->artist?->name ?? 'Artist';
            if (is_array($artistName)) {
                $artistName = $artistName['ro'] ?? $artistName['en'] ?? reset($artistName) ?: 'Artist';
            }
            $url = rtrim(config('app.marketplace_url') ?? config('app.url'), '/') . '/booking/conversation/' . $r->guest_token;
            $subject = $isCounter
                ? '📝 Contrapropunere de la ' . $artistName
                : '💬 Mesaj nou de la ' . $artistName;
            $msg = (new MailMessage)
                ->subject($subject)
                ->greeting('Salut, ' . e($r->guest_name) . '!')
                ->line('**' . e($artistName) . '** a răspuns la cererea ta de booking.');
        }

        if ($isCounter && is_array($m->counter_terms)) {
            $msg->line('**Termeni propuși:**');
            if (!empty($m->counter_terms['fee_ron'])) {
                $msg->line('· Buget: **' . number_format($m->counter_terms['fee_ron'], 0, ',', '.') . ' RON**');
            }
            if (!empty($m->counter_terms['set_length_min'])) {
                $msg->line('· Lungime set: **' . $m->counter_terms['set_length_min'] . ' min**');
            }
            if (!empty($m->counter_terms['event_date'])) {
                $msg->line('· Data: **' . $m->counter_terms['event_date'] . '**');
            }
        }

        if ($m->body) {
            $msg->line('**Mesaj:** ' . e(mb_substr($m->body, 0, 500)));
        }

        return $msg->action('Vezi conversația', $url);
    }
}
