<?php

namespace App\Models\FeatureStore;

use App\Models\Platform\CoreCustomer;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FsPersonPurchaseWindow extends Model
{
    protected $table = 'fs_person_purchase_window';

    protected $fillable = [
        'tenant_id',
        'person_id',
        'window_type',
        'purchases_count',
        'avg_days_before_event',
        'preference_score',
    ];

    protected $casts = [
        'purchases_count' => 'integer',
        'avg_days_before_event' => 'float',
        'preference_score' => 'float',
    ];

    /**
     * Purchase window types.
     * Defines how far in advance of an event a user typically purchases.
     */
    public const WINDOW_TYPES = [
        'last_minute' => [0, 1],      // Same day or day before
        'week' => [2, 7],             // 2-7 days before
        'two_weeks' => [8, 14],       // 8-14 days before
        'month' => [15, 30],          // 15-30 days before
        'early_bird' => [31, 365],    // 31+ days before
    ];

    /**
     * Window labels for display.
     */
    public const WINDOW_LABELS = [
        'last_minute' => 'Last Minute (0-1 days)',
        'week' => 'Week Before (2-7 days)',
        'two_weeks' => 'Two Weeks (8-14 days)',
        'month' => 'Month Before (15-30 days)',
        'early_bird' => 'Early Bird (31+ days)',
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

    public function scopeForWindow($query, string $windowType)
    {
        return $query->where('window_type', $windowType);
    }

    public function scopeLastMinuteBuyers($query)
    {
        return $query->where('window_type', 'last_minute')
            ->where('preference_score', '>=', 0.4);
    }

    public function scopeEarlyBirdBuyers($query)
    {
        return $query->where('window_type', 'early_bird')
            ->where('preference_score', '>=', 0.4);
    }

    // Static helpers

    /**
     * Determine window type from days before event.
     */
    public static function determineWindowType(int $daysBefore): string
    {
        foreach (self::WINDOW_TYPES as $type => $range) {
            if ($daysBefore >= $range[0] && $daysBefore <= $range[1]) {
                return $type;
            }
        }
        return 'early_bird';
    }

    /**
     * Get preferred purchase window for a person.
     */
    public static function getPreferredWindow(int $tenantId, int $personId): ?string
    {
        return static::forTenant($tenantId)
            ->forPerson($personId)
            ->orderByDesc('preference_score')
            ->value('window_type');
    }

    /**
     * Get purchase window profile for a person.
     */
    public static function getProfile(int $tenantId, int $personId): array
    {
        $windows = static::forTenant($tenantId)
            ->forPerson($personId)
            ->orderByDesc('preference_score')
            ->get();

        if ($windows->isEmpty()) {
            return ['preferred_window' => null, 'windows' => []];
        }

        return [
            'preferred_window' => $windows->first()->window_type,
            'avg_days_before' => $windows->avg('avg_days_before_event'),
            'windows' => $windows->map(fn($w) => [
                'type' => $w->window_type,
                'label' => self::WINDOW_LABELS[$w->window_type] ?? $w->window_type,
                'purchases' => $w->purchases_count,
                'avg_days' => round($w->avg_days_before_event, 1),
                'preference_pct' => round($w->preference_score * 100, 1),
            ])->toArray(),
        ];
    }

    /**
     * Find users with specific window preference.
     */
    public static function findByWindowPreference(
        int $tenantId,
        string $windowType,
        float $minScore = 0.3
    ): array {
        return static::forTenant($tenantId)
            ->forWindow($windowType)
            ->where('preference_score', '>=', $minScore)
            ->pluck('person_id')
            ->toArray();
    }
}
