<?php

namespace App\Models;

use App\Support\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * F3 — Interest taxonomy (thematic angle of an activity: mystery, adventure,
 * culture, food, nature…). Per marketplace client, attachable to activities.
 */
class Interest extends Model
{
    use Translatable;

    protected $table = 'interests';

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
        return $this->belongsToMany(Activity::class, 'activity_interest')
            ->withPivot('sort_order');
    }

    public function scopeVisible($query)
    {
        return $query->where('is_visible', true);
    }
}
