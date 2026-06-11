<?php

namespace App\Jobs\Shop;

use App\Models\Shop\ShopStockAlert;
use App\Models\Shop\ShopProduct;
use App\Models\Tenant;
use App\Notifications\Shop\ShopBackInStockNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class ProcessBackInStockAlertsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public ?int $tenantId = null,
        public ?string $productId = null
    ) {}

    public function handle(): void
    {
        if ($this->productId) {
            // Process specific product
            $product = ShopProduct::find($this->productId);
            if ($product && $product->isInStock()) {
                $this->processAlertsForProduct($product);
            }
            return;
        }

        // Process all tenants
        $query = Tenant::whereHas('microservices', function ($q) {
            $q->where('slug', 'shop')
                ->wherePivot('is_active', true);
        });

        if ($this->tenantId) {
            $query->where('id', $this->tenantId);
        }

        $tenants = $query->get();

        foreach ($tenants as $tenant) {
            $this->processAlertsForTenant($tenant);
        }
    }

    protected function processAlertsForTenant(Tenant $tenant): void
    {
        // Find pending alerts where product is now in stock
        $alerts = ShopStockAlert::where('tenant_id', $tenant->id)
            ->where('status', 'pending')
            ->with(['product', 'product.variants'])
            ->get();

        foreach ($alerts as $alert) {
            $product = $alert->product;

            if (!$product || $product->status !== 'active' || !$product->is_visible) {
                // Product no longer available, cancel alert
                $alert->update(['status' => 'cancelled']);
                continue;
            }

            // Check if the specific variant or product is in stock
            $inStock = false;
            if ($alert->variant_id) {
                $variant = $product->variants->find($alert->variant_id);
                $inStock = $variant && $variant->isInStock();
            } else {
                $inStock = $product->isInStock();
            }

            if ($inStock) {
                $this->sendNotification($alert, $product);
            }
        }
    }

    protected function processAlertsForProduct(ShopProduct $product): void
    {
        $alerts = ShopStockAlert::where('product_id', $product->id)
            ->where('status', 'pending')
            ->get();

        foreach ($alerts as $alert) {
            // Check if the specific variant or product is in stock
            $inStock = false;
            if ($alert->variant_id) {
                $variant = $product->variants()->find($alert->variant_id);
                $inStock = $variant && $variant->isInStock();
            } else {
                $inStock = $product->isInStock();
            }

            if ($inStock) {
                $this->sendNotification($alert, $product);
            }
        }
    }

    protected function sendNotification(ShopStockAlert $alert, ShopProduct $product): void
    {
        try {
            Notification::route('mail', $alert->email)
                ->notify(new ShopBackInStockNotification($alert, $product));

            $alert->update([
                'status' => 'sent',
                'notified_at' => now(),
            ]);

            Log::info('Sent back in stock notification', [
                'alert_id' => $alert->id,
                'product_id' => $product->id,
                'email' => $alert->email,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send back in stock notification', [
                'alert_id' => $alert->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
