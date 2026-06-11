<?php

namespace App\Models;

use App\Support\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VenueType extends Model
{
    use Translatable;

    protected $table = 'venue_types';

    public array $translatable = ['name', 'description'];

    protected $fillable = [
        'venue_category_id',
        'name',
        'slug',
        'icon',
        'description',
        'sort_order',
    ];

    protected $casts = [
        'name' => 'array',
        'description' => 'array',
        'sort_order' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(VenueCategory::class, 'venue_category_id');
    }

    public function venues(): HasMany
    {
        return $this->hasMany(Venue::class);
    }
}
