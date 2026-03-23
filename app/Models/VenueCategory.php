<?php

namespace App\Models;

use App\Support\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VenueCategory extends Model
{
    use Translatable;

    protected $table = 'venue_categories';

    public array $translatable = ['name'];

    protected $fillable = [
        'name',
        'slug',
        'icon',
        'sort_order',
    ];

    protected $casts = [
        'name' => 'array',
        'sort_order' => 'integer',
    ];

    public function venueTypes(): HasMany
    {
        return $this->hasMany(VenueType::class)->orderBy('sort_order');
    }

    /**
     * Route key uses 'id' (default) for Filament admin compatibility.
     * For public routes that need slug-based URLs, use explicit route model binding.
     */
}
