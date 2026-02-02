<?php

namespace App\Models\Shop;

use App\Models\MarketplaceClient;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use App\Support\Translatable;

class ShopAttribute extends Model
{
    use HasUuids, Translatable;

    protected $table = 'shop_attributes';

    public array $translatable = ['name'];

    protected $fillable = [
        'marketplace_client_id',
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
    /**
     * Get the marketplace client that owns this record
     */
    public function marketplaceClient()
    {
        return $this->belongsTo(MarketplaceClient::class);
    }

}
