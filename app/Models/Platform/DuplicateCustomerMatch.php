<?php

namespace App\Models\Platform;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DuplicateCustomerMatch extends Model
{
    protected $table = 'duplicate_customer_matches';

    protected $fillable = [
        'customer_a_id',
        'customer_b_id',
        'match_score',
        'match_type',
        'confidence',
        'matched_fields',
        'is_resolved',
        'resolution',
        'resolved_at',
        'resolved_by',
    ];

    protected $casts = [
        'match_score' => 'decimal:3',
        'matched_fields' => 'array',
        'is_resolved' => 'boolean',
        'resolved_at' => 'datetime',
    ];

    // Match types
    const MATCH_EXACT = 'exact';
    const MATCH_HIGH = 'high';
    const MATCH_MEDIUM = 'medium';
    const MATCH_LOW = 'low';

    // Confidence levels
    const CONFIDENCE_DEFINITE = 'definite';
    const CONFIDENCE_LIKELY = 'likely';
    const CONFIDENCE_POSSIBLE = 'possible';

    // Resolution types
    const RESOLUTION_MERGED = 'merged';
    const RESOLUTION_DISMISSED = 'dismissed';
    const RESOLUTION_PENDING = 'pending';

    public function customerA(): BelongsTo
    {
        return $this->belongsTo(CoreCustomer::class, 'customer_a_id');
    }

    public function customerB(): BelongsTo
    {
        return $this->belongsTo(CoreCustomer::class, 'customer_b_id');
    }

    public function scopeUnresolved($query)
    {
        return $query->where('is_resolved', false);
    }

    public function scopeResolved($query)
    {
        return $query->where('is_resolved', true);
    }

    public function scopeHighConfidence($query, float $minScore = 0.9)
    {
        return $query->where('match_score', '>=', $minScore);
    }

    public function scopeByConfidence($query, string $confidence)
    {
        return $query->where('confidence', $confidence);
    }

    public static function findOrCreateMatch(
        int $customerAId,
        int $customerBId,
        float $matchScore,
        array $matchedFields
    ): self {
        // Always store with smaller ID first for consistency
        [$firstId, $secondId] = $customerAId < $customerBId
            ? [$customerAId, $customerBId]
            : [$customerBId, $customerAId];

        return self::firstOrCreate(
            [
                'customer_a_id' => $firstId,
                'customer_b_id' => $secondId,
            ],
            [
                'match_score' => $matchScore,
                'match_type' => self::getMatchType($matchScore),
                'confidence' => self::getConfidence($matchScore),
                'matched_fields' => $matchedFields,
                'is_resolved' => false,
            ]
        );
    }

    public static function getMatchType(float $score): string
    {
        return match (true) {
            $score >= 0.95 => self::MATCH_EXACT,
            $score >= 0.85 => self::MATCH_HIGH,
            $score >= 0.70 => self::MATCH_MEDIUM,
            default => self::MATCH_LOW,
        };
    }

    public static function getConfidence(float $score): string
    {
        return match (true) {
            $score >= 0.95 => self::CONFIDENCE_DEFINITE,
            $score >= 0.80 => self::CONFIDENCE_LIKELY,
            default => self::CONFIDENCE_POSSIBLE,
        };
    }

    public function resolve(string $resolution, ?string $resolvedBy = null): void
    {
        $this->update([
            'is_resolved' => true,
            'resolution' => $resolution,
            'resolved_at' => now(),
            'resolved_by' => $resolvedBy,
        ]);
    }
}
