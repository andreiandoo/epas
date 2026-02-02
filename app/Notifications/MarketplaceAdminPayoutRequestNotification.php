<?php

namespace App\Notifications;

use App\Models\MarketplacePayout;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MarketplaceAdminPayoutRequestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public MarketplacePayout $payout
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $organizer = $this->payout->organizer;
        $amount = number_format($this->payout->amount, 2) . ' ' . $this->payout->currency;

        return (new MailMessage)
            ->subject("New Payout Request - {$this->payout->reference}")
            ->greeting("Hello {$notifiable->name},")
            ->line("A new payout request has been submitted and requires your attention.")
            ->line("**Reference:** {$this->payout->reference}")
            ->line("**Organizer:** {$organizer->name}")
            ->line("**Amount:** {$amount}")
            ->line("**Bank:** " . ($this->payout->payout_method['bank_name'] ?? 'N/A'))
            ->line("**IBAN:** " . ($this->payout->payout_method['iban'] ?? 'N/A'))
            ->action('Review Payout', url("/marketplace/payouts/{$this->payout->id}"))
            ->line("Please review and process this request.");
    }

    public function toArray(object $notifiable): array
    {
        $organizer = $this->payout->organizer;
        $amount = number_format($this->payout->amount, 2) . ' ' . $this->payout->currency;

        return [
            'type' => 'payout_request',
            'payout_id' => $this->payout->id,
            'reference' => $this->payout->reference,
            'organizer_id' => $organizer->id,
            'organizer_name' => $organizer->name,
            'amount' => $this->payout->amount,
            'currency' => $this->payout->currency,
            'title' => 'Cerere nouă de plată',
            'message' => "{$organizer->name} a solicitat o plată de {$amount}",
            'url' => "/marketplace/payouts/{$this->payout->id}",
        ];
    }
}
