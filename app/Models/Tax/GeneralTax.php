<?php

namespace App\Models\Tax;

use App\Models\Tenant;
use App\Models\EventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class GeneralTax extends Model
{
    use LogsActivity;

    protected $table = 'general_taxes';

    protected $fillable = [
        'tenant_id',
        'event_type_id',
        'name',
        'value',
        'value_type',
        'explanation',
        'is_active',
    ];

    protected $casts = [
        'value' => 'decimal:4',
        'is_active' => 'boolean',
    ];

    // Activity Log Configuration
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'value', 'value_type', 'event_type_id', 'explanation', 'is_active'])
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
            return $query->where('event_type_id', $eventTypeId);
        }
        return $query->whereNull('event_type_id');
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
        return number_format($this->value, 2);
    }
}
