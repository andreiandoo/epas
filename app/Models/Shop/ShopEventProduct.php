<?php

namespace App\Models\Shop;

use App\Models\Event;
use App\Models\TicketType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class ShopEventProduct extends Model
{
    use HasUuids;

    protected $table = 'shop_event_products';

    protected $fillable = [
        'event_id',
        'product_id',
        'association_type',
        'ticket_type_id',
        'quantity_included',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'quantity_included' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    // Relationships

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(ShopProduct::class, 'product_id');
    }

    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class);
    }

    // Scopes

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeUpsells(Builder $query): Builder
    {
        return $query->where('association_type', 'upsell');
    }

    public function scopeBundles(Builder $query): Builder
    {
        return $query->where('association_type', 'bundle');
    }

    public function scopeForTicketType(Builder $query, int $ticketTypeId): Builder
    {
        return $query->where('ticket_type_id', $ticketTypeId);
    }

    // Helpers

    public function isUpsell(): bool
    {
        return $this->association_type === 'upsell';
    }

    public function isBundle(): bool
    {
        return $this->association_type === 'bundle';
    }

    // Static Methods

    public static function getUpsellsForEvent(int $eventId): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('event_id', $eventId)
            ->where('association_type', 'upsell')
            ->where('is_active', true)
            ->with(['product' => fn($q) => $q->active()->visible()])
            ->orderBy('sort_order')
            ->get()
            ->filter(fn($ep) => $ep->product !== null);
    }

    public static function getBundlesForTicketType(int $ticketTypeId): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('ticket_type_id', $ticketTypeId)
            ->where('association_type', 'bundle')
            ->where('is_active', true)
            ->with(['product' => fn($q) => $q->active()])
            ->orderBy('sort_order')
            ->get()
            ->filter(fn($ep) => $ep->product !== null);
    }
}
