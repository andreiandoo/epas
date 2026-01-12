<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventGoal extends Model
{
    protected $fillable = [
        'event_id',
        'type',
        'name',
        'target_value',
        'current_value',
        'progress_percent',
        'deadline',
        'alert_thresholds',
        'alerts_sent',
        'email_alerts',
        'in_app_alerts',
        'alert_email',
        'status',
        'achieved_at',
        'notes',
    ];

    protected $casts = [
        'target_value' => 'integer',
        'current_value' => 'integer',
        'progress_percent' => 'decimal:2',
        'deadline' => 'date',
        'alert_thresholds' => 'array',
        'alerts_sent' => 'array',
        'email_alerts' => 'boolean',
        'in_app_alerts' => 'boolean',
        'achieved_at' => 'datetime',
    ];

    // Goal types
    const TYPE_REVENUE = 'revenue';
    const TYPE_TICKETS = 'tickets';
    const TYPE_VISITORS = 'visitors';
    const TYPE_CONVERSION = 'conversion_rate';

    // Default alert thresholds
    const DEFAULT_THRESHOLDS = [25, 50, 75, 90, 100];

    // Status constants
    const STATUS_ACTIVE = 'active';
    const STATUS_ACHIEVED = 'achieved';
    const STATUS_MISSED = 'missed';
    const STATUS_CANCELLED = 'cancelled';

    // Type labels and icons
    const TYPE_CONFIG = [
        self::TYPE_REVENUE => [
            'label' => 'Revenue Goal',
            'icon' => 'currency-dollar',
            'color' => 'emerald',
            'unit' => 'currency',
        ],
        self::TYPE_TICKETS => [
            'label' => 'Tickets Goal',
            'icon' => 'ticket',
            'color' => 'blue',
            'unit' => 'count',
        ],
        self::TYPE_VISITORS => [
            'label' => 'Visitors Goal',
            'icon' => 'users',
            'color' => 'purple',
            'unit' => 'count',
        ],
        self::TYPE_CONVERSION => [
            'label' => 'Conversion Rate Goal',
            'icon' => 'chart-bar',
            'color' => 'amber',
            'unit' => 'percent',
        ],
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Get type label
     */
    public function getTypeLabelAttribute(): string
    {
        return self::TYPE_CONFIG[$this->type]['label'] ?? ucfirst($this->type);
    }

    /**
     * Get type icon
     */
    public function getTypeIconAttribute(): string
    {
        return self::TYPE_CONFIG[$this->type]['icon'] ?? 'flag';
    }

    /**
     * Get type color
     */
    public function getTypeColorAttribute(): string
    {
        return self::TYPE_CONFIG[$this->type]['color'] ?? 'gray';
    }

    /**
     * Get formatted target value
     */
    public function getFormattedTargetAttribute(): string
    {
        return $this->formatValue($this->target_value);
    }

    /**
     * Get formatted current value
     */
    public function getFormattedCurrentAttribute(): string
    {
        return $this->formatValue($this->current_value);
    }

    /**
     * Format value based on type
     */
    protected function formatValue(int $value): string
    {
        return match ($this->type) {
            self::TYPE_REVENUE => number_format($value / 100, 2) . ' ' . ($this->event?->currency ?? 'EUR'),
            self::TYPE_CONVERSION => number_format($value / 100, 2) . '%',
            default => number_format($value),
        };
    }

    /**
     * Check if goal is achieved
     */
    public function isAchieved(): bool
    {
        return $this->status === self::STATUS_ACHIEVED || $this->progress_percent >= 100;
    }

    /**
     * Check if goal is active
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Check if deadline has passed
     */
    public function isOverdue(): bool
    {
        return $this->deadline && $this->deadline->isPast() && !$this->isAchieved();
    }

    /**
     * Get remaining to target
     */
    public function getRemainingAttribute(): int
    {
        return max(0, $this->target_value - $this->current_value);
    }

    /**
     * Get days remaining until deadline
     */
    public function getDaysRemainingAttribute(): ?int
    {
        if (!$this->deadline) {
            return null;
        }
        return max(0, now()->diffInDays($this->deadline, false));
    }

    /**
     * Update progress from event data
     */
    public function updateProgress(): self
    {
        $event = $this->event;
        if (!$event) {
            return $this;
        }

        $this->current_value = match ($this->type) {
            self::TYPE_REVENUE => (int) ($event->total_revenue * 100), // Convert to cents
            self::TYPE_TICKETS => $event->total_tickets_sold,
            self::TYPE_VISITORS => $event->analyticsDaily()->sum('unique_visitors'),
            self::TYPE_CONVERSION => (int) ($event->analyticsDaily()->avg('conversion_rate') * 100),
            default => 0,
        };

        $this->progress_percent = $this->target_value > 0
            ? min(100, round(($this->current_value / $this->target_value) * 100, 2))
            : 0;

        // Check if achieved
        if ($this->progress_percent >= 100 && $this->status === self::STATUS_ACTIVE) {
            $this->status = self::STATUS_ACHIEVED;
            $this->achieved_at = now();
        }

        // Check if missed (deadline passed without achieving)
        if ($this->isOverdue() && $this->status === self::STATUS_ACTIVE) {
            $this->status = self::STATUS_MISSED;
        }

        $this->save();

        return $this;
    }

    /**
     * Get pending alert thresholds (not yet sent)
     */
    public function getPendingAlerts(): array
    {
        $thresholds = $this->alert_thresholds ?? self::DEFAULT_THRESHOLDS;
        $sent = $this->alerts_sent ?? [];

        return array_filter($thresholds, function ($threshold) use ($sent) {
            return $this->progress_percent >= $threshold && !in_array($threshold, $sent);
        });
    }

    /**
     * Mark alert as sent
     */
    public function markAlertSent(int $threshold): self
    {
        $sent = $this->alerts_sent ?? [];
        if (!in_array($threshold, $sent)) {
            $sent[] = $threshold;
            $this->alerts_sent = $sent;
            $this->save();
        }
        return $this;
    }

    /**
     * Get progress status class for UI
     */
    public function getProgressStatusAttribute(): string
    {
        if ($this->isAchieved()) {
            return 'success';
        }
        if ($this->isOverdue()) {
            return 'danger';
        }
        if ($this->progress_percent >= 75) {
            return 'success';
        }
        if ($this->progress_percent >= 50) {
            return 'warning';
        }
        return 'info';
    }

    /**
     * Scope for active goals
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope for event
     */
    public function scopeForEvent($query, int $eventId)
    {
        return $query->where('event_id', $eventId);
    }

    /**
     * Scope for goals needing alert check
     */
    public function scopeNeedingAlertCheck($query)
    {
        return $query->active()
            ->where(function ($q) {
                $q->where('email_alerts', true)
                    ->orWhere('in_app_alerts', true);
            });
    }
}
