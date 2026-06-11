<?php

namespace App\Models;

use App\Support\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

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
        'ical_token',
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

    /**
     * Returnează token-ul iCal, generându-l prima dată dacă lipsește.
     * Token-ul e folosit ca secret în URL-ul `/booking/ical/{token}.ics`.
     */
    public function ensureIcalToken(): string
    {
        if (!empty($this->ical_token)) {
            return $this->ical_token;
        }
        do {
            $token = Str::random(40);
        } while (static::where('ical_token', $token)->exists());
        $this->ical_token = $token;
        $this->save();
        return $token;
    }

    public function regenerateIcalToken(): string
    {
        $this->ical_token = null;
        return $this->ensureIcalToken();
    }
}
