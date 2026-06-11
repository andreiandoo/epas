<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArtistFanSegment extends Model
{
    public const MAX_PER_ARTIST = 10;

    protected $fillable = [
        'artist_id',
        'name',
        'description',
        'criteria',
        'color',
    ];

    protected $casts = [
        'criteria' => 'array',
    ];

    public function artist(): BelongsTo
    {
        return $this->belongsTo(Artist::class);
    }

    /**
     * Validate criteria shape — returnează array sanitizat (drop câmpuri unknown).
     */
    public static function sanitizeCriteria(?array $criteria): array
    {
        if (!is_array($criteria)) {
            return [];
        }

        $clean = [];

        if (isset($criteria['events_min']) && is_numeric($criteria['events_min'])) {
            $clean['events_min'] = max(0, (int) $criteria['events_min']);
        }
        if (isset($criteria['events_max']) && is_numeric($criteria['events_max'])) {
            $clean['events_max'] = max(0, (int) $criteria['events_max']);
        }
        if (isset($criteria['spend_min']) && is_numeric($criteria['spend_min'])) {
            $clean['spend_min'] = max(0, (float) $criteria['spend_min']);
        }
        if (isset($criteria['spend_max']) && is_numeric($criteria['spend_max'])) {
            $clean['spend_max'] = max(0, (float) $criteria['spend_max']);
        }
        if (isset($criteria['cities']) && is_array($criteria['cities'])) {
            $clean['cities'] = array_values(array_filter(
                array_map(fn ($c) => is_string($c) ? trim($c) : null, $criteria['cities']),
                fn ($c) => $c !== null && $c !== ''
            ));
        }
        if (isset($criteria['last_event_after']) && is_string($criteria['last_event_after'])) {
            $clean['last_event_after'] = $criteria['last_event_after'];
        }
        if (isset($criteria['last_event_before']) && is_string($criteria['last_event_before'])) {
            $clean['last_event_before'] = $criteria['last_event_before'];
        }
        if (isset($criteria['genres']) && is_array($criteria['genres'])) {
            $clean['genres'] = array_values(array_filter(
                array_map(fn ($g) => is_numeric($g) ? (int) $g : null, $criteria['genres']),
                fn ($g) => $g !== null
            ));
        }

        return $clean;
    }
}
