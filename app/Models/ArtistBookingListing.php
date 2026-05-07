<?php

namespace App\Models;

use App\Support\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArtistBookingListing extends Model
{
    use Translatable;

    public array $translatable = ['description'];

    protected $fillable = [
        'artist_id',
        'min_fee_ron',
        'max_fee_ron',
        'show_fee_publicly',
        'event_types',
        'accepted_genres',
        'standard_set_length_min',
        'standard_min_audience',
        'standard_max_audience',
        'requires_soundcheck',
        'soundcheck_min_minutes',
        'requires_backline',
        'requires_catering',
        'requires_accommodation',
        'requires_transport',
        'description',
        'max_distance_km',
        'response_target_hours',
        'status',
    ];

    protected $casts = [
        'show_fee_publicly' => 'boolean',
        'event_types' => 'array',
        'accepted_genres' => 'array',
        'requires_soundcheck' => 'boolean',
        'requires_backline' => 'boolean',
        'requires_catering' => 'boolean',
        'requires_accommodation' => 'boolean',
        'requires_transport' => 'boolean',
        'description' => 'array',
    ];

    public function artist(): BelongsTo
    {
        return $this->belongsTo(Artist::class);
    }
}
