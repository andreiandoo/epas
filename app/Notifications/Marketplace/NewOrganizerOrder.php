<?php

namespace App\Notifications\Marketplace;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewOrganizerOrder extends Notification implements ShouldQueue
{
    use Queueable;

    protected Order $order;

    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $ticketCount = $this->order->tickets()->count();
        $revenue = number_format($this->order->organizer_revenue, 2);

        return (new MailMessage)
            ->subject("New Order: #{$this->order->id}")
            ->greeting('New Order Received!')
            ->line("You have received a new order.")
            ->line('**Order Details:**')
            ->line("- Order ID: #{$this->order->id}")
            ->line("- Customer: {$this->order->customer_email}")
            ->line("- Tickets: {$ticketCount}")
            ->line("- Your Revenue: {$revenue} RON")
            ->action('View Order', url("/organizer/orders/{$this->order->id}"))
            ->line('Thank you for using our platform!');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'new_order',
            'order_id' => $this->order->id,
            'customer_email' => $this->order->customer_email,
            'revenue' => $this->order->organizer_revenue,
            'message' => "New order #{$this->order->id} received.",
            'action_url' => "/organizer/orders/{$this->order->id}",
        ];
    }
}
