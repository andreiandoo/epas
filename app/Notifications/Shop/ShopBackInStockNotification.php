<?php

namespace App\Notifications\Shop;

use App\Models\Shop\ShopProduct;
use App\Models\Shop\ShopStockAlert;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ShopBackInStockNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public ShopStockAlert $alert,
        public ShopProduct $product
    ) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $language = app()->getLocale();
        $productTitle = $this->product->getTranslation('title', $language);
        $tenant = $this->product->tenant;
        $currency = $tenant->settings['currency'] ?? 'RON';
        $priceFormatted = number_format($this->product->display_price / 100, 2) . ' ' . $currency;

        $message = (new MailMessage)
            ->subject("{$productTitle} is Back in Stock!")
            ->greeting('Good news!')
            ->line("The product you were waiting for is now back in stock.")
            ->line("**{$productTitle}**")
            ->line("**Price:** {$priceFormatted}");

        if ($this->product->image_url) {
            // Note: MailMessage doesn't support inline images directly
            // but we include a reference
        }

        $message->line("Hurry! Stock is limited.");

        // Add shop URL if available
        $domain = $tenant->domains()->where('is_primary', true)->first();
        if ($domain) {
            $productUrl = "https://{$domain->domain}/shop/products/{$this->product->slug}";
            $message->action('View Product', $productUrl);
        }

        return $message;
    }

    public function toArray($notifiable): array
    {
        return [
            'alert_id' => $this->alert->id,
            'product_id' => $this->product->id,
            'product_title' => $this->product->getTranslation('title', 'en'),
        ];
    }
}
