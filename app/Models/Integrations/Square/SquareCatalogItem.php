<?php

namespace App\Models\Integrations\Square;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SquareCatalogItem extends Model
{
    protected $fillable = [
        'connection_id',
        'catalog_object_id',
        'type',
        'name',
        'description',
        'price_cents',
        'currency',
        'sku',
        'category_id',
        'is_deleted',
        'local_type',
        'local_id',
        'metadata',
    ];

    protected $casts = [
        'is_deleted' => 'boolean',
        'metadata' => 'array',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(SquareConnection::class, 'connection_id');
    }

    public function getPriceAttribute(): float
    {
        return $this->price_cents / 100;
    }

    public function isItem(): bool
    {
        return $this->type === 'ITEM';
    }

    public function isVariation(): bool
    {
        return $this->type === 'ITEM_VARIATION';
    }
}
