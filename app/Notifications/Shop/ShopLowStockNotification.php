<?php

namespace App\Notifications\Shop;

use App\Models\Shop\ShopProduct;
use App\Models\Shop\ShopProductVariant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ShopLowStockNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public ShopProduct $product,
        public ?ShopProductVariant $variant = null
    ) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $language = app()->getLocale();
        $productTitle = $this->product->getTranslation('title', $language);

        $stockQuantity = $this->variant?->stock_quantity ?? $this->product->stock_quantity;
        $threshold = $this->product->low_stock_threshold ?? 5;
        $sku = $this->variant?->sku ?? $this->product->sku;

        $subject = $stockQuantity <= 0
            ? "Out of Stock Alert: {$productTitle}"
            : "Low Stock Alert: {$productTitle}";

        $message = (new MailMessage)
            ->subject($subject)
            ->greeting('Inventory Alert');

        if ($stockQuantity <= 0) {
            $message->line("**{$productTitle}** is now OUT OF STOCK.");
        } else {
            $message->line("**{$productTitle}** is running low on stock.");
        }

        $message->line("**SKU:** {$sku}");

        if ($this->variant) {
            $message->line("**Variant:** {$this->variant->getAttributeLabel()}");
        }

        $message->line("**Current Stock:** {$stockQuantity}");
        $message->line("**Low Stock Threshold:** {$threshold}");

        $message->line('Please restock this product soon to avoid lost sales.');

        return $message;
    }

    public function toArray($notifiable): array
    {
        return [
            'product_id' => $this->product->id,
            'variant_id' => $this->variant?->id,
            'stock_quantity' => $this->variant?->stock_quantity ?? $this->product->stock_quantity,
            'sku' => $this->variant?->sku ?? $this->product->sku,
        ];
    }
}
