<?php

namespace App\Models\Shop;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use App\Support\Translatable;

class ShopCategory extends Model
{
    use HasUuids, Translatable;

    protected $table = 'shop_categories';

    public array $translatable = ['name', 'description'];

    protected $fillable = [
        'tenant_id',
        'parent_id',
        'name',
        'slug',
        'description',
        'image_url',
        'icon',
        'color',
        'sort_order',
        'is_active',
        'is_visible',
        'meta',
        'meta_title',
        'meta_description',
    ];

    protected $casts = [
        'name' => 'array',
        'description' => 'array',
        'meta' => 'array',
        'meta_title' => 'array',
        'meta_description' => 'array',
        'is_active' => 'boolean',
        'is_visible' => 'boolean',
        'sort_order' => 'integer',
    ];

    // Relationships

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(ShopCategory::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(ShopCategory::class, 'parent_id')->orderBy('sort_order');
    }

    public function products(): HasMany
    {
        return $this->hasMany(ShopProduct::class, 'category_id');
    }

    // Scopes

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeRoot(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order');
    }

    // Image URL Accessor

    /**
     * Get image_url as a full storage URL
     */
    public function getImageUrlAttribute(): ?string
    {
        $value = $this->attributes['image_url'] ?? null;
        if (!$value) {
            return null;
        }
        // If it's already a full URL, return as-is
        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }
        // Convert storage path to URL
        return Storage::disk('public')->url($value);
    }

    // Helpers

    public function getFullPath(): string
    {
        $path = [$this->getTranslation('name', app()->getLocale())];
        $parent = $this->parent;

        while ($parent) {
            array_unshift($path, $parent->getTranslation('name', app()->getLocale()));
            $parent = $parent->parent;
        }

        return implode(' > ', $path);
    }

    public function hasChildren(): bool
    {
        return $this->children()->exists();
    }

    public function getActiveProductCount(): int
    {
        return $this->products()->where('status', 'active')->count();
    }
}
