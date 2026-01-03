<?php

namespace App\Models;

use App\Support\Translatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class MarketplaceRegion extends Model
{
    use HasFactory, Translatable;

    protected $table = 'marketplace_regions';

    public array $translatable = ['name', 'description'];

    protected $fillable = [
        'marketplace_client_id',
        'name',
        'slug',
        'description',
        'code',
        'country',
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

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($region) {
            if (empty($region->slug)) {
                $lang = $region->marketplaceClient?->language ?? 'en';
                $baseName = $region->name[$lang] ?? reset($region->name) ?? 'region';
                $baseSlug = Str::slug($baseName);
                $slug = $baseSlug;
                $counter = 1;

                while (static::where('marketplace_client_id', $region->marketplace_client_id)
                    ->where('slug', $slug)
                    ->exists()) {
                    $slug = $baseSlug . '-' . $counter++;
                }

                $region->slug = $slug;
            }
        });
    }

    // =========================================
    // Relationships
    // =========================================

    public function marketplaceClient(): BelongsTo
    {
        return $this->belongsTo(MarketplaceClient::class);
    }

    public function cities(): HasMany
    {
        return $this->hasMany(MarketplaceCity::class, 'region_id');
    }

    // =========================================
    // Scopes
    // =========================================

    public function scopeVisible($query)
    {
        return $query->where('is_visible', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeForMarketplace($query, $marketplaceClientId)
    {
        return $query->where('marketplace_client_id', $marketplaceClientId);
    }

    // =========================================
    // Helpers
    // =========================================

    /**
     * Update city count
     */
    public function updateCityCount(): void
    {
        $this->update([
            'city_count' => $this->cities()->where('is_visible', true)->count(),
        ]);
    }

    /**
     * Update event count across all cities
     */
    public function updateEventCount(): void
    {
        $cityIds = $this->cities()->pluck('id');
        $count = MarketplaceEvent::whereIn('marketplace_city_id', $cityIds)
            ->where('status', 'published')
            ->count();

        $this->update(['event_count' => $count]);
    }

    /**
     * Get localized name
     */
    public function getLocalizedName(?string $locale = null): string
    {
        $locale = $locale ?? ($this->marketplaceClient?->language ?? 'en');
        return $this->name[$locale] ?? $this->name['en'] ?? reset($this->name) ?? '';
    }
}
