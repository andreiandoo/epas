<?php

namespace App\Models;

use App\Support\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * F4 — Attraction (point of interest). Per marketplace client, geo-located,
 * typed, optionally tied to a city, and linked to activities many-to-many.
 */
class Attraction extends Model
{
    use Translatable;
    use SoftDeletes;

    protected $table = 'attractions';

    public array $translatable = ['name', 'subtitle', 'description'];

    protected $fillable = [
        'marketplace_client_id',
        'attraction_type_id',
        'marketplace_city_id',
        'slug',
        'name',
        'subtitle',
        'description',
        'cover_image_url',
        'gallery',
        'latitude',
        'longitude',
        'address',
        'seo',
        'faqs',
        'sort_order',
        'is_featured',
        'is_visible',
    ];

    protected $casts = [
        'name' => 'array',
        'subtitle' => 'array',
        'description' => 'array',
        'gallery' => 'array',
        'seo' => 'array',
        'faqs' => 'array',
        'latitude' => 'float',
        'longitude' => 'float',
        'is_featured' => 'boolean',
        'is_visible' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function marketplaceClient(): BelongsTo
    {
        return $this->belongsTo(MarketplaceClient::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(AttractionType::class, 'attraction_type_id');
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(MarketplaceCity::class, 'marketplace_city_id');
    }

    public function activities(): BelongsToMany
    {
        return $this->belongsToMany(Activity::class, 'activity_attraction')
            ->withPivot('sort_order');
    }

    public function scopeVisible($query)
    {
        return $query->where('is_visible', true);
    }
}
