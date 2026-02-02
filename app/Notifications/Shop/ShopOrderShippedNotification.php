<?php

namespace App\Notifications\Shop;

use App\Models\Shop\ShopOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ShopOrderShippedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ShopOrder $order) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject("Your Order Has Shipped - #{$this->order->order_number}")
            ->greeting("Good news!")
            ->line("Your order #{$this->order->order_number} has been shipped.");

        if ($this->order->shipping_method) {
            $message->line("**Shipping Method:** {$this->order->shipping_method}");
        }

        if ($this->order->tracking_number) {
            $message->line("**Tracking Number:** {$this->order->tracking_number}");

            if ($this->order->tracking_url) {
                $message->action('Track Your Package', $this->order->tracking_url);
            }
        }

        $message->line('**Shipping Address:**');
        $message->line($this->order->getFormattedShippingAddress());

        $message->line('Thank you for your order!');

        return $message;
    }

    public function toArray($notifiable): array
    {
        return [
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'tracking_number' => $this->order->tracking_number,
            'tracking_url' => $this->order->tracking_url,
        ];
    }
}
