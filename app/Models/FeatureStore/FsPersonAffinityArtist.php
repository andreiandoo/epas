<?php

namespace App\Models\FeatureStore;

use App\Models\Artist;
use App\Models\Platform\CoreCustomer;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FsPersonAffinityArtist extends Model
{
    protected $table = 'fs_person_affinity_artist';

    protected $fillable = [
        'tenant_id',
        'person_id',
        'artist_id',
        'affinity_score',
        'views_count',
        'purchases_count',
        'attendance_count',
        'last_interaction_at',
    ];

    protected $casts = [
        'affinity_score' => 'decimal:4',
        'views_count' => 'integer',
        'purchases_count' => 'integer',
        'attendance_count' => 'integer',
        'last_interaction_at' => 'datetime',
    ];

    /**
     * Decay constant for recency-weighted scoring (days).
     */
    public const DECAY_TAU = 60;

    /**
     * Base points for different event types.
     */
    public const EVENT_WEIGHTS = [
        'event_view' => 1,
        'add_to_cart' => 3,
        'checkout_started' => 5,
        'order_completed' => 10,
        'entry_granted' => 12,
    ];

    // Relationships

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(CoreCustomer::class, 'person_id');
    }

    public function artist(): BelongsTo
    {
        return $this->belongsTo(Artist::class);
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

    public function scopeHighAffinity($query, float $threshold = 10.0)
    {
        return $query->where('affinity_score', '>=', $threshold);
    }

    public function scopeTopN($query, int $n = 10)
    {
        return $query->orderByDesc('affinity_score')->limit($n);
    }

    public function scopeWithPurchases($query)
    {
        return $query->where('purchases_count', '>', 0);
    }

    public function scopeWithAttendance($query)
    {
        return $query->where('attendance_count', '>', 0);
    }

    public function scopeActiveRecently($query, int $days = 90)
    {
        return $query->where('last_interaction_at', '>=', now()->subDays($days));
    }

    // Calculations

    /**
     * Calculate recency-weighted score contribution.
     */
    public static function calculateDecayedWeight(string $eventType, int $daysSince): float
    {
        $baseWeight = self::EVENT_WEIGHTS[$eventType] ?? 0;
        return $baseWeight * exp(-$daysSince / self::DECAY_TAU);
    }

    /**
     * Get top artists for a person.
     */
    public static function getTopArtists(int $tenantId, int $personId, int $limit = 10): array
    {
        return static::forTenant($tenantId)
            ->forPerson($personId)
            ->with('artist:id,name,slug')
            ->topN($limit)
            ->get()
            ->map(fn($aff) => [
                'artist_id' => $aff->artist_id,
                'artist_name' => $aff->artist->name ?? null,
                'affinity_score' => $aff->affinity_score,
                'purchases_count' => $aff->purchases_count,
                'attendance_count' => $aff->attendance_count,
            ])
            ->toArray();
    }

    /**
     * Find users with affinity for specific artists.
     */
    public static function findUsersWithAffinity(
        int $tenantId,
        array $artistIds,
        float $minScore = 5.0
    ): array {
        return static::forTenant($tenantId)
            ->whereIn('artist_id', $artistIds)
            ->highAffinity($minScore)
            ->select('person_id')
            ->distinct()
            ->pluck('person_id')
            ->toArray();
    }
}
