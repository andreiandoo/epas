<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MarketplacePasswordResetNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $token,
        public string $type, // 'organizer' or 'customer'
        public ?string $clientDomain = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $resetUrl = $this->getResetUrl($notifiable);
        $expireMinutes = config('auth.passwords.marketplace.expire', 60);

        return (new MailMessage)
            ->subject('Reset Your Password')
            ->greeting("Hello {$notifiable->name},")
            ->line('You are receiving this email because we received a password reset request for your account.')
            ->action('Reset Password', $resetUrl)
            ->line("This password reset link will expire in {$expireMinutes} minutes.")
            ->line('If you did not request a password reset, no further action is required.');
    }

    protected function getResetUrl($notifiable): string
    {
        $baseUrl = $this->clientDomain
            ? rtrim($this->clientDomain, '/')
            : config('app.url');

        $path = $this->type === 'organizer'
            ? '/organizer/reset-password'
            : '/reset-password';

        return $baseUrl . $path . '?' . http_build_query([
            'token' => $this->token,
            'email' => $notifiable->email,
        ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'token' => $this->token,
            'type' => $this->type,
        ];
    }
}
