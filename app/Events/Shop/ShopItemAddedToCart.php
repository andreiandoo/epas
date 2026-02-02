<?php

namespace App\Events\Shop;

use App\Models\Shop\ShopCart;
use App\Models\Shop\ShopCartItem;
use App\Models\Shop\ShopProduct;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ShopItemAddedToCart
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $tenantId,
        public ShopCart $cart,
        public ShopCartItem $item,
        public ShopProduct $product,
        public array $context = []
    ) {}
}
