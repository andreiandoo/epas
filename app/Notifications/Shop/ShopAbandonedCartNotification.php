<?php

namespace App\Notifications\Shop;

use App\Models\Shop\ShopCart;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ShopAbandonedCartNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public ShopCart $cart,
        public int $emailNumber = 1
    ) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $tenant = $this->cart->tenant;
        $language = $tenant->language ?? 'en';
        $currency = $this->cart->currency ?? $tenant->settings['currency'] ?? 'RON';

        $subject = match($this->emailNumber) {
            1 => 'You left something behind!',
            2 => 'Your cart is waiting for you',
            3 => 'Last chance to complete your order',
            default => 'Complete your purchase',
        };

        $message = (new MailMessage)
            ->subject($subject);

        if ($this->emailNumber === 1) {
            $message->greeting('Did you forget something?')
                ->line('We noticed you left some items in your cart.');
        } elseif ($this->emailNumber === 2) {
            $message->greeting('Still thinking it over?')
                ->line('Your items are still waiting for you.');
        } else {
            $message->greeting('Last chance!')
                ->line("Don't miss out on these items.");
        }

        // List cart items
        $message->line('**Your Cart:**');

        $totalCents = 0;
        foreach ($this->cart->items as $item) {
            $productTitle = $item->product?->getTranslation('title', $language) ?? 'Product';
            $variantLabel = $item->variant?->getAttributeLabel();
            $itemName = $variantLabel ? "{$productTitle} - {$variantLabel}" : $productTitle;
            $itemPrice = number_format($item->total_cents / 100, 2);

            $message->line("- {$item->quantity}x {$itemName} - {$itemPrice} {$currency}");
            $totalCents += $item->total_cents;
        }

        $totalFormatted = number_format($totalCents / 100, 2) . ' ' . $currency;
        $message->line("**Subtotal: {$totalFormatted}**");

        // Recovery URL
        $domain = $tenant->domains()->where('is_primary', true)->first();
        if ($domain) {
            $recoveryUrl = "https://{$domain->domain}/shop/cart?recovery={$this->cart->id}";
            $message->action('Complete Your Purchase', $recoveryUrl);
        }

        $message->line('Questions? Just reply to this email.');

        return $message;
    }

    public function toArray($notifiable): array
    {
        return [
            'cart_id' => $this->cart->id,
            'email_number' => $this->emailNumber,
            'item_count' => $this->cart->getItemCount(),
            'subtotal_cents' => $this->cart->getSubtotalCents(),
        ];
    }
}
