<?php

namespace App\Notifications;

use App\Models\Integrations\FacebookCapi\FacebookCapiConnection;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Fired once per failure incident by `capi:health-check` when a CAPI
 * connection flips from healthy → alerting. The connection's
 * `last_alerted_at` is set after dispatch so a follow-up tick does not
 * re-send the same alert; the next mail only fires after the
 * connection recovers (Recovered notification) and breaks again.
 */
class CapiConnectionUnhealthy extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array{total:int,sent:int,failed:int,failure_pct:float,window_hours:int,latest_error:?string,since:?string,organizer_name:?string,marketplace_name:?string}  $stats
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
        $failPct = round($this->stats['failure_pct'], 1);
        $editUrl = url("/marketplace/organizers/{$this->connection->marketplace_organizer_id}/edit?tab=bilete-termeni");

        $msg = (new MailMessage)
            ->error()
            ->subject("⚠️ Facebook CAPI nu mai trimite evenimente — {$orgName}")
            ->greeting('Alertă integrare Facebook CAPI')
            ->line("Pentru organizatorul **{$orgName}**" . ($mpName ? " ({$mpName})" : '') . ', integrarea Facebook Conversions API a încetat să trimită evenimente cu succes spre Meta.')
            ->line("**Pixel:** `{$this->connection->pixel_id}`")
            ->line("**În ultimele {$window} ore:** {$this->stats['total']} încercări, {$this->stats['sent']} reușite, **{$this->stats['failed']} eșuate** ({$failPct}%).");

        if (! empty($this->stats['since'])) {
            $msg->line("**Eșecurile au început:** {$this->stats['since']}");
        }
        if (! empty($this->stats['latest_error'])) {
            $msg->line("**Ultimul mesaj de eroare de la Meta:**")
                ->line('> ' . mb_substr($this->stats['latest_error'], 0, 350));
        }

        $msg->line('**Cauze frecvente:**')
            ->line('• Access token-ul a fost invalidat de Meta (schimbare parolă cont, expirare token user vs. system, ștergere acces din Business Manager).')
            ->line('• Pixel ID configurat greșit sau șters.')
            ->line('• Permisiunile sistem user-ului au fost revocate.');

        $msg->line('**Cum se rezolvă:**')
            ->line('1. Regenerează un access token în Events Manager → Pixel → Settings → „Generate access token" (recomandat: System User Token cu expirare „Never").')
            ->line('2. Lipește token-ul în pagina organizatorului → secțiunea „Facebook Conversions API" → Save.')
            ->line('3. Click pe „Test conexiune cu Meta" pentru confirmare.')
            ->action('Deschide pagina organizatorului', $editUrl)
            ->line('Vei primi o a doua notificare când evenimentele se reiau cu succes. Până atunci, ad spend-ul Meta pentru acest organizator rulează fără date de conversie corecte.');

        return $msg;
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'capi_unhealthy',
            'connection_id' => $this->connection->id,
            'organizer_id' => $this->connection->marketplace_organizer_id,
            'organizer_name' => $this->stats['organizer_name'] ?? null,
            'pixel_id' => $this->connection->pixel_id,
            'failure_pct' => round($this->stats['failure_pct'], 1),
            'failed' => $this->stats['failed'],
            'total' => $this->stats['total'],
            'window_hours' => $this->stats['window_hours'],
            'latest_error' => $this->stats['latest_error'] ?? null,
            'message' => "Facebook CAPI failing for organizer #{$this->connection->marketplace_organizer_id}",
        ];
    }
}
