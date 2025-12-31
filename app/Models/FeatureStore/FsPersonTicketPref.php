<?php

namespace App\Models\FeatureStore;

use App\Models\Platform\CoreCustomer;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FsPersonTicketPref extends Model
{
    protected $table = 'fs_person_ticket_pref';

    protected $fillable = [
        'tenant_id',
        'person_id',
        'ticket_category',
        'purchases_count',
        'avg_price',
        'preference_score',
        'price_band',
    ];

    protected $casts = [
        'purchases_count' => 'integer',
        'avg_price' => 'decimal:2',
        'preference_score' => 'decimal:4',
    ];

    /**
     * Standard ticket categories.
     */
    public const CATEGORIES = [
        'GA' => 'General Admission',
        'VIP' => 'VIP',
        'Premium' => 'Premium',
        'EarlyBird' => 'Early Bird',
        'Student' => 'Student',
        'Group' => 'Group',
        'Family' => 'Family',
        'Standing' => 'Standing',
        'Seated' => 'Seated',
    ];

    /**
     * Price bands.
     */
    public const PRICE_BANDS = [
        'low' => [0, 30],
        'mid' => [30, 75],
        'high' => [75, 150],
        'premium' => [150, PHP_INT_MAX],
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

    public function scopeForCategory($query, string $category)
    {
        return $query->where('ticket_category', $category);
    }

    public function scopeForPriceBand($query, string $band)
    {
        return $query->where('price_band', $band);
    }

    public function scopeTopPreferences($query, int $limit = 5)
    {
        return $query->orderByDesc('preference_score')->limit($limit);
    }

    // Static helpers

    /**
     * Determine price band from price.
     */
    public static function determinePriceBand(float $price): string
    {
        foreach (self::PRICE_BANDS as $band => $range) {
            if ($price >= $range[0] && $price < $range[1]) {
                return $band;
            }
        }
        return 'premium';
    }

    /**
     * Get preferred categories for a person.
     */
    public static function getPreferences(int $tenantId, int $personId): array
    {
        return static::forTenant($tenantId)
            ->forPerson($personId)
            ->topPreferences()
            ->get()
            ->map(fn($pref) => [
                'category' => $pref->ticket_category,
                'purchases_count' => $pref->purchases_count,
                'avg_price' => $pref->avg_price,
                'preference_score' => $pref->preference_score,
                'price_band' => $pref->price_band,
            ])
            ->toArray();
    }

    /**
     * Calculate price fit score (0-1) for a person and target price.
     */
    public static function calculatePriceFit(int $tenantId, int $personId, float $targetPrice): float
    {
        $avgPrice = static::forTenant($tenantId)
            ->forPerson($personId)
            ->avg('avg_price');

        if ($avgPrice === null) {
            return 0.5; // No data, neutral score
        }

        // Price fit decays as difference increases
        $difference = abs($avgPrice - $targetPrice);
        return max(0, 1 - ($difference / 100));
    }

    /**
     * Get dominant price band for a person.
     */
    public static function getDominantPriceBand(int $tenantId, int $personId): ?string
    {
        return static::forTenant($tenantId)
            ->forPerson($personId)
            ->orderByDesc('purchases_count')
            ->value('price_band');
    }
}
