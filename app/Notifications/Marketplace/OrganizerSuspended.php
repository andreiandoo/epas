<?php

namespace App\Notifications\Marketplace;

use App\Models\Marketplace\MarketplaceOrganizer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrganizerSuspended extends Notification implements ShouldQueue
{
    use Queueable;

    protected MarketplaceOrganizer $organizer;
    protected ?string $reason;

    public function __construct(MarketplaceOrganizer $organizer, ?string $reason = null)
    {
        $this->organizer = $organizer;
        $this->reason = $reason;
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $marketplace = $this->organizer->marketplace;

        $mail = (new MailMessage)
            ->subject('Important: Your Organizer Account Has Been Suspended')
            ->greeting('Account Suspension Notice')
            ->line("Your organizer account **{$this->organizer->name}** on {$marketplace->name} has been suspended.");

        if ($this->reason) {
            $mail->line('**Reason:** ' . $this->reason);
        }

        return $mail
            ->line('While suspended:')
            ->line('- Your events will not be visible to customers')
            ->line('- You cannot create new events')
            ->line('- Pending payouts will be held')
            ->line('If you believe this is an error or would like to appeal, please contact support.')
            ->salutation('Regards, The ' . $marketplace->name . ' Team');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'organizer_suspended',
            'organizer_id' => $this->organizer->id,
            'organizer_name' => $this->organizer->name,
            'reason' => $this->reason,
            'message' => "Your organizer account has been suspended.",
        ];
    }
}
