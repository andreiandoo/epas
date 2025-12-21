<?php

namespace App\Notifications\Marketplace;

use App\Models\Marketplace\MarketplacePayout;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PayoutReady extends Notification implements ShouldQueue
{
    use Queueable;

    protected MarketplacePayout $payout;

    public function __construct(MarketplacePayout $payout)
    {
        $this->payout = $payout;
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $organizer = $this->payout->organizer;
        $amount = number_format($this->payout->total_amount, 2);
        $currency = $this->payout->currency ?? 'RON';

        return (new MailMessage)
            ->subject("Payout Ready: {$amount} {$currency}")
            ->greeting('Your Payout is Ready!')
            ->line("A new payout of **{$amount} {$currency}** has been generated and is ready for processing.")
            ->line('**Payout Details:**')
            ->line('- Reference: ' . $this->payout->reference)
            ->line('- Period: ' . $this->payout->period_start->format('M j, Y') . ' - ' . $this->payout->period_end->format('M j, Y'))
            ->line('- Orders included: ' . $this->payout->orders()->count())
            ->action('View Payout', url("/organizer/payouts/{$this->payout->id}"))
            ->line('The marketplace administrator will process this payout shortly.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'payout_ready',
            'payout_id' => $this->payout->id,
            'reference' => $this->payout->reference,
            'amount' => $this->payout->total_amount,
            'currency' => $this->payout->currency ?? 'RON',
            'message' => "New payout of {$this->payout->total_amount} {$this->payout->currency} is ready.",
            'action_url' => "/organizer/payouts/{$this->payout->id}",
        ];
    }
}
