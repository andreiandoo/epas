<?php

namespace App\Notifications;

use App\Models\Affiliate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AffiliateApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Affiliate $affiliate) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $tenant = $this->affiliate->tenant;
        $trackingUrl = $this->affiliate->getTrackingUrl();

        return (new MailMessage)
            ->subject("Your Affiliate Application Has Been Approved!")
            ->greeting("Congratulations {$this->affiliate->name}!")
            ->line("Your affiliate application for {$tenant->name} has been approved.")
            ->line("You can now start promoting and earning commissions.")
            ->line("Your unique affiliate code is: **{$this->affiliate->code}**")
            ->when($trackingUrl, fn ($message) => $message
                ->line("Share this tracking link with your audience:")
                ->action('Your Affiliate Link', $trackingUrl)
            )
            ->line("Commission rate: " . ($this->affiliate->commission_type === 'percent'
                ? "{$this->affiliate->commission_rate}%"
                : number_format($this->affiliate->commission_rate, 2) . " RON per sale"))
            ->line("Log in to your account to view your dashboard, track clicks, and monitor your earnings.");
    }
}
