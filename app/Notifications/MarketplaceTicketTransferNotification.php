<?php

namespace App\Notifications;

use App\Models\MarketplaceTicketTransfer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MarketplaceTicketTransferNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public MarketplaceTicketTransfer $transfer,
        public string $action // 'initiated', 'received', 'accepted', 'rejected', 'cancelled'
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return match ($this->action) {
            'initiated' => $this->initiatedMail(),
            'received' => $this->receivedMail(),
            'accepted' => $this->acceptedMail(),
            'rejected' => $this->rejectedMail(),
            'cancelled' => $this->cancelledMail(),
            default => $this->defaultMail(),
        };
    }

    protected function initiatedMail(): MailMessage
    {
        $event = $this->transfer->ticket->event;
        $eventName = $event?->title ?? 'Event';

        return (new MailMessage)
            ->subject("Ticket Transfer Initiated - {$eventName}")
            ->greeting("Hello {$this->transfer->from_name},")
            ->line("You have initiated a ticket transfer.")
            ->line("**Event:** {$eventName}")
            ->line("**Ticket:** {$this->transfer->ticket->ticketType?->name}")
            ->line("**Transfer to:** {$this->transfer->to_name} ({$this->transfer->to_email})")
            ->line("The recipient will receive an email with instructions to accept the transfer.")
            ->line("This transfer will expire on " . $this->transfer->expires_at->format('F j, Y \a\t g:i A') . ".")
            ->line("You can cancel this transfer anytime before it's accepted.");
    }

    protected function receivedMail(): MailMessage
    {
        $event = $this->transfer->ticket->event;
        $eventName = $event?->title ?? 'Event';

        $mail = (new MailMessage)
            ->subject("You've Received a Ticket Transfer - {$eventName}")
            ->greeting("Hello {$this->transfer->to_name},")
            ->line("{$this->transfer->from_name} wants to transfer a ticket to you!")
            ->line("**Event:** {$eventName}")
            ->line("**Ticket:** {$this->transfer->ticket->ticketType?->name}");

        if ($event?->starts_at) {
            $mail->line("**Date:** " . $event->starts_at->format('F j, Y \a\t g:i A'));
        }

        if ($this->transfer->message) {
            $mail->line("**Message:** {$this->transfer->message}");
        }

        return $mail
            ->action('Accept Transfer', $this->transfer->getAcceptUrl())
            ->line("This transfer will expire on " . $this->transfer->expires_at->format('F j, Y \a\t g:i A') . ".")
            ->line("If you don't want this ticket, you can simply ignore this email.");
    }

    protected function acceptedMail(): MailMessage
    {
        $event = $this->transfer->ticket->event;
        $eventName = $event?->title ?? 'Event';

        return (new MailMessage)
            ->subject("Ticket Transfer Accepted - {$eventName}")
            ->greeting("Hello {$this->transfer->from_name},")
            ->line("Great news! {$this->transfer->to_name} has accepted your ticket transfer.")
            ->line("**Event:** {$eventName}")
            ->line("**Ticket:** {$this->transfer->ticket->ticketType?->name}")
            ->line("The ticket is now in their possession.");
    }

    protected function rejectedMail(): MailMessage
    {
        $event = $this->transfer->ticket->event;
        $eventName = $event?->title ?? 'Event';

        return (new MailMessage)
            ->subject("Ticket Transfer Declined - {$eventName}")
            ->greeting("Hello {$this->transfer->from_name},")
            ->line("{$this->transfer->to_name} has declined your ticket transfer.")
            ->line("**Event:** {$eventName}")
            ->line("**Ticket:** {$this->transfer->ticket->ticketType?->name}")
            ->line("The ticket remains in your possession. You can transfer it to someone else if you wish.");
    }

    protected function cancelledMail(): MailMessage
    {
        $event = $this->transfer->ticket->event;
        $eventName = $event?->title ?? 'Event';

        return (new MailMessage)
            ->subject("Ticket Transfer Cancelled - {$eventName}")
            ->greeting("Hello {$this->transfer->to_name},")
            ->line("The ticket transfer from {$this->transfer->from_name} has been cancelled.")
            ->line("**Event:** {$eventName}")
            ->line("**Ticket:** {$this->transfer->ticket->ticketType?->name}")
            ->line("If you still want to attend this event, you can purchase tickets directly.");
    }

    protected function defaultMail(): MailMessage
    {
        return (new MailMessage)
            ->subject("Ticket Transfer Update")
            ->line("There's an update about your ticket transfer.");
    }

    public function toArray(object $notifiable): array
    {
        return [
            'transfer_id' => $this->transfer->id,
            'ticket_id' => $this->transfer->ticket_id,
            'action' => $this->action,
        ];
    }
}
