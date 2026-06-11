<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class EventReportSchedule extends Model
{
    protected $fillable = [
        'event_id',
        'marketplace_organizer_id',
        'frequency',
        'day_of_week',
        'day_of_month',
        'send_at',
        'timezone',
        'recipients',
        'sections',
        'format',
        'include_comparison',
        'is_active',
        'last_sent_at',
        'next_send_at',
    ];

    protected $casts = [
        'day_of_week' => 'integer',
        'day_of_month' => 'integer',
        'recipients' => 'array',
        'sections' => 'array',
        'include_comparison' => 'boolean',
        'is_active' => 'boolean',
        'last_sent_at' => 'datetime',
        'next_send_at' => 'datetime',
    ];

    // Frequency constants
    const FREQ_DAILY = 'daily';
    const FREQ_WEEKLY = 'weekly';
    const FREQ_MONTHLY = 'monthly';

    // Format constants
    const FORMAT_EMAIL = 'email';
    const FORMAT_PDF = 'pdf';
    const FORMAT_CSV = 'csv';

    // Default sections
    const DEFAULT_SECTIONS = [
        'overview',
        'chart',
        'traffic',
        'milestones',
        'goals',
        'top_locations',
        'recent_sales',
    ];

    // Day names for weekly
    const DAY_NAMES = [
        0 => 'Sunday',
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(MarketplaceOrganizer::class, 'marketplace_organizer_id');
    }

    /**
     * Get frequency label
     */
    public function getFrequencyLabelAttribute(): string
    {
        return match ($this->frequency) {
            self::FREQ_DAILY => 'Daily',
            self::FREQ_WEEKLY => 'Weekly on ' . (self::DAY_NAMES[$this->day_of_week] ?? 'Monday'),
            self::FREQ_MONTHLY => 'Monthly on day ' . ($this->day_of_month ?? 1),
            default => ucfirst($this->frequency),
        };
    }

    /**
     * Get schedule description
     */
    public function getScheduleDescriptionAttribute(): string
    {
        $time = Carbon::parse($this->send_at)->format('H:i');
        return "{$this->frequency_label} at {$time} ({$this->timezone})";
    }

    /**
     * Calculate next send time
     */
    public function calculateNextSendAt(): Carbon
    {
        $now = now($this->timezone);
        $sendTime = Carbon::parse($this->send_at, $this->timezone);

        $next = match ($this->frequency) {
            self::FREQ_DAILY => $now->copy()->setTimeFrom($sendTime),
            self::FREQ_WEEKLY => $now->copy()->next(self::DAY_NAMES[$this->day_of_week ?? 1])->setTimeFrom($sendTime),
            self::FREQ_MONTHLY => $now->copy()->setDay($this->day_of_month ?? 1)->setTimeFrom($sendTime),
            default => $now->copy()->addDay()->setTimeFrom($sendTime),
        };

        // If calculated time is in the past, move to next occurrence
        if ($next->lte($now)) {
            $next = match ($this->frequency) {
                self::FREQ_DAILY => $next->addDay(),
                self::FREQ_WEEKLY => $next->addWeek(),
                self::FREQ_MONTHLY => $next->addMonth(),
                default => $next->addDay(),
            };
        }

        return $next->setTimezone('UTC');
    }

    /**
     * Update next send time
     */
    public function scheduleNext(): self
    {
        $this->next_send_at = $this->calculateNextSendAt();
        $this->save();
        return $this;
    }

    /**
     * Mark as sent
     */
    public function markSent(): self
    {
        $this->last_sent_at = now();
        $this->scheduleNext();
        return $this;
    }

    /**
     * Get period for report based on frequency
     */
    public function getReportPeriod(): array
    {
        $end = now()->subDay()->endOfDay();

        $start = match ($this->frequency) {
            self::FREQ_DAILY => $end->copy()->startOfDay(),
            self::FREQ_WEEKLY => $end->copy()->subDays(6)->startOfDay(),
            self::FREQ_MONTHLY => $end->copy()->subDays(29)->startOfDay(),
            default => $end->copy()->subDays(6)->startOfDay(),
        };

        return ['start' => $start, 'end' => $end];
    }

    /**
     * Get comparison period
     */
    public function getComparisonPeriod(): array
    {
        $period = $this->getReportPeriod();
        $days = $period['start']->diffInDays($period['end']) + 1;

        return [
            'start' => $period['start']->copy()->subDays($days),
            'end' => $period['start']->copy()->subDay(),
        ];
    }

    /**
     * Scope for active schedules
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for due reports
     */
    public function scopeDue($query)
    {
        return $query->active()
            ->where('next_send_at', '<=', now());
    }

    /**
     * Scope for event
     */
    public function scopeForEvent($query, int $eventId)
    {
        return $query->where('event_id', $eventId);
    }
}
