<?php

namespace App\Models;

use App\Support\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An extra on a product (life jacket, photo pack…). For every unit of the
 * product bought, `included_qty` come free and up to `max_per_unit` more can be
 * bought at `price_cents` each.
 */
class ActivityAddon extends Model
{
    use Translatable;

    public array $translatable = ['name'];

    protected $fillable = [
        'activity_id',
        'name',
        'price_cents',
        'included_qty',
        'max_per_unit',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'name' => 'array',
        'price_cents' => 'integer',
        'included_qty' => 'integer',
        'max_per_unit' => 'integer',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }
}
