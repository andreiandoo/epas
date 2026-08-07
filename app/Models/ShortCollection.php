<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * An editorial playlist of shorts — "Weekend in Bucharest", "Top festivals" (B7).
 */
class ShortCollection extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'description',
        'cover_path',
        'marketplace_client_id',
        'is_active',
        'sort',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $collection) {
            if (empty($collection->slug)) {
                $collection->slug = Str::slug($collection->title).'-'.Str::lower(Str::random(4));
            }
        });
    }

    public function shorts(): BelongsToMany
    {
        return $this->belongsToMany(Short::class, 'short_collection_items')
            ->withPivot('sort')
            ->orderByPivot('sort');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * A marketplace sees its own collections plus the global editorial ones.
     */
    public function scopeForClient(Builder $query, ?int $clientId): Builder
    {
        if (! $clientId) {
            return $query;
        }

        return $query->where(fn (Builder $q) => $q
            ->whereNull('marketplace_client_id')
            ->orWhere('marketplace_client_id', $clientId));
    }

    public function getCoverUrlAttribute(): ?string
    {
        if (! $this->cover_path) {
            return null;
        }

        if (str_starts_with($this->cover_path, 'http://') || str_starts_with($this->cover_path, 'https://')) {
            return $this->cover_path;
        }

        return Storage::disk('public')->url($this->cover_path);
    }
}
