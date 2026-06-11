<?php

namespace App\Events\Shop;

use App\Models\Shop\ShopOrder;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ShopOrderCompleted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $tenantId,
        public ShopOrder $order,
        public array $paymentData = [],
        public array $context = []
    ) {}
}
