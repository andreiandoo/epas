<?php

namespace App\Notifications\Shop;

use App\Models\Shop\ShopOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ShopOrderConfirmedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ShopOrder $order) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $tenant = $this->order->tenant;
        $currency = $this->order->currency ?? 'RON';
        $totalFormatted = number_format($this->order->total / 100, 2) . ' ' . $currency;

        $message = (new MailMessage)
            ->subject("Order Confirmed - #{$this->order->order_number}")
            ->greeting("Thank you for your order!")
            ->line("Your order #{$this->order->order_number} has been confirmed.");

        // Add order items
        $itemsText = [];
        foreach ($this->order->items as $item) {
            $itemTotal = number_format($item->total_cents / 100, 2) . ' ' . $currency;
            $itemsText[] = "{$item->quantity}x {$item->product_title} - {$itemTotal}";
        }

        $message->line('**Order Details:**');
        foreach ($itemsText as $itemText) {
            $message->line("- {$itemText}");
        }

        $message->line("**Total: {$totalFormatted}**");

        // Digital products notification
        if ($this->order->hasDigitalProducts()) {
            $message->line('Your digital products are ready for download.');
        }

        // Physical products notification
        if ($this->order->hasPhysicalProducts()) {
            $message->line('We will notify you when your order ships.');
        }

        // Add shipping address if applicable
        if ($this->order->hasPhysicalProducts() && $this->order->shipping_address) {
            $message->line('**Shipping Address:**');
            $message->line($this->order->getFormattedShippingAddress());
        }

        return $message;
    }

    public function toArray($notifiable): array
    {
        return [
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'total_cents' => $this->order->total_cents,
        ];
    }
}
