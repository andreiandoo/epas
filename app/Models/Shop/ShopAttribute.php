<?php

namespace App\Models\Shop;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Translatable\HasTranslations;

class ShopAttribute extends Model
{
    use HasUuids, HasTranslations;

    protected $table = 'shop_attributes';

    public $translatable = ['name'];

    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'type',
        'is_visible',
        'sort_order',
    ];

    protected $casts = [
        'name' => 'array',
        'is_visible' => 'boolean',
        'sort_order' => 'integer',
    ];

    // Relationships

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function values(): HasMany
    {
        return $this->hasMany(ShopAttributeValue::class, 'attribute_id')->orderBy('sort_order');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(ShopProduct::class, 'shop_product_attribute', 'attribute_id', 'product_id');
    }

    // Scopes

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_visible', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order');
    }

    // Helpers

    public function isColorType(): bool
    {
        return $this->type === 'color';
    }

    public function isSelectType(): bool
    {
        return $this->type === 'select';
    }
}
