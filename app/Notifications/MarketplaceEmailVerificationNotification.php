<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MarketplaceEmailVerificationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected string $token;
    protected string $userType; // 'organizer' or 'customer'
    protected string $marketplaceDomain;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $token, string $userType, string $marketplaceDomain)
    {
        $this->token = $token;
        $this->userType = $userType;
        $this->marketplaceDomain = $marketplaceDomain;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $verificationUrl = $this->buildVerificationUrl($notifiable);

        $userTypeName = $this->userType === 'organizer' ? 'Organizer' : 'Customer';
        $greeting = $this->userType === 'organizer'
            ? "Welcome, {$notifiable->name}!"
            : "Welcome, {$notifiable->first_name}!";

        return (new MailMessage)
            ->subject('Verify Your Email Address')
            ->greeting($greeting)
            ->line('Thank you for registering! Please verify your email address to complete your account setup.')
            ->action('Verify Email Address', $verificationUrl)
            ->line('This verification link will expire in 24 hours.')
            ->line('If you did not create an account, no further action is required.')
            ->salutation('Best regards,');
    }

    /**
     * Build the verification URL
     */
    protected function buildVerificationUrl(object $notifiable): string
    {
        $baseUrl = rtrim($this->marketplaceDomain, '/');
        // Ensure domain has protocol
        if ($baseUrl && !str_starts_with($baseUrl, 'http')) {
            $baseUrl = 'https://' . $baseUrl;
        }

        // URL structure: /verify-email?token={token}&email={email}&type={type}
        return sprintf(
            '%s/verify-email?token=%s&email=%s&type=%s',
            $baseUrl,
            $this->token,
            urlencode($notifiable->email),
            $this->userType
        );
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'token' => $this->token,
            'user_type' => $this->userType,
            'email' => $notifiable->email,
        ];
    }
}
