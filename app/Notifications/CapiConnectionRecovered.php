<?php

namespace App\Notifications;

use App\Models\Integrations\FacebookCapi\FacebookCapiConnection;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Fired by `capi:health-check` when a CAPI connection flips back from
 * alerting → healthy (a `sent`-dominant window after the recovery).
 * Confirms to the recipients of the original Unhealthy alert that the
 * integration is back online; resets the alerting state so a future
 * regression triggers a fresh alert.
 */
class CapiConnectionRecovered extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array{total:int,sent:int,failed:int,window_hours:int,organizer_name:?string,marketplace_name:?string,outage_started:?string,outage_duration_human:?string}  $stats
     */
    public function __construct(
        public FacebookCapiConnection $connection,
        public array $stats,
    ) {}

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        $orgName = $this->stats['organizer_name'] ?? ('Organizer #' . $this->connection->marketplace_organizer_id);
        $mpName = $this->stats['marketplace_name'] ?? '';
        $window = $this->stats['window_hours'];
        $editUrl = url("/marketplace/organizers/{$this->connection->marketplace_organizer_id}/edit?tab=bilete-termeni");

        $msg = (new MailMessage)
            ->success()
            ->subject("✅ Facebook CAPI restabilit — {$orgName}")
            ->greeting('Integrare Facebook CAPI funcțională din nou')
            ->line("Pentru organizatorul **{$orgName}**" . ($mpName ? " ({$mpName})" : '') . ', evenimentele Facebook Conversions API curg din nou cu succes spre Meta.')
            ->line("**Pixel:** `{$this->connection->pixel_id}`")
            ->line("**În ultimele {$window} ore:** {$this->stats['total']} încercări, **{$this->stats['sent']} reușite**, {$this->stats['failed']} eșuate.");

        if (! empty($this->stats['outage_started'])) {
            $duration = $this->stats['outage_duration_human'] ?? '';
            $msg->line("**Durata incidentului:** {$this->stats['outage_started']}" . ($duration ? " ({$duration})" : ''));
        }

        $msg->line('Datele pierdute în timpul incidentului nu pot fi recuperate, dar tracking-ul de la momentul recuperării este complet.')
            ->action('Vezi pagina organizatorului', $editUrl)
            ->line('Vei primi din nou o alertă dacă integrarea cade în viitor.');

        return $msg;
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'capi_recovered',
            'connection_id' => $this->connection->id,
            'organizer_id' => $this->connection->marketplace_organizer_id,
            'organizer_name' => $this->stats['organizer_name'] ?? null,
            'pixel_id' => $this->connection->pixel_id,
            'sent' => $this->stats['sent'],
            'total' => $this->stats['total'],
            'window_hours' => $this->stats['window_hours'],
            'message' => "Facebook CAPI restored for organizer #{$this->connection->marketplace_organizer_id}",
        ];
    }
}
