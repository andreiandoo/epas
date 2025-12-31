<?php

namespace App\Events\Shop;

use App\Models\Shop\ShopCart;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ShopCheckoutStarted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $tenantId,
        public ShopCart $cart,
        public ?int $customerId = null,
        public array $context = []
    ) {}
}
