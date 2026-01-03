<?php

namespace App\Models;

use App\Support\Translatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class MarketplaceCity extends Model
{
    use HasFactory, Translatable;

    protected $table = 'marketplace_cities';

    public array $translatable = ['name', 'description'];

    protected $fillable = [
        'marketplace_client_id',
        'region_id',
        'county_id',
        'name',
        'slug',
        'description',
        'country',
        'latitude',
        'longitude',
        'timezone',
        'image_url',
        'cover_image_url',
        'icon',
        'population',
        'sort_order',
        'is_visible',
        'is_featured',
        'is_capital',
        'venue_count',
        'event_count',
    ];

    protected $casts = [
        'name' => 'array',
        'description' => 'array',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'is_visible' => 'boolean',
        'is_featured' => 'boolean',
        'is_capital' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($city) {
            if (empty($city->slug)) {
                $lang = $city->marketplaceClient?->language ?? 'en';
                $baseName = $city->name[$lang] ?? reset($city->name) ?? 'city';
                $baseSlug = Str::slug($baseName);
                $slug = $baseSlug;
                $counter = 1;

                while (static::where('marketplace_client_id', $city->marketplace_client_id)
                    ->where('slug', $slug)
                    ->exists()) {
                    $slug = $baseSlug . '-' . $counter++;
                }

                $city->slug = $slug;
            }
        });

        // Update region city count when city is created/deleted
        static::created(function ($city) {
            $city->region?->updateCityCount();
        });

        static::deleted(function ($city) {
            $city->region?->updateCityCount();
        });
    }

    // =========================================
    // Relationships
    // =========================================

    public function marketplaceClient(): BelongsTo
    {
        return $this->belongsTo(MarketplaceClient::class);
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(MarketplaceRegion::class, 'region_id');
    }

    public function county(): BelongsTo
    {
        return $this->belongsTo(MarketplaceCounty::class, 'county_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(MarketplaceEvent::class, 'marketplace_city_id');
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

    public function scopeCapitals($query)
    {
        return $query->where('is_capital', true);
    }

    public function scopeForMarketplace($query, $marketplaceClientId)
    {
        return $query->where('marketplace_client_id', $marketplaceClientId);
    }

    public function scopeInRegion($query, $regionId)
    {
        return $query->where('region_id', $regionId);
    }

    // =========================================
    // Helpers
    // =========================================

    /**
     * Update event count
     */
    public function updateEventCount(): void
    {
        $this->update([
            'event_count' => $this->events()->where('status', 'published')->count(),
        ]);
    }

    /**
     * Get localized name
     */
    public function getLocalizedName(?string $locale = null): string
    {
        $locale = $locale ?? ($this->marketplaceClient?->language ?? 'en');
        return $this->name[$locale] ?? $this->name['en'] ?? reset($this->name) ?? '';
    }

    /**
     * Get full location string (City, Region)
     */
    public function getFullLocationAttribute(): string
    {
        $locale = $this->marketplaceClient?->language ?? 'en';
        $cityName = $this->getLocalizedName($locale);
        $regionName = $this->region?->getLocalizedName($locale);

        return $regionName ? "{$cityName}, {$regionName}" : $cityName;
    }
}
