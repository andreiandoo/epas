<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Banner custom din caruselul hero al homepage-ului marketplace-ului.
 */
class MarketplaceHeroBanner extends Model
{
    protected $fillable = [
        'marketplace_client_id',
        'name',
        'title',
        'subtitle',
        'url',
        'open_in_new_tab',
        'image_horizontal',
        'image_vertical',
        'position',
        'is_active',
        'starts_at',
        'ends_at',
    ];

    protected $casts = [
        'open_in_new_tab' => 'boolean',
        'is_active' => 'boolean',
        'position' => 'integer',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function marketplaceClient(): BelongsTo
    {
        return $this->belongsTo(MarketplaceClient::class);
    }

    /** Active and inside the optional schedule window. */
    public function scopeLive(Builder $query): Builder
    {
        $now = now();

        return $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', $now))
            ->where(fn ($q) => $q->whereNotNull('image_horizontal')->orWhereNotNull('image_vertical'));
    }

    public function toApiArray(): array
    {
        $disk = Storage::disk('public');

        return [
            'id' => $this->id,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'url' => $this->url,
            'open_in_new_tab' => $this->open_in_new_tab,
            'image_horizontal' => $this->image_horizontal ? $disk->url($this->image_horizontal) : null,
            'image_vertical' => $this->image_vertical ? $disk->url($this->image_vertical) : null,
            'position' => $this->position,
        ];
    }
}
