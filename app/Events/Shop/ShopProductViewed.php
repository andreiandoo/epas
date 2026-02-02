<?php

namespace App\Events\Shop;

use App\Models\Shop\ShopProduct;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ShopProductViewed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $tenantId,
        public ShopProduct $product,
        public ?int $customerId = null,
        public ?string $sessionId = null,
        public array $context = []
    ) {}
}
