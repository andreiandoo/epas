<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MarketplaceTicketType extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'marketplace_event_id',
        'name',
        'description',
        'price',
        'currency',
        'quantity',
        'quantity_sold',
        'quantity_reserved',
        'min_per_order',
        'max_per_order',
        'sale_starts_at',
        'sale_ends_at',
        'scheduled_at',
        'autostart_when_previous_sold_out',
        'status',
        'is_visible',
        'sort_order',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'sale_starts_at' => 'datetime',
        'sale_ends_at' => 'datetime',
        'scheduled_at' => 'datetime',
        'autostart_when_previous_sold_out' => 'boolean',
        'is_visible' => 'boolean',
    ];

    // =========================================
    // Relationships
    // =========================================

    public function event(): BelongsTo
    {
        return $this->belongsTo(MarketplaceEvent::class, 'marketplace_event_id');
    }

    // =========================================
    // Status Checks
    // =========================================

    public function isOnSale(): bool
    {
        return $this->status === 'on_sale';
    }

    public function isPaused(): bool
    {
        return $this->status === 'paused';
    }

    public function isSoldOut(): bool
    {
        return $this->status === 'sold_out' || $this->getAvailableQuantityAttribute() <= 0;
    }

    public function isAvailableForSale(): bool
    {
        if (!$this->isOnSale() || !$this->is_visible) {
            return false;
        }

        $now = now();

        if ($this->sale_starts_at && $now < $this->sale_starts_at) {
            return false;
        }

        if ($this->sale_ends_at && $now > $this->sale_ends_at) {
            return false;
        }

        if ($this->quantity !== null && $this->getAvailableQuantityAttribute() <= 0) {
            return false;
        }

        return true;
    }

    // =========================================
    // Inventory
    // =========================================

    public function getAvailableQuantityAttribute(): ?int
    {
        if ($this->quantity === null) {
            return null; // Unlimited
        }

        return max(0, $this->quantity - $this->quantity_sold - $this->quantity_reserved);
    }

    /**
     * Reserve tickets
     */
    public function reserve(int $quantity): bool
    {
        if ($this->quantity !== null) {
            $available = $this->available_quantity;
            if ($available < $quantity) {
                return false;
            }
        }

        $this->increment('quantity_reserved', $quantity);
        return true;
    }

    /**
     * Release reserved tickets
     */
    public function releaseReservation(int $quantity): void
    {
        $this->decrement('quantity_reserved', min($quantity, $this->quantity_reserved));
    }

    /**
     * Confirm sale (convert reservation to sold)
     */
    public function confirmSale(int $quantity): void
    {
        $this->decrement('quantity_reserved', min($quantity, $this->quantity_reserved));
        $this->increment('quantity_sold', $quantity);

        // Check if sold out
        if ($this->quantity !== null && $this->available_quantity <= 0) {
            $this->update(['status' => 'sold_out']);
        }
    }

    /**
     * Cancel sale (return to inventory)
     */
    public function cancelSale(int $quantity): void
    {
        $this->decrement('quantity_sold', min($quantity, $this->quantity_sold));

        // Restore to on_sale if was sold_out
        if ($this->status === 'sold_out' && $this->available_quantity > 0) {
            $this->update(['status' => 'on_sale']);
        }
    }

    // =========================================
    // Helpers
    // =========================================

    public function getPriceFormattedAttribute(): string
    {
        return number_format($this->price, 2) . ' ' . $this->currency;
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'on_sale' => 'On Sale',
            'paused' => 'Paused',
            'sold_out' => 'Sold Out',
            'hidden' => 'Hidden',
            default => ucfirst($this->status),
        };
    }
}
