<?php

namespace App\Models\Shop;

use App\Models\TicketType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ShopOrderItem extends Model
{
    use HasUuids;

    protected $table = 'shop_order_items';

    protected $fillable = [
        'order_id',
        'product_id',
        'variant_id',
        'quantity',
        'unit_price_cents',
        'total_cents',
        'product_snapshot',
        'variant_snapshot',
        'is_bundled',
        'ticket_type_id',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price_cents' => 'integer',
        'total_cents' => 'integer',
        'product_snapshot' => 'array',
        'variant_snapshot' => 'array',
        'is_bundled' => 'boolean',
    ];

    // Relationships

    public function order(): BelongsTo
    {
        return $this->belongsTo(ShopOrder::class, 'order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(ShopProduct::class, 'product_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ShopProductVariant::class, 'variant_id');
    }

    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class);
    }

    public function digitalDownload(): HasOne
    {
        return $this->hasOne(ShopDigitalDownload::class, 'order_item_id');
    }

    public function review(): HasOne
    {
        return $this->hasOne(ShopReview::class, 'order_item_id');
    }

    // Accessors

    public function getUnitPriceAttribute(): float
    {
        return $this->unit_price_cents / 100;
    }

    public function getTotalAttribute(): float
    {
        return $this->total_cents / 100;
    }

    public function getProductTitleAttribute(): string
    {
        return $this->product_snapshot['title'] ?? $this->product?->getTranslation('title', app()->getLocale()) ?? 'Unknown Product';
    }

    public function getVariantLabelAttribute(): ?string
    {
        if ($this->variant_snapshot) {
            return $this->variant_snapshot['attribute_label'] ?? null;
        }

        return $this->variant?->getAttributeLabel();
    }

    public function getFullNameAttribute(): string
    {
        $name = $this->product_title;
        $label = $this->variant_label;

        return $label ? "{$name} - {$label}" : $name;
    }

    // Helpers

    public function isDigital(): bool
    {
        return ($this->product_snapshot['type'] ?? $this->product?->type) === 'digital';
    }

    public function isPhysical(): bool
    {
        return ($this->product_snapshot['type'] ?? $this->product?->type) === 'physical';
    }

    public function isBundled(): bool
    {
        return $this->is_bundled;
    }

    public function canReview(): bool
    {
        // Can review if order is completed and no review exists
        return $this->order->status === 'completed'
            && !$this->review()->exists()
            && ($this->product_snapshot['reviews_enabled'] ?? $this->product?->reviews_enabled ?? true);
    }

    // Snapshot Creation

    public static function createSnapshot(ShopProduct $product, ?ShopProductVariant $variant = null): array
    {
        $locale = app()->getLocale();

        $snapshot = [
            'id' => $product->id,
            'title' => $product->getTranslation('title', $locale),
            'slug' => $product->slug,
            'sku' => $product->sku,
            'type' => $product->type,
            'image_url' => $product->image_url,
            'reviews_enabled' => $product->reviews_enabled,
        ];

        $variantSnapshot = null;
        if ($variant) {
            $variantSnapshot = [
                'id' => $variant->id,
                'sku' => $variant->sku,
                'attribute_label' => $variant->getAttributeLabel(),
                'image_url' => $variant->image_url,
            ];
        }

        return ['product' => $snapshot, 'variant' => $variantSnapshot];
    }
}
