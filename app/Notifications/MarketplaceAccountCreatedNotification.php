<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MarketplaceAccountCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $password,
        public ?string $clientDomain = null,
        public ?string $clientName = null,
        public ?string $setPasswordToken = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $siteName = $this->clientName ?? 'bilete.online';
        $domain = $this->clientDomain ? rtrim($this->clientDomain, '/') : config('app.url');
        // Ensure domain has protocol
        if ($domain && !str_starts_with($domain, 'http')) {
            $domain = 'https://' . $domain;
        }

        $mail = (new MailMessage)
            ->subject("Contul tău pe {$siteName}")
            ->greeting("Salut {$notifiable->first_name},")
            ->line("Ți-am creat automat un cont pe {$siteName} folosind datele de la ultima ta comandă.")
            ->line("**Email:** {$notifiable->email}");

        if ($this->setPasswordToken) {
            // Include a set-password link
            $setPasswordUrl = $domain . '/reset-password?' . http_build_query([
                'token' => $this->setPasswordToken,
                'email' => $notifiable->email,
            ]);
            $mail->line('Setează-ți o parolă pentru a-ți activa contul:')
                ->action('Setează parola', $setPasswordUrl)
                ->line('Linkul expiră în 60 de minute.');
        } else {
            // Fallback: show the auto-generated password
            $mail->line("**Parola:** {$this->password}")
                ->action('Intră în cont', $domain . '/cont')
                ->line('Îți recomandăm să îți schimbi parola după prima autentificare.');
        }

        return $mail->salutation("Echipa {$siteName}");
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'account_created',
        ];
    }
}
