<?php

namespace App\Models\FeatureStore;

use App\Models\Platform\CoreCustomer;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FsPersonAntiAffinityArtist extends Model
{
    protected $table = 'fs_person_anti_affinity_artist';

    protected $fillable = [
        'tenant_id',
        'person_id',
        'artist_id',
        'bounce_count',
        'view_count',
        'anti_affinity_score',
        'avg_time_on_page_ms',
        'last_bounce_at',
    ];

    protected $casts = [
        'bounce_count' => 'integer',
        'view_count' => 'integer',
        'anti_affinity_score' => 'float',
        'avg_time_on_page_ms' => 'integer',
        'last_bounce_at' => 'datetime',
    ];

    /**
     * Anti-affinity thresholds.
     */
    public const BOUNCE_TIME_THRESHOLD_MS = 5000; // Less than 5 seconds = bounce
    public const HIGH_ANTI_AFFINITY_SCORE = 0.6; // 60%+ bounce rate

    // Relationships

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(CoreCustomer::class, 'person_id');
    }

    // Scopes

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeForPerson($query, int $personId)
    {
        return $query->where('person_id', $personId);
    }

    public function scopeForArtist($query, int $artistId)
    {
        return $query->where('artist_id', $artistId);
    }

    public function scopeHighAntiAffinity($query, float $threshold = 0.5)
    {
        return $query->where('anti_affinity_score', '>=', $threshold)
            ->where('view_count', '>=', 2); // Minimum views for significance
    }

    // Helpers

    /**
     * Record a view with time on page.
     */
    public static function recordView(
        int $tenantId,
        int $personId,
        int $artistId,
        int $timeOnPageMs
    ): self {
        $record = static::firstOrNew([
            'tenant_id' => $tenantId,
            'person_id' => $personId,
            'artist_id' => $artistId,
        ]);

        $record->view_count++;

        // Calculate running average time on page
        if ($record->avg_time_on_page_ms) {
            $record->avg_time_on_page_ms = (int) (
                ($record->avg_time_on_page_ms * ($record->view_count - 1) + $timeOnPageMs)
                / $record->view_count
            );
        } else {
            $record->avg_time_on_page_ms = $timeOnPageMs;
        }

        // Determine if this was a bounce
        if ($timeOnPageMs < self::BOUNCE_TIME_THRESHOLD_MS) {
            $record->bounce_count++;
            $record->last_bounce_at = now();
        }

        // Calculate anti-affinity score
        $record->anti_affinity_score = $record->view_count > 0
            ? $record->bounce_count / $record->view_count
            : 0;

        $record->save();

        return $record;
    }

    /**
     * Get artists to exclude for a person.
     */
    public static function getExcludedArtists(int $tenantId, int $personId, float $threshold = 0.5): array
    {
        return static::forTenant($tenantId)
            ->forPerson($personId)
            ->highAntiAffinity($threshold)
            ->pluck('artist_id')
            ->toArray();
    }

    /**
     * Check if person has anti-affinity for artist.
     */
    public static function hasAntiAffinity(int $tenantId, int $personId, int $artistId): bool
    {
        return static::forTenant($tenantId)
            ->forPerson($personId)
            ->forArtist($artistId)
            ->where('anti_affinity_score', '>=', self::HIGH_ANTI_AFFINITY_SCORE)
            ->exists();
    }
}
