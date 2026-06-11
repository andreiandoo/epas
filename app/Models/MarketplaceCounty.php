<?php

namespace App\Models;

use App\Support\Translatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketplaceCounty extends Model
{
    use HasFactory, Translatable;

    protected $fillable = [
        'marketplace_client_id',
        'region_id',
        'name',
        'slug',
        'code',
        'country',
        'description',
        'image_url',
        'icon',
        'color',
        'sort_order',
        'is_visible',
        'is_featured',
        'city_count',
        'event_count',
    ];

    protected $casts = [
        'name' => 'array',
        'description' => 'array',
        'is_visible' => 'boolean',
        'is_featured' => 'boolean',
    ];

    public array $translatable = ['name', 'description'];

    public function marketplaceClient(): BelongsTo
    {
        return $this->belongsTo(MarketplaceClient::class);
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(MarketplaceRegion::class, 'region_id');
    }

    public function cities(): HasMany
    {
        return $this->hasMany(MarketplaceCity::class, 'county_id');
    }
}
