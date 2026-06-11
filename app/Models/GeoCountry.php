<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Centralized country reference (global, not per-marketplace).
 * See migration 2026_05_27_120000_create_geo_tables.
 */
class GeoCountry extends Model
{
    protected $table = 'geo_countries';

    protected $fillable = [
        'iso2', 'iso3', 'name_native', 'name_en', 'phone_code', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function counties(): HasMany
    {
        return $this->hasMany(GeoCounty::class, 'country_id');
    }

    public function localities(): HasMany
    {
        return $this->hasMany(GeoLocality::class, 'country_id');
    }
}
