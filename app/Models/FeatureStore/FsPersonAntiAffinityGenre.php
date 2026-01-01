<?php

namespace App\Models\FeatureStore;

use App\Models\Platform\CoreCustomer;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FsPersonAntiAffinityGenre extends Model
{
    protected $table = 'fs_person_anti_affinity_genre';

    protected $fillable = [
        'tenant_id',
        'person_id',
        'genre',
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

    public const BOUNCE_TIME_THRESHOLD_MS = 5000;
    public const HIGH_ANTI_AFFINITY_SCORE = 0.6;

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

    public function scopeHighAntiAffinity($query, float $threshold = 0.5)
    {
        return $query->where('anti_affinity_score', '>=', $threshold)
            ->where('view_count', '>=', 2);
    }

    // Helpers

    public static function recordView(
        int $tenantId,
        int $personId,
        string $genre,
        int $timeOnPageMs
    ): self {
        $record = static::firstOrNew([
            'tenant_id' => $tenantId,
            'person_id' => $personId,
            'genre' => $genre,
        ]);

        $record->view_count++;

        if ($record->avg_time_on_page_ms) {
            $record->avg_time_on_page_ms = (int) (
                ($record->avg_time_on_page_ms * ($record->view_count - 1) + $timeOnPageMs)
                / $record->view_count
            );
        } else {
            $record->avg_time_on_page_ms = $timeOnPageMs;
        }

        if ($timeOnPageMs < self::BOUNCE_TIME_THRESHOLD_MS) {
            $record->bounce_count++;
            $record->last_bounce_at = now();
        }

        $record->anti_affinity_score = $record->view_count > 0
            ? $record->bounce_count / $record->view_count
            : 0;

        $record->save();

        return $record;
    }

    public static function getExcludedGenres(int $tenantId, int $personId, float $threshold = 0.5): array
    {
        return static::forTenant($tenantId)
            ->forPerson($personId)
            ->highAntiAffinity($threshold)
            ->pluck('genre')
            ->toArray();
    }
}
