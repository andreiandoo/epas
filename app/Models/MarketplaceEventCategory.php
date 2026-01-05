<?php

namespace App\Models;

use App\Support\Translatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class MarketplaceEventCategory extends Model
{
    use HasFactory, Translatable;

    protected $table = 'marketplace_event_categories';

    public array $translatable = ['name', 'description', 'meta_title', 'meta_description'];

    protected $fillable = [
        'marketplace_client_id',
        'parent_id',
        'name',
        'slug',
        'description',
        'image_url',
        'icon',
        'icon_emoji',
        'color',
        'meta_title',
        'meta_description',
        'sort_order',
        'is_visible',
        'is_featured',
        'event_count',
    ];

    protected $casts = [
        'name' => 'array',
        'description' => 'array',
        'meta_title' => 'array',
        'meta_description' => 'array',
        'is_visible' => 'boolean',
        'is_featured' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($category) {
            if (empty($category->slug)) {
                $lang = $category->marketplaceClient?->language ?? 'en';
                $baseName = $category->name[$lang] ?? reset($category->name) ?? 'category';
                $baseSlug = Str::slug($baseName);
                $slug = $baseSlug;
                $counter = 1;

                while (static::where('marketplace_client_id', $category->marketplace_client_id)
                    ->where('slug', $slug)
                    ->exists()) {
                    $slug = $baseSlug . '-' . $counter++;
                }

                $category->slug = $slug;
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

    public function parent(): BelongsTo
    {
        return $this->belongsTo(MarketplaceEventCategory::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(MarketplaceEventCategory::class, 'parent_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(MarketplaceEvent::class, 'marketplace_event_category_id');
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

    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeForMarketplace($query, $marketplaceClientId)
    {
        return $query->where('marketplace_client_id', $marketplaceClientId);
    }

    // =========================================
    // Helpers
    // =========================================

    /**
     * Update event count
     */
    public function updateEventCount(): void
    {
        $count = $this->events()->where('status', 'published')->count();

        // Include child categories if this is a parent
        foreach ($this->children as $child) {
            $count += $child->events()->where('status', 'published')->count();
        }

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

    /**
     * Get display name with icon
     */
    public function getDisplayNameAttribute(): string
    {
        $name = $this->getLocalizedName();
        return $this->icon_emoji ? "{$this->icon_emoji} {$name}" : $name;
    }

    /**
     * Get all ancestors
     */
    public function getAncestors(): array
    {
        $ancestors = [];
        $parent = $this->parent;

        while ($parent) {
            array_unshift($ancestors, $parent);
            $parent = $parent->parent;
        }

        return $ancestors;
    }

    /**
     * Get breadcrumb path
     */
    public function getBreadcrumbAttribute(): string
    {
        $path = array_map(fn($a) => $a->getLocalizedName(), $this->getAncestors());
        $path[] = $this->getLocalizedName();
        return implode(' > ', $path);
    }

    /**
     * Get full image URL from stored path
     */
    public function getImageFullUrlAttribute(): ?string
    {
        if (empty($this->image_url)) {
            return null;
        }

        // If already a full URL, return as-is
        if (str_starts_with($this->image_url, 'http://') || str_starts_with($this->image_url, 'https://')) {
            return $this->image_url;
        }

        // Convert relative path to full URL using Storage
        return \Illuminate\Support\Facades\Storage::disk('public')->url($this->image_url);
    }
}
