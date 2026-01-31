<?php

namespace App\Jobs\Shop;

use App\Models\Shop\ShopProduct;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\Shop\ShopLowStockNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class SendLowStockAlertsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public ?int $tenantId = null
    ) {}

    public function handle(): void
    {
        $query = Tenant::whereHas('microservices', function ($q) {
            $q->where('slug', 'shop')
                ->wherePivot('is_active', true);
        });

        if ($this->tenantId) {
            $query->where('id', $this->tenantId);
        }

        $tenants = $query->get();

        foreach ($tenants as $tenant) {
            $this->checkLowStockForTenant($tenant);
        }
    }

    protected function checkLowStockForTenant(Tenant $tenant): void
    {
        $config = $tenant->microservices()
            ->where('slug', 'shop')
            ->first()
            ?->pivot
            ?->configuration ?? [];

        // Get notification emails
        $alertEmails = $config['stock_alert_emails'] ?? [];
        if (empty($alertEmails)) {
            // Fall back to tenant admin users
            $alertEmails = User::where('tenant_id', $tenant->id)
                ->where('is_active', true)
                ->pluck('email')
                ->toArray();
        }

        if (empty($alertEmails)) {
            return;
        }

        // Find low stock products
        $lowStockProducts = ShopProduct::where('tenant_id', $tenant->id)
            ->where('track_inventory', true)
            ->where('status', 'active')
            ->lowStock()
            ->with('variants')
            ->get();

        // Find out of stock products
        $outOfStockProducts = ShopProduct::where('tenant_id', $tenant->id)
            ->where('track_inventory', true)
            ->where('status', 'active')
            ->outOfStock()
            ->with('variants')
            ->get();

        $alertsSent = 0;

        // Send alerts for low stock (only once per day)
        foreach ($lowStockProducts as $product) {
            if ($this->shouldSendAlert($product, 'low_stock')) {
                $this->sendAlert($product, $alertEmails);
                $this->markAlertSent($product, 'low_stock');
                $alertsSent++;
            }

            // Also check variants
            foreach ($product->variants as $variant) {
                if ($variant->isLowStock() && $this->shouldSendVariantAlert($variant, 'low_stock')) {
                    $this->sendAlert($product, $alertEmails, $variant);
                    $this->markVariantAlertSent($variant, 'low_stock');
                    $alertsSent++;
                }
            }
        }

        // Send alerts for out of stock
        foreach ($outOfStockProducts as $product) {
            if ($this->shouldSendAlert($product, 'out_of_stock')) {
                $this->sendAlert($product, $alertEmails);
                $this->markAlertSent($product, 'out_of_stock');
                $alertsSent++;
            }

            // Also check variants
            foreach ($product->variants as $variant) {
                if ($variant->stock_quantity <= 0 && $this->shouldSendVariantAlert($variant, 'out_of_stock')) {
                    $this->sendAlert($product, $alertEmails, $variant);
                    $this->markVariantAlertSent($variant, 'out_of_stock');
                    $alertsSent++;
                }
            }
        }

        if ($alertsSent > 0) {
            Log::info('Sent low stock alerts', [
                'tenant_id' => $tenant->id,
                'alerts_sent' => $alertsSent,
            ]);
        }
    }

    protected function shouldSendAlert(ShopProduct $product, string $type): bool
    {
        $meta = $product->meta ?? [];
        $lastAlert = $meta["last_{$type}_alert"] ?? null;

        if (!$lastAlert) {
            return true;
        }

        // Only send once per day
        return now()->diffInHours($lastAlert) >= 24;
    }

    protected function shouldSendVariantAlert($variant, string $type): bool
    {
        $meta = $variant->meta ?? [];
        $lastAlert = $meta["last_{$type}_alert"] ?? null;

        if (!$lastAlert) {
            return true;
        }

        return now()->diffInHours($lastAlert) >= 24;
    }

    protected function markAlertSent(ShopProduct $product, string $type): void
    {
        $meta = $product->meta ?? [];
        $meta["last_{$type}_alert"] = now()->toISOString();
        $product->update(['meta' => $meta]);
    }

    protected function markVariantAlertSent($variant, string $type): void
    {
        $meta = $variant->meta ?? [];
        $meta["last_{$type}_alert"] = now()->toISOString();
        $variant->update(['meta' => $meta]);
    }

    protected function sendAlert(ShopProduct $product, array $emails, $variant = null): void
    {
        try {
            Notification::route('mail', $emails)
                ->notify(new ShopLowStockNotification($product, $variant));
        } catch (\Exception $e) {
            Log::error('Failed to send low stock alert', [
                'product_id' => $product->id,
                'variant_id' => $variant?->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
