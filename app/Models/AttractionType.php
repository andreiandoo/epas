<?php

namespace App\Models;

use App\Support\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * F4 — Attraction type taxonomy (monument, museum, park, castle, square…).
 */
class AttractionType extends Model
{
    use Translatable;

    protected $table = 'attraction_types';

    public array $translatable = ['name', 'description'];

    protected $fillable = [
        'marketplace_client_id',
        'slug',
        'name',
        'description',
        'icon_emoji',
        'color',
        'sort_order',
        'is_visible',
    ];

    protected $casts = [
        'name' => 'array',
        'description' => 'array',
        'is_visible' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function marketplaceClient(): BelongsTo
    {
        return $this->belongsTo(MarketplaceClient::class);
    }

    public function attractions(): HasMany
    {
        return $this->hasMany(Attraction::class);
    }

    public function scopeVisible($query)
    {
        return $query->where('is_visible', true);
    }
}
