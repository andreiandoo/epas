<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MarketplaceNewsletterWelcomeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected string $marketplaceDomain;
    protected string $marketplaceName;

    public function __construct(string $marketplaceDomain, string $marketplaceName)
    {
        $this->marketplaceDomain = rtrim($marketplaceDomain, '/');
        $this->marketplaceName = $marketplaceName;
    }

    public function via(object $notifiable): array
    {
        return ['marketplace-mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $firstName = $notifiable->first_name ?: 'there';
        $registerUrl = $this->marketplaceDomain . '/register';

        return (new MailMessage)
            ->subject("Bine ai venit in comunitatea {$this->marketplaceName}!")
            ->greeting("Salut, {$firstName}! 👋")
            ->line("Multumim ca te-ai abonat la newsletter-ul **{$this->marketplaceName}**! Esti acum parte dintr-o comunitate pasionata de evenimente.")
            ->line('**Ce vei primi de la noi:**')
            ->line('🎵 **Evenimente noi** — Fii primul care afla despre concerte, festivaluri si spectacole')
            ->line('🏷️ **Oferte exclusive** — Acces la reduceri si promotii speciale doar pentru abonati')
            ->line('📍 **Recomandari personalizate** — Evenimente din orasul tau si pe gusturile tale')
            ->line('⏰ **Alerte de disponibilitate** — Afla cand biletele se pun in vanzare')
            ->line('---')
            ->line('**Vrei si mai mult?** Creaza-ti un cont gratuit si deblocheaza functionalitati exclusive:')
            ->line('⭐ **Favorite** — Salveaza artistii si locatiile preferate')
            ->line('🔔 **Alerte personalizate** — Primeste notificari pentru evenimentele care te intereseaza')
            ->line('🎮 **Gamification** — Colecteaza puncte cu fiecare achizitie si obtine recompense')
            ->line('💰 **Discounturi** — Acces la oferte exclusive pentru membrii inregistrati')
            ->action('Creaza-ti cont gratuit', $registerUrl)
            ->line('Ne bucuram ca esti alaturi de noi!')
            ->salutation("Cu drag,\nEchipa {$this->marketplaceName}");
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'newsletter_welcome',
            'marketplace' => $this->marketplaceName,
        ];
    }
}
