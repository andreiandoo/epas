<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Snapshot din tics.ro: celelalte platforme pe care se vinde un eveniment al nostru.
 * Scris de App\Services\Tics\TicsListingsService.
 */
class TicsEventListing extends Model
{
    protected $fillable = [
        'event_id',
        'marketplace_client_id',
        'found',
        'tics_event_id',
        'tics_url',
        'match_confidence',
        'platforms',
        'other_active_count',
        'synced_at',
    ];

    protected $casts = [
        'found' => 'boolean',
        'match_confidence' => 'float',
        'platforms' => 'array',
        'other_active_count' => 'integer',
        'synced_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** Doar listările active de pe alte platforme. */
    public function activePlatforms(): array
    {
        return array_values(array_filter($this->platforms ?? [], fn ($p) => ($p['status'] ?? null) === 'active'));
    }
}
