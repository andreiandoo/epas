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
        public ?string $clientName = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $siteName = $this->clientName ?? 'bilete.online';
        $loginUrl = $this->clientDomain
            ? rtrim($this->clientDomain, '/') . '/cont'
            : config('app.url') . '/cont';

        return (new MailMessage)
            ->subject("Contul tău pe {$siteName}")
            ->greeting("Salut {$notifiable->first_name},")
            ->line("Ți-am creat automat un cont pe {$siteName} folosind datele de la ultima ta comandă.")
            ->line('Datele tale de autentificare:')
            ->line("**Email:** {$notifiable->email}")
            ->line("**Parola:** {$this->password}")
            ->action('Intră în cont', $loginUrl)
            ->line('Îți recomandăm să îți schimbi parola după prima autentificare.')
            ->salutation("Echipa {$siteName}");
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'account_created',
        ];
    }
}
