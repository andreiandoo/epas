<?php

namespace App\Models\Platform;

use Illuminate\Database\Eloquent\Model;

class DataRetentionPolicy extends Model
{
    protected $fillable = [
        'data_type',
        'retention_days',
        'is_active',
        'archive_strategy',
        'last_cleanup_at',
        'last_cleanup_count',
    ];

    protected $casts = [
        'retention_days' => 'integer',
        'is_active' => 'boolean',
        'last_cleanup_at' => 'datetime',
        'last_cleanup_count' => 'integer',
    ];

    const STRATEGY_DELETE = 'delete';
    const STRATEGY_ARCHIVE = 'archive';
    const STRATEGY_ANONYMIZE = 'anonymize';

    const DATA_TYPE_SESSIONS = 'sessions';
    const DATA_TYPE_EVENTS = 'events';
    const DATA_TYPE_CONVERSIONS = 'conversions';
    const DATA_TYPE_PAGEVIEWS = 'pageviews';

    const STRATEGIES = [
        self::STRATEGY_DELETE => 'Delete',
        self::STRATEGY_ARCHIVE => 'Archive',
        self::STRATEGY_ANONYMIZE => 'Anonymize',
    ];

    // Alias for backward compatibility
    const ARCHIVE_STRATEGIES = self::STRATEGIES;

    const DATA_TYPES = [
        self::DATA_TYPE_SESSIONS => 'Sessions',
        self::DATA_TYPE_EVENTS => 'Events',
        self::DATA_TYPE_CONVERSIONS => 'Conversions',
        self::DATA_TYPE_PAGEVIEWS => 'Page Views',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForDataType($query, string $dataType)
    {
        return $query->where('data_type', $dataType);
    }

    public function getCutoffDate(): \DateTime
    {
        return now()->subDays($this->retention_days);
    }

    public function recordCleanup(int $count): void
    {
        $this->update([
            'last_cleanup_at' => now(),
            'last_cleanup_count' => $count,
        ]);
    }

    public static function getOrCreateDefault(string $dataType): self
    {
        return static::firstOrCreate(
            ['data_type' => $dataType],
            [
                'retention_days' => match ($dataType) {
                    self::DATA_TYPE_SESSIONS => 90,
                    self::DATA_TYPE_EVENTS => 365,
                    self::DATA_TYPE_CONVERSIONS => 730, // 2 years for conversions
                    self::DATA_TYPE_PAGEVIEWS => 180,
                    default => 365,
                },
                'is_active' => true,
                'archive_strategy' => self::STRATEGY_DELETE,
            ]
        );
    }
}
