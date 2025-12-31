<?php

namespace App\Models\FeatureStore;

use App\Models\Platform\CoreCustomer;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FsPersonActivityPattern extends Model
{
    protected $table = 'fs_person_activity_pattern';

    protected $fillable = [
        'tenant_id',
        'person_id',
        'hourly_views',
        'hourly_purchases',
        'preferred_hour',
        'daily_views',
        'daily_purchases',
        'preferred_day',
        'peak_hours',
        'peak_days',
        'weekend_ratio',
        'is_weekend_buyer',
    ];

    protected $casts = [
        'hourly_views' => 'array',
        'hourly_purchases' => 'array',
        'preferred_hour' => 'integer',
        'daily_views' => 'array',
        'daily_purchases' => 'array',
        'preferred_day' => 'integer',
        'peak_hours' => 'array',
        'peak_days' => 'array',
        'weekend_ratio' => 'float',
        'is_weekend_buyer' => 'boolean',
    ];

    /**
     * Day labels.
     */
    public const DAYS = [
        0 => 'Sunday',
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
    ];

    /**
     * Time of day labels.
     */
    public const TIME_LABELS = [
        'early_morning' => [5, 8],   // 5-8 AM
        'morning' => [9, 11],        // 9-11 AM
        'midday' => [12, 14],        // 12-2 PM
        'afternoon' => [15, 17],     // 3-5 PM
        'evening' => [18, 21],       // 6-9 PM
        'night' => [22, 4],          // 10 PM - 4 AM
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

    public function scopeWeekendBuyers($query)
    {
        return $query->where('is_weekend_buyer', true);
    }

    public function scopeWithPreferredHour($query, int $hour)
    {
        return $query->where('preferred_hour', $hour);
    }

    public function scopeActiveInHourRange($query, int $startHour, int $endHour)
    {
        return $query->whereRaw(
            "preferred_hour BETWEEN ? AND ?",
            [$startHour, $endHour]
        );
    }

    // Helpers

    /**
     * Get the time of day label for preferred hour.
     */
    public function getTimeOfDayLabel(): ?string
    {
        if ($this->preferred_hour === null) {
            return null;
        }

        $hour = $this->preferred_hour;

        foreach (self::TIME_LABELS as $label => $range) {
            if ($range[0] <= $range[1]) {
                if ($hour >= $range[0] && $hour <= $range[1]) {
                    return $label;
                }
            } else {
                // Handles overnight range (night: 22-4)
                if ($hour >= $range[0] || $hour <= $range[1]) {
                    return $label;
                }
            }
        }

        return null;
    }

    /**
     * Get preferred day name.
     */
    public function getPreferredDayName(): ?string
    {
        return self::DAYS[$this->preferred_day] ?? null;
    }

    /**
     * Check if user is active at a given hour.
     */
    public function isActiveAtHour(int $hour): bool
    {
        $peakHours = $this->peak_hours ?? [];
        return in_array($hour, $peakHours);
    }

    /**
     * Get optimal notification time for this user.
     */
    public function getOptimalNotificationTime(): ?int
    {
        // Return preferred purchase hour, or if no purchases, preferred view hour
        if ($this->hourly_purchases) {
            $max = 0;
            $bestHour = null;
            foreach ($this->hourly_purchases as $hour => $count) {
                if ($count > $max) {
                    $max = $count;
                    $bestHour = (int) $hour;
                }
            }
            if ($bestHour !== null) {
                return $bestHour;
            }
        }

        return $this->preferred_hour;
    }

    // Static helpers

    /**
     * Get activity profile for a person.
     */
    public static function getProfile(int $tenantId, int $personId): ?array
    {
        $pattern = static::forTenant($tenantId)->forPerson($personId)->first();

        if (!$pattern) {
            return null;
        }

        return [
            'preferred_hour' => $pattern->preferred_hour,
            'preferred_hour_label' => $pattern->preferred_hour !== null
                ? sprintf('%02d:00', $pattern->preferred_hour)
                : null,
            'time_of_day' => $pattern->getTimeOfDayLabel(),
            'preferred_day' => $pattern->preferred_day,
            'preferred_day_name' => $pattern->getPreferredDayName(),
            'peak_hours' => $pattern->peak_hours,
            'peak_days' => $pattern->peak_days,
            'is_weekend_buyer' => $pattern->is_weekend_buyer,
            'weekend_ratio' => $pattern->weekend_ratio,
            'optimal_notification_hour' => $pattern->getOptimalNotificationTime(),
        ];
    }

    /**
     * Find users with matching activity patterns.
     */
    public static function findByActivityWindow(
        int $tenantId,
        ?int $hour = null,
        ?int $day = null,
        ?bool $weekendBuyer = null
    ): array {
        $query = static::forTenant($tenantId);

        if ($hour !== null) {
            $query->where('preferred_hour', $hour);
        }

        if ($day !== null) {
            $query->where('preferred_day', $day);
        }

        if ($weekendBuyer !== null) {
            $query->where('is_weekend_buyer', $weekendBuyer);
        }

        return $query->pluck('person_id')->toArray();
    }
}
