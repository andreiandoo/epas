<?php

namespace App\Notifications;

use App\Models\AffiliateConversion;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AffiliateConversionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public AffiliateConversion $conversion) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $affiliate = $this->conversion->affiliate;
        $commissionDisplay = number_format($this->conversion->commission_value, 2) . ' ' . ($this->conversion->currency ?? 'RON');

        return (new MailMessage)
            ->subject("New Commission Earned - {$commissionDisplay}")
            ->greeting("Great news, {$affiliate->name}!")
            ->line("You've earned a new commission!")
            ->line("**Order Value:** " . number_format($this->conversion->order_value, 2) . ' ' . ($this->conversion->currency ?? 'RON'))
            ->line("**Your Commission:** {$commissionDisplay}")
            ->line("**Status:** " . ucfirst($this->conversion->status))
            ->when($this->conversion->status === 'pending', fn ($message) => $message
                ->line("This commission is currently pending review and will be credited to your available balance after the hold period.")
            )
            ->when($this->conversion->status === 'approved', fn ($message) => $message
                ->line("This commission has been approved and will be available for withdrawal after the hold period.")
            )
            ->line("Log in to your account to view your full earnings dashboard.");
    }
}
