<?php

namespace App\Models\Shop;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ShopDigitalDownload extends Model
{
    use HasUuids;

    protected $table = 'shop_digital_downloads';

    protected $fillable = [
        'order_item_id',
        'customer_id',
        'download_token',
        'download_count',
        'max_downloads',
        'expires_at',
        'last_downloaded_at',
    ];

    protected $casts = [
        'download_count' => 'integer',
        'max_downloads' => 'integer',
        'expires_at' => 'datetime',
        'last_downloaded_at' => 'datetime',
    ];

    // Relationships

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(ShopOrderItem::class, 'order_item_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    // Boot

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($download) {
            if (!$download->download_token) {
                $download->download_token = Str::random(64);
            }
        });
    }

    // Methods

    public function canDownload(): bool
    {
        // Check if expired
        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        // Check download limit
        if ($this->max_downloads && $this->download_count >= $this->max_downloads) {
            return false;
        }

        // Check order status
        $order = $this->orderItem?->order;
        if (!$order || !in_array($order->payment_status, ['paid'])) {
            return false;
        }

        return true;
    }

    public function incrementDownload(): void
    {
        $this->increment('download_count');
        $this->update(['last_downloaded_at' => now()]);
    }

    public function getDownloadUrl(): string
    {
        return route('api.shop.download', ['token' => $this->download_token]);
    }

    public function getRemainingDownloads(): ?int
    {
        if (!$this->max_downloads) {
            return null; // Unlimited
        }

        return max(0, $this->max_downloads - $this->download_count);
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isLimitReached(): bool
    {
        return $this->max_downloads && $this->download_count >= $this->max_downloads;
    }

    // Static

    public static function createForOrderItem(ShopOrderItem $item): ?self
    {
        $product = $item->product;

        if (!$product || !$product->isDigital() || !$product->digital_file_url) {
            return null;
        }

        $order = $item->order;

        return static::create([
            'order_item_id' => $item->id,
            'customer_id' => $order->customer_id,
            'max_downloads' => $product->digital_download_limit,
            'expires_at' => $product->digital_download_expiry_days
                ? now()->addDays($product->digital_download_expiry_days)
                : null,
        ]);
    }
}
