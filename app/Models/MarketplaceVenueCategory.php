<?php

namespace App\Models;

use App\Support\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class MarketplaceVenueCategory extends Model
{
    use Translatable;

    protected $table = 'marketplace_venue_categories';

    /**
     * Translatable fields
     */
    public array $translatable = ['name', 'description'];

    protected $fillable = [
        'marketplace_client_id',
        'name',
        'slug',
        'description',
        'icon',
        'color',
        'image_url',
        'sort_order',
        'is_active',
        'is_featured',
    ];

    protected $casts = [
        'name' => 'array',
        'description' => 'array',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
    ];

    // ==================== RELATIONSHIPS ====================

    public function marketplaceClient(): BelongsTo
    {
        return $this->belongsTo(MarketplaceClient::class);
    }

    public function venues(): BelongsToMany
    {
        return $this->belongsToMany(
            Venue::class,
            'marketplace_venue_category_venue',
            'marketplace_venue_category_id',
            'venue_id'
        )->withPivot('sort_order')->withTimestamps();
    }

    // ==================== SCOPES ====================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    // ==================== ACCESSORS ====================

    public function getVenuesCountAttribute(): int
    {
        return $this->venues()->count();
    }

    // ==================== SLUG GENERATION ====================

    protected static function booted(): void
    {
        static::creating(function (self $category) {
            if (blank($category->slug)) {
                $name = is_array($category->name) ? ($category->name['en'] ?? $category->name['ro'] ?? '') : $category->name;
                if (filled($name)) {
                    $category->slug = static::uniqueSlug(Str::slug($name), $category->marketplace_client_id);
                }
            }
        });
    }

    protected static function uniqueSlug(string $base, ?int $marketplaceClientId = null): string
    {
        $slug = $base ?: 'category';
        $i = 1;

        $query = static::where('slug', $slug);
        if ($marketplaceClientId) {
            $query->where('marketplace_client_id', $marketplaceClientId);
        }

        while ($query->exists()) {
            $slug = $base . '-' . $i++;
            $query = static::where('slug', $slug);
            if ($marketplaceClientId) {
                $query->where('marketplace_client_id', $marketplaceClientId);
            }
        }

        return $slug;
    }
}
