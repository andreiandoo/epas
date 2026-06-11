<?php

namespace App\Models;

use App\Support\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * F3 — Traveler type taxonomy (who an activity is for: couples, families, solo,
 * groups, kids, teens…). Per marketplace client, attachable to activities.
 */
class TravelerType extends Model
{
    use Translatable;

    protected $table = 'traveler_types';

    public array $translatable = ['name', 'description'];

    protected $fillable = [
        'marketplace_client_id',
        'slug',
        'name',
        'description',
        'icon_emoji',
        'color',
        'seo',
        'sort_order',
        'is_visible',
    ];

    protected $casts = [
        'name' => 'array',
        'description' => 'array',
        'seo' => 'array',
        'is_visible' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function marketplaceClient(): BelongsTo
    {
        return $this->belongsTo(MarketplaceClient::class);
    }

    public function activities(): BelongsToMany
    {
        return $this->belongsToMany(Activity::class, 'activity_traveler_type')
            ->withPivot('sort_order');
    }

    public function scopeVisible($query)
    {
        return $query->where('is_visible', true);
    }
}
