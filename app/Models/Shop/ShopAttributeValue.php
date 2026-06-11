<?php

namespace App\Models\Shop;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Support\Translatable;

class ShopAttributeValue extends Model
{
    use HasUuids, Translatable;

    protected $table = 'shop_attribute_values';

    public array $translatable = ['value'];

    protected $fillable = [
        'attribute_id',
        'value',
        'slug',
        'color_hex',
        'sort_order',
    ];

    protected $casts = [
        'value' => 'array',
        'sort_order' => 'integer',
    ];

    // Relationships

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(ShopAttribute::class, 'attribute_id');
    }

    public function variants(): BelongsToMany
    {
        return $this->belongsToMany(ShopProductVariant::class, 'shop_variant_attribute_value', 'attribute_value_id', 'variant_id');
    }

    // Helpers

    public function getDisplayLabel(): string
    {
        $locale = app()->getLocale();
        $label = $this->getTranslation('value', $locale);

        if ($this->attribute && $this->attribute->isColorType() && $this->color_hex) {
            return $label; // Frontend can use color_hex for display
        }

        return $label;
    }
}
