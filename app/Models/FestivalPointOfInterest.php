<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FestivalPointOfInterest extends Model
{
    protected $table = 'festival_points_of_interest';

    protected $fillable = [
        'festival_map_id',
        'tenant_id',
        'stage_id',
        'name',
        'category',
        'icon',
        'description',
        'lat',
        'lng',
        'pixel_position',
        'status',
        'operating_hours',
        'meta',
    ];

    protected $casts = [
        'lat'             => 'decimal:7',
        'lng'             => 'decimal:7',
        'pixel_position'  => 'array',
        'operating_hours' => 'array',
        'meta'            => 'array',
    ];

    public function festivalMap(): BelongsTo
    {
        return $this->belongsTo(FestivalMap::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }

    public static function categoryLabels(): array
    {
        return [
            'stage'            => 'Stage',
            'food'             => 'Food',
            'drink'            => 'Bar / Drink',
            'toilet'           => 'Toilets',
            'first_aid'        => 'First Aid',
            'info'             => 'Info Point',
            'atm'              => 'ATM',
            'camping'          => 'Camping',
            'parking'          => 'Parking',
            'entrance'         => 'Entrance',
            'exit'             => 'Exit',
            'shower'           => 'Shower',
            'charging'         => 'Phone Charging',
            'merch'            => 'Merchandise',
            'vip'              => 'VIP Area',
            'chill'            => 'Chill Zone',
            'art_installation' => 'Art Installation',
            'workshop'         => 'Workshop Area',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }
}
