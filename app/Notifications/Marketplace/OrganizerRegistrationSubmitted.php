<?php

namespace App\Notifications\Marketplace;

use App\Models\Marketplace\MarketplaceOrganizer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrganizerRegistrationSubmitted extends Notification implements ShouldQueue
{
    use Queueable;

    protected MarketplaceOrganizer $organizer;

    public function __construct(MarketplaceOrganizer $organizer)
    {
        $this->organizer = $organizer;
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $marketplace = $this->organizer->marketplace;

        return (new MailMessage)
            ->subject('New Organizer Registration: ' . $this->organizer->name)
            ->greeting('New Registration Submitted')
            ->line('A new organizer has registered on your marketplace.')
            ->line('**Organizer Details:**')
            ->line('- Name: ' . $this->organizer->name)
            ->line('- Contact: ' . $this->organizer->contact_name)
            ->line('- Email: ' . $this->organizer->contact_email)
            ->line('- Company: ' . ($this->organizer->company_name ?? 'Not provided'))
            ->action('Review Registration', url("/tenant/organizers/{$this->organizer->id}"))
            ->line('Please review and approve or reject this registration.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'organizer_registration',
            'organizer_id' => $this->organizer->id,
            'organizer_name' => $this->organizer->name,
            'contact_email' => $this->organizer->contact_email,
            'message' => "New organizer registration: {$this->organizer->name}",
            'action_url' => "/tenant/organizers/{$this->organizer->id}",
        ];
    }
}
