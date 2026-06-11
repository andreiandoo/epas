<?php

namespace App\Notifications;

use App\Models\WalletPass;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WalletPassReadyNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public WalletPass $walletPass) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $platform = $this->walletPass->platform === 'apple' ? 'Apple Wallet' : 'Google Pay';

        return (new MailMessage)
            ->subject('Your Mobile Pass is Ready')
            ->greeting('Hello!')
            ->line("Your {$platform} pass has been generated and is ready to download.")
            ->action('Download Pass', $this->walletPass->download_url)
            ->line('Add it to your wallet for easy access at the event.');
    }
}
