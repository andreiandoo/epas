<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tour Optimizer (Modulul 3 din Extended Artist) — scenarii salvate de artist.
 *
 * Limită: 20 scenarii per artist (controlat in Controller).
 * Status: 'draft' | 'active'.
 */
class ArtistTourScenario extends Model
{
    public const MAX_PER_ARTIST = 20;

    protected $fillable = [
        'artist_id',
        'name',
        'start_date',
        'end_date',
        'cities',
        'constraints',
        'optimized_route',
        'summary',
        'status',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'cities' => 'array',
        'constraints' => 'array',
        'optimized_route' => 'array',
        'summary' => 'array',
    ];

    public function artist(): BelongsTo
    {
        return $this->belongsTo(Artist::class);
    }
}
