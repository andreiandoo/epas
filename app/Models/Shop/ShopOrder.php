<?php

namespace App\Models\Shop;

use App\Models\Tenant;
use App\Models\Customer;
use App\Models\Event;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class ShopOrder extends Model
{
    use HasUuids;

    protected $table = 'shop_orders';

    protected $fillable = [
        'tenant_id',
        'order_number',
        'customer_id',
        'customer_email',
        'customer_phone',
        'status',
        'payment_status',
        'fulfillment_status',
        'subtotal_cents',
        'discount_cents',
        'shipping_cents',
        'tax_cents',
        'total_cents',
        'currency',
        'coupon_code',
        'coupon_discount_cents',
        'billing_address',
        'shipping_address',
        'shipping_method',
        'shipping_provider',
        'tracking_number',
        'tracking_url',
        'notes',
        'internal_notes',
        'event_id',
        'ticket_order_id',
        'payment_method',
        'payment_transaction_id',
        'meta',
        'paid_at',
        'shipped_at',
        'delivered_at',
        'completed_at',
        'cancelled_at',
    ];

    protected $casts = [
        'subtotal_cents' => 'integer',
        'discount_cents' => 'integer',
        'shipping_cents' => 'integer',
        'tax_cents' => 'integer',
        'total_cents' => 'integer',
        'coupon_discount_cents' => 'integer',
        'billing_address' => 'array',
        'shipping_address' => 'array',
        'meta' => 'array',
        'paid_at' => 'datetime',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    // Relationships

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ShopOrderItem::class, 'order_id');
    }

    public function giftCardTransactions(): HasMany
    {
        return $this->hasMany(ShopGiftCardTransaction::class, 'order_id');
    }

    // Scopes

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeProcessing(Builder $query): Builder
    {
        return $query->where('status', 'processing');
    }

    public function scopePaid(Builder $query): Builder
    {
        return $query->where('payment_status', 'paid');
    }

    public function scopeUnfulfilled(Builder $query): Builder
    {
        return $query->where('fulfillment_status', 'unfulfilled');
    }

    public function scopeNeedsShipping(Builder $query): Builder
    {
        return $query->where('payment_status', 'paid')
            ->where('fulfillment_status', '!=', 'fulfilled')
            ->where('status', '!=', 'cancelled');
    }

    // Order Number Generation

    public static function generateOrderNumber(int $tenantId): string
    {
        $prefix = 'SH';
        $year = date('Y');

        // Get tenant configuration for custom prefix
        $tenant = Tenant::find($tenantId);
        $config = $tenant?->microservices()
            ->where('slug', 'shop')
            ->first()
            ?->pivot
            ?->configuration;

        if (!empty($config['order_prefix'])) {
            $prefix = $config['order_prefix'];
        }

        // Get next sequence number
        $lastOrder = static::where('tenant_id', $tenantId)
            ->whereYear('created_at', $year)
            ->orderBy('order_number', 'desc')
            ->first();

        if ($lastOrder && preg_match('/(\d+)$/', $lastOrder->order_number, $matches)) {
            $sequence = (int) $matches[1] + 1;
        } else {
            $sequence = 1;
        }

        return sprintf('%s-%s-%05d', $prefix, $year, $sequence);
    }

    // Status Methods

    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isRefunded(): bool
    {
        return $this->status === 'refunded' || $this->payment_status === 'refunded';
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, ['pending', 'processing'])
            && $this->fulfillment_status !== 'fulfilled';
    }

    public function canBeRefunded(): bool
    {
        return $this->payment_status === 'paid'
            && !in_array($this->status, ['refunded', 'cancelled']);
    }

    // Fulfillment Methods

    public function markAsProcessing(): void
    {
        $this->update(['status' => 'processing']);
    }

    public function markAsShipped(?string $trackingNumber = null, ?string $trackingUrl = null): void
    {
        $data = [
            'status' => 'shipped',
            'fulfillment_status' => 'fulfilled',
            'shipped_at' => now(),
        ];

        if ($trackingNumber) {
            $data['tracking_number'] = $trackingNumber;
        }
        if ($trackingUrl) {
            $data['tracking_url'] = $trackingUrl;
        }

        $this->update($data);
    }

    public function markAsDelivered(): void
    {
        $this->update([
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);
    }

    public function markAsCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    public function markAsCancelled(?string $reason = null): void
    {
        $meta = $this->meta ?? [];
        if ($reason) {
            $meta['cancellation_reason'] = $reason;
        }

        $this->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'meta' => $meta,
        ]);

        // Restore stock
        foreach ($this->items as $item) {
            $item->product->incrementStock($item->quantity, $item->variant_id);
        }
    }

    // Helpers

    public function hasPhysicalProducts(): bool
    {
        return $this->items()->whereHas('product', function ($q) {
            $q->where('type', 'physical');
        })->exists();
    }

    public function hasDigitalProducts(): bool
    {
        return $this->items()->whereHas('product', function ($q) {
            $q->where('type', 'digital');
        })->exists();
    }

    public function getTotalWeight(): int
    {
        $weight = 0;

        foreach ($this->items as $item) {
            $itemWeight = $item->variant?->weight_grams ?? $item->product->weight_grams ?? 0;
            $weight += $itemWeight * $item->quantity;
        }

        return $weight;
    }

    public function recalculateTotals(): void
    {
        $subtotalCents = $this->items->sum('total_cents');
        $discountCents = $this->coupon_discount_cents ?? 0;
        $shippingCents = $this->shipping_cents ?? 0;
        $taxCents = $this->tax_cents ?? 0;

        $this->update([
            'subtotal_cents' => $subtotalCents,
            'discount_cents' => $discountCents,
            'total_cents' => max(0, $subtotalCents - $discountCents + $shippingCents + $taxCents),
        ]);
    }

    public function getFormattedBillingAddress(): string
    {
        $addr = $this->billing_address;
        if (!$addr) return '';

        $parts = array_filter([
            $addr['name'] ?? null,
            $addr['line1'] ?? $addr['address'] ?? null,
            $addr['line2'] ?? null,
            implode(', ', array_filter([
                $addr['city'] ?? null,
                $addr['state'] ?? $addr['region'] ?? null,
                $addr['postal_code'] ?? $addr['zip'] ?? null,
            ])),
            $addr['country'] ?? null,
        ]);

        return implode("\n", $parts);
    }

    public function getFormattedShippingAddress(): string
    {
        $addr = $this->shipping_address ?? $this->billing_address;
        if (!$addr) return '';

        $parts = array_filter([
            $addr['name'] ?? null,
            $addr['line1'] ?? $addr['address'] ?? null,
            $addr['line2'] ?? null,
            implode(', ', array_filter([
                $addr['city'] ?? null,
                $addr['state'] ?? $addr['region'] ?? null,
                $addr['postal_code'] ?? $addr['zip'] ?? null,
            ])),
            $addr['country'] ?? null,
        ]);

        return implode("\n", $parts);
    }
}
