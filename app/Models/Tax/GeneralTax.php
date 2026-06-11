<?php

namespace App\Models\Tax;

use App\Models\Tenant;
use App\Models\EventType;
use App\Models\Tax\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Carbon\Carbon;

class GeneralTax extends Model
{
    use LogsActivity, SoftDeletes, Auditable;

    protected $table = 'general_taxes';

    protected $fillable = [
        'tenant_id',
        'name',
        'icon_svg',
        'value',
        'value_type',
        'currency',
        'explanation',
        'priority',
        'is_compound',
        'compound_order',
        'valid_from',
        'valid_until',
        'is_active',
        'visible_on_checkout',
        'visible_on_ticket',
        // Payment info
        'beneficiary',
        'iban',
        'beneficiary_address',
        'where_to_pay',
        // Payment terms
        'payment_term',
        'payment_term_day',
        'payment_term_days_after',
        'payment_term_type',
        // Legal & docs
        'legal_basis',
        'declaration',
        'before_event_instructions',
        'after_event_instructions',
        // Application rules
        'is_added_to_price',
        'applied_to_base',
        'has_tiered_rates',
        'tiered_rates',
        'min_revenue_threshold',
        'max_revenue_threshold',
        'min_guaranteed_amount',
    ];

    protected $casts = [
        'value' => 'decimal:4',
        'priority' => 'integer',
        'is_compound' => 'boolean',
        'compound_order' => 'integer',
        'valid_from' => 'date',
        'valid_until' => 'date',
        'is_active' => 'boolean',
        'visible_on_checkout' => 'boolean',
        'visible_on_ticket' => 'boolean',
        'is_added_to_price' => 'boolean',
        'has_tiered_rates' => 'boolean',
        'tiered_rates' => 'array',
        'payment_term_day' => 'integer',
        'payment_term_days_after' => 'integer',
        'min_revenue_threshold' => 'decimal:2',
        'max_revenue_threshold' => 'decimal:2',
        'min_guaranteed_amount' => 'decimal:2',
    ];

    // Activity Log Configuration
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'name', 'icon_svg', 'value', 'value_type', 'currency',
                'explanation', 'priority', 'is_compound', 'compound_order',
                'valid_from', 'valid_until', 'is_active'
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    // Relationships

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function eventTypes(): BelongsToMany
    {
        return $this->belongsToMany(EventType::class, 'general_tax_event_type')
            ->withTimestamps();
    }

    /**
     * @deprecated Use eventTypes() instead
     */
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
                // Taxes that apply to this specific event type
                $q->whereHas('eventTypes', function ($subQ) use ($eventTypeId) {
                    $subQ->where('event_types.id', $eventTypeId);
                })
                // Or global taxes (no event types assigned)
                ->orWhereDoesntHave('eventTypes');
            });
        }
        // If no event type specified, only return global taxes
        return $query->whereDoesntHave('eventTypes');
    }

    /**
     * Scope for taxes that apply to any of the given event type IDs
     */
    public function scopeForEventTypes(Builder $query, array $eventTypeIds): Builder
    {
        if (empty($eventTypeIds)) {
            return $query->whereDoesntHave('eventTypes');
        }

        return $query->where(function ($q) use ($eventTypeIds) {
            // Taxes that apply to any of these event types
            $q->whereHas('eventTypes', function ($subQ) use ($eventTypeIds) {
                $subQ->whereIn('event_types.id', $eventTypeIds);
            })
            // Or global taxes (no event types assigned)
            ->orWhereDoesntHave('eventTypes');
        });
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

    public function scopeNonCompound(Builder $query): Builder
    {
        return $query->where('is_compound', false);
    }

    public function scopeCompound(Builder $query): Builder
    {
        return $query->where('is_compound', true)->orderBy('compound_order');
    }

    public function scopeVisibleOnCheckout(Builder $query): Builder
    {
        return $query->where('visible_on_checkout', true);
    }

    public function scopeVisibleOnTicket(Builder $query): Builder
    {
        return $query->where('visible_on_ticket', true);
    }

    // Helpers

    public function isCompound(): bool
    {
        return (bool) $this->is_compound;
    }

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
