<?php

namespace App\Notifications\Booking;

use App\Models\ArtistBookingRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Trimisă către guest când artistul a refuzat cererea.
 */
class BookingRejectedNotification extends Notification implements ShouldQueue
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
        $artistName = $r->artist?->name ?? 'Artist';
        if (is_array($artistName)) {
            $artistName = $artistName['ro'] ?? $artistName['en'] ?? reset($artistName) ?: 'Artist';
        }

        $msg = (new MailMessage)
            ->subject('Cererea ta de booking pentru ' . $artistName . ' a fost refuzată')
            ->greeting('Salut, ' . e($r->guest_name) . '!')
            ->line('Din păcate, **' . e($artistName) . '** nu poate accepta cererea ta de booking pentru ' . $r->event_date->translatedFormat('j M Y') . ' la ' . e($r->event_city) . '.');

        if ($r->rejection_reason) {
            $msg->line('**Motiv:** ' . e($r->rejection_reason));
        }

        $msg->line('Mulțumim pentru interes — încearcă o altă dată sau alt artist din platformă.');

        return $msg;
    }
}
