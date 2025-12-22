<?php

namespace App\Models\Tax;

use App\Models\Tenant;
use App\Models\EventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Carbon\Carbon;

class GeneralTax extends Model
{
    use LogsActivity, SoftDeletes;

    protected $table = 'general_taxes';

    protected $fillable = [
        'tenant_id',
        'event_type_id',
        'name',
        'value',
        'value_type',
        'currency',
        'explanation',
        'priority',
        'valid_from',
        'valid_until',
        'is_active',
    ];

    protected $casts = [
        'value' => 'decimal:4',
        'priority' => 'integer',
        'valid_from' => 'date',
        'valid_until' => 'date',
        'is_active' => 'boolean',
    ];

    // Activity Log Configuration
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'name', 'value', 'value_type', 'currency', 'event_type_id',
                'explanation', 'priority', 'valid_from', 'valid_until', 'is_active'
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    // Relationships

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function eventType(): BelongsTo
    {
        return $this->belongsTo(EventType::class);
    }

    // Scopes

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeForEventType(Builder $query, ?int $eventTypeId): Builder
    {
        if ($eventTypeId) {
            return $query->where(function ($q) use ($eventTypeId) {
                $q->where('event_type_id', $eventTypeId)
                  ->orWhereNull('event_type_id'); // Include global taxes
            });
        }
        return $query->whereNull('event_type_id');
    }

    public function scopeValidOn(Builder $query, ?Carbon $date = null): Builder
    {
        $date = $date ?? Carbon::today();

        return $query->where(function ($q) use ($date) {
            $q->where(function ($inner) use ($date) {
                // Has valid_from and it's before or equal to date
                $inner->whereNotNull('valid_from')
                      ->where('valid_from', '<=', $date);
            })->orWhereNull('valid_from');
        })->where(function ($q) use ($date) {
            $q->where(function ($inner) use ($date) {
                // Has valid_until and it's after or equal to date
                $inner->whereNotNull('valid_until')
                      ->where('valid_until', '>=', $date);
            })->orWhereNull('valid_until');
        });
    }

    public function scopeApplicable(Builder $query, int $tenantId, ?int $eventTypeId = null, ?Carbon $date = null): Builder
    {
        return $query->forTenant($tenantId)
            ->active()
            ->forEventType($eventTypeId)
            ->validOn($date)
            ->orderByDesc('priority');
    }

    public function scopeByPriority(Builder $query): Builder
    {
        return $query->orderByDesc('priority');
    }

    // Helpers

    public function isPercent(): bool
    {
        return $this->value_type === 'percent';
    }

    public function isFixed(): bool
    {
        return $this->value_type === 'fixed';
    }

    public function isValidOn(?Carbon $date = null): bool
    {
        $date = $date ?? Carbon::today();

        if ($this->valid_from && $date->lt($this->valid_from)) {
            return false;
        }

        if ($this->valid_until && $date->gt($this->valid_until)) {
            return false;
        }

        return true;
    }

    public function isCurrentlyValid(): bool
    {
        return $this->is_active && $this->isValidOn();
    }

    public function calculateTax(float $amount): float
    {
        if ($this->isPercent()) {
            return $amount * ($this->value / 100);
        }
        return (float) $this->value;
    }

    public function getFormattedValue(): string
    {
        if ($this->isPercent()) {
            return number_format($this->value, 2) . '%';
        }
        $currency = $this->currency ?? '';
        return number_format($this->value, 2) . ($currency ? " {$currency}" : '');
    }

    public function getValidityStatus(): string
    {
        if (!$this->is_active) {
            return 'inactive';
        }

        $today = Carbon::today();

        if ($this->valid_from && $today->lt($this->valid_from)) {
            return 'scheduled';
        }

        if ($this->valid_until && $today->gt($this->valid_until)) {
            return 'expired';
        }

        return 'active';
    }

    public function getValidityPeriod(): ?string
    {
        if (!$this->valid_from && !$this->valid_until) {
            return null;
        }

        $from = $this->valid_from ? $this->valid_from->format('M d, Y') : 'Always';
        $until = $this->valid_until ? $this->valid_until->format('M d, Y') : 'Forever';

        return "{$from} - {$until}";
    }
}
