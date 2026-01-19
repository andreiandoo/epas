<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MarketplaceOrderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Order $order,
        public string $action
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return match ($this->action) {
            'confirmed' => $this->confirmedMail($notifiable),
            'refunded' => $this->refundedMail($notifiable),
            'cancelled' => $this->cancelledMail($notifiable),
            'event_cancelled' => $this->eventCancelledMail($notifiable),
            default => $this->defaultMail($notifiable),
        };
    }

    protected function confirmedMail($notifiable): MailMessage
    {
        $event = $this->order->event ?? $this->order->marketplaceEvent;
        $eventName = $event?->title ?? $event?->name ?? 'Event';
        $ticketCount = $this->order->tickets()->count();

        return (new MailMessage)
            ->subject("Order Confirmed - {$this->order->order_number}")
            ->greeting("Hello {$this->order->customer_name},")
            ->line("Thank you for your purchase! Your order has been confirmed.")
            ->line("**Order Number:** {$this->order->order_number}")
            ->line("**Event:** {$eventName}")
            ->line("**Tickets:** {$ticketCount}")
            ->line("**Total:** {$this->order->total} {$this->order->currency}")
            ->line("Your tickets are now available for download. You can access them from your account or use the link below.")
            ->action('View Your Tickets', $this->getTicketsUrl())
            ->line("Please bring your tickets (printed or on your phone) to the event entrance.")
            ->line("Thank you for choosing us!");
    }

    protected function refundedMail($notifiable): MailMessage
    {
        $event = $this->order->event ?? $this->order->marketplaceEvent;
        $eventName = $event?->title ?? $event?->name ?? 'Event';

        $mail = (new MailMessage)
            ->subject("Order Refunded - {$this->order->order_number}")
            ->greeting("Hello {$this->order->customer_name},")
            ->line("Your order has been refunded.")
            ->line("**Order Number:** {$this->order->order_number}")
            ->line("**Event:** {$eventName}")
            ->line("**Refund Amount:** {$this->order->refund_amount} {$this->order->currency}");

        if ($this->order->refund_reason) {
            $mail->line("**Reason:** {$this->order->refund_reason}");
        }

        return $mail
            ->line("The refund will be processed to your original payment method within 5-10 business days.")
            ->line("If you have any questions, please contact support.");
    }

    protected function cancelledMail($notifiable): MailMessage
    {
        $event = $this->order->event ?? $this->order->marketplaceEvent;
        $eventName = $event?->title ?? $event?->name ?? 'Event';

        return (new MailMessage)
            ->subject("Order Cancelled - {$this->order->order_number}")
            ->greeting("Hello {$this->order->customer_name},")
            ->line("Your order has been cancelled.")
            ->line("**Order Number:** {$this->order->order_number}")
            ->line("**Event:** {$eventName}")
            ->line("If you did not request this cancellation, please contact support immediately.");
    }

    protected function eventCancelledMail($notifiable): MailMessage
    {
        $event = $this->order->event ?? $this->order->marketplaceEvent;
        $eventName = $event?->title ?? $event?->name ?? 'Event';

        return (new MailMessage)
            ->subject("Event Cancelled - Automatic Refund Issued")
            ->greeting("Hello {$this->order->customer_name},")
            ->line("We regret to inform you that the following event has been cancelled:")
            ->line("**Event:** {$eventName}")
            ->line("**Order Number:** {$this->order->order_number}")
            ->line("A full refund of **{$this->order->total} {$this->order->currency}** has been automatically issued.")
            ->line("The refund will be processed to your original payment method within 5-10 business days.")
            ->line("We apologize for any inconvenience this may have caused.");
    }

    protected function defaultMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Order Update - {$this->order->order_number}")
            ->greeting("Hello {$this->order->customer_name},")
            ->line("There's an update to your order.")
            ->line("**Order Number:** {$this->order->order_number}")
            ->line("**Status:** {$this->order->status}");
    }

    protected function getTicketsUrl(): string
    {
        $client = $this->order->marketplaceClient;
        if ($client && $client->domain) {
            return rtrim($client->domain, '/') . '/orders/' . $this->order->order_number;
        }
        return config('app.url') . '/orders/' . $this->order->order_number;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'status' => $this->order->status,
            'action' => $this->action,
        ];
    }
}
