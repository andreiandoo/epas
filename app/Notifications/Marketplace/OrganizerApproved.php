<?php

namespace App\Notifications\Marketplace;

use App\Models\Marketplace\MarketplaceOrganizer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrganizerApproved extends Notification implements ShouldQueue
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
            ->subject('Your Organizer Account Has Been Approved!')
            ->greeting('Congratulations!')
            ->line("Your organizer account **{$this->organizer->name}** has been approved on {$marketplace->name}.")
            ->line('You can now:')
            ->line('- Create and manage events')
            ->line('- Sell tickets to your events')
            ->line('- Track your sales and revenue')
            ->line('- Manage your team members')
            ->action('Go to Dashboard', url('/organizer'))
            ->line('Welcome to the platform! If you have any questions, please contact support.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'organizer_approved',
            'organizer_id' => $this->organizer->id,
            'organizer_name' => $this->organizer->name,
            'message' => "Your organizer account has been approved!",
            'action_url' => '/organizer',
        ];
    }
}
