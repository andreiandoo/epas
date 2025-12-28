<?php

namespace App\Models\Seating;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SeatingSection extends Model
{
    protected $fillable = [
        'layout_id',
        'tenant_id',
        'name',
        'section_code',
        'price_tier_id',
        'section_type',
        'x_position',
        'y_position',
        'width',
        'height',
        'rotation',
        'display_order',
        'color_hex',
        'background_color',
        'corner_radius',
        'background_image',
        'meta',
        'metadata',
    ];

    protected $casts = [
        'meta' => 'array',
        'metadata' => 'array',
        'x_position' => 'integer',
        'y_position' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'rotation' => 'integer',
        'display_order' => 'integer',
        'corner_radius' => 'integer',
    ];

    protected $attributes = [
        'color_hex' => '#3B82F6',
        'section_type' => 'standard',
        'x_position' => 100,
        'y_position' => 100,
        'width' => 200,
        'height' => 150,
        'rotation' => 0,
        'display_order' => 0,
        'corner_radius' => 0,
    ];

    /**
     * Relationships
     */
    public function layout(): BelongsTo
    {
        return $this->belongsTo(SeatingLayout::class, 'layout_id');
    }

    public function rows(): HasMany
    {
        return $this->hasMany(SeatingRow::class, 'section_id');
    }
}
