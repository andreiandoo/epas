<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * First-level subdivision (RO: județ). Global reference.
 * See migration 2026_05_27_120000_create_geo_tables.
 */
class GeoCounty extends Model
{
    protected $table = 'geo_counties';

    protected $fillable = [
        'country_id', 'code', 'name_native', 'name_ascii', 'slug',
        'latitude', 'longitude', 'sort_order',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'sort_order' => 'integer',
    ];

    public function country(): BelongsTo
    {
        return $this->belongsTo(GeoCountry::class, 'country_id');
    }

    public function localities(): HasMany
    {
        return $this->hasMany(GeoLocality::class, 'county_id');
    }
}
