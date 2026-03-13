<?php

namespace App\Jobs\Shop;

use App\Models\Shop\ShopCart;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CleanupExpiredCartsJob implements ShouldQueue
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
            $this->cleanupCartsForTenant($tenant);
        }
    }

    protected function cleanupCartsForTenant(Tenant $tenant): void
    {
        $config = $tenant->microservices()
            ->where('slug', 'shop')
            ->first()
            ?->pivot
            ?->configuration ?? [];

        $cartExpiryHours = $config['cart_expiry_hours'] ?? 168; // Default 7 days

        // Delete expired carts and their items
        $expiredCarts = ShopCart::where('tenant_id', $tenant->id)
            ->where(function ($q) use ($cartExpiryHours) {
                // Carts with explicit expiry
                $q->whereNotNull('expires_at')
                    ->where('expires_at', '<', now());
            })
            ->orWhere(function ($q) use ($tenant, $cartExpiryHours) {
                // Old active/abandoned carts without expiry
                $q->where('tenant_id', $tenant->id)
                    ->whereIn('status', ['active', 'abandoned'])
                    ->whereNull('expires_at')
                    ->where('updated_at', '<', now()->subHours($cartExpiryHours));
            })
            ->get();

        $deletedCount = 0;
        foreach ($expiredCarts as $cart) {
            $cart->items()->delete();
            $cart->delete();
            $deletedCount++;
        }

        if ($deletedCount > 0) {
            Log::info('Cleaned up expired carts', [
                'tenant_id' => $tenant->id,
                'deleted_count' => $deletedCount,
            ]);
        }

        // Also clean up empty carts older than 24 hours
        $emptyCartsDeleted = ShopCart::where('tenant_id', $tenant->id)
            ->whereDoesntHave('items')
            ->where('updated_at', '<', now()->subHours(24))
            ->delete();

        if ($emptyCartsDeleted > 0) {
            Log::info('Cleaned up empty carts', [
                'tenant_id' => $tenant->id,
                'deleted_count' => $emptyCartsDeleted,
            ]);
        }
    }
}
