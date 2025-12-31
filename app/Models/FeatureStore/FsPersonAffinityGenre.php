<?php

namespace App\Models\FeatureStore;

use App\Models\Platform\CoreCustomer;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FsPersonAffinityGenre extends Model
{
    protected $table = 'fs_person_affinity_genre';

    protected $fillable = [
        'tenant_id',
        'person_id',
        'genre',
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

    public function scopeForGenre($query, string $genre)
    {
        return $query->where('genre', $genre);
    }

    public function scopeForGenres($query, array $genres)
    {
        return $query->whereIn('genre', $genres);
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

    // Static helpers

    /**
     * Get top genres for a person.
     */
    public static function getTopGenres(int $tenantId, int $personId, int $limit = 10): array
    {
        return static::forTenant($tenantId)
            ->forPerson($personId)
            ->topN($limit)
            ->get()
            ->map(fn($aff) => [
                'genre' => $aff->genre,
                'affinity_score' => $aff->affinity_score,
                'purchases_count' => $aff->purchases_count,
                'attendance_count' => $aff->attendance_count,
            ])
            ->toArray();
    }

    /**
     * Find users with affinity for specific genres.
     */
    public static function findUsersWithAffinity(
        int $tenantId,
        array $genres,
        float $minScore = 5.0
    ): array {
        return static::forTenant($tenantId)
            ->forGenres($genres)
            ->highAffinity($minScore)
            ->select('person_id')
            ->distinct()
            ->pluck('person_id')
            ->toArray();
    }

    /**
     * Get average affinity score for genres.
     */
    public static function getAverageAffinity(int $tenantId, int $personId, array $genres): float
    {
        return static::forTenant($tenantId)
            ->forPerson($personId)
            ->forGenres($genres)
            ->avg('affinity_score') ?? 0;
    }
}
