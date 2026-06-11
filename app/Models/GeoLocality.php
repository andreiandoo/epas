<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Settlement (RO: municipiu/oraș/comună/sat). Global reference.
 * See migration 2026_05_27_120000_create_geo_tables.
 */
class GeoLocality extends Model
{
    protected $table = 'geo_localities';

    protected $fillable = [
        'country_id', 'county_id', 'name_native', 'name_ascii', 'slug',
        'type', 'latitude', 'longitude', 'sort_order',
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

    public function county(): BelongsTo
    {
        return $this->belongsTo(GeoCounty::class, 'county_id');
    }
}
