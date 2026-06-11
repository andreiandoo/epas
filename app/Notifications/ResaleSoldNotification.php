<?php

namespace App\Notifications;

use App\Models\ResaleListing;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResaleSoldNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ResaleListing $listing) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $netAmount = $this->listing->asking_price - $this->listing->platform_fee;

        return (new MailMessage)
            ->subject('Your Ticket Has Been Sold!')
            ->greeting('Congratulations!')
            ->line('Your resale listing has been purchased.')
            ->line("Asking Price: \${$this->listing->asking_price}")
            ->line("Platform Fee: \${$this->listing->platform_fee}")
            ->line("Your Payout: \${$netAmount}")
            ->line('The payout will be processed within 3-5 business days.');
    }
}
