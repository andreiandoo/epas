<?php

namespace App\Models\Shop;

use App\Models\Tenant;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class ShopStockAlert extends Model
{
    use HasUuids;

    protected $table = 'shop_stock_alerts';

    protected $fillable = [
        'tenant_id',
        'product_id',
        'variant_id',
        'customer_id',
        'email',
        'status',
        'notified_at',
    ];

    protected $casts = [
        'notified_at' => 'datetime',
    ];

    // Relationships

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(ShopProduct::class, 'product_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ShopProductVariant::class, 'variant_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    // Scopes

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    // Methods

    public function markAsNotified(): void
    {
        $this->update([
            'status' => 'notified',
            'notified_at' => now(),
        ]);
    }

    public function cancel(): void
    {
        $this->update(['status' => 'cancelled']);
    }

    // Static

    public static function subscribe(
        int $tenantId,
        string $productId,
        string $email,
        ?string $variantId = null,
        ?int $customerId = null
    ): self {
        return static::firstOrCreate([
            'tenant_id' => $tenantId,
            'product_id' => $productId,
            'variant_id' => $variantId,
            'email' => $email,
            'status' => 'pending',
        ], [
            'customer_id' => $customerId,
        ]);
    }

    public static function notifyBackInStock(ShopProduct $product, ?ShopProductVariant $variant = null): int
    {
        $alerts = static::where('product_id', $product->id)
            ->where('variant_id', $variant?->id)
            ->where('status', 'pending')
            ->get();

        $count = 0;

        foreach ($alerts as $alert) {
            // Send notification email
            // TODO: Implement notification

            $alert->markAsNotified();
            $count++;
        }

        return $count;
    }
}
