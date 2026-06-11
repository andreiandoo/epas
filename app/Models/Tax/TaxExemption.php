<?php

namespace App\Models\Tax;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Carbon\Carbon;

class TaxExemption extends Model
{
    use LogsActivity, SoftDeletes;

    protected $table = 'tax_exemptions';

    protected $fillable = [
        'tenant_id',
        'name',
        'exemption_type',
        'exemptable_id',
        'exemptable_type',
        'scope',
        'exemption_percent',
        'reason',
        'valid_from',
        'valid_until',
        'is_active',
    ];

    protected $casts = [
        'exemption_percent' => 'decimal:2',
        'valid_from' => 'date',
        'valid_until' => 'date',
        'is_active' => 'boolean',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'name', 'exemption_type', 'exemptable_id', 'scope',
                'exemption_percent', 'reason', 'valid_from', 'valid_until', 'is_active'
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    // Relationships

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function exemptable(): MorphTo
    {
        return $this->morphTo();
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

    public function scopeForType(Builder $query, string $type): Builder
    {
        return $query->where('exemption_type', $type);
    }

    public function scopeValidOn(Builder $query, ?Carbon $date = null): Builder
    {
        $date = $date ?? Carbon::today();

        return $query->where(function ($q) use ($date) {
            $q->where(function ($inner) use ($date) {
                $inner->whereNotNull('valid_from')
                      ->where('valid_from', '<=', $date);
            })->orWhereNull('valid_from');
        })->where(function ($q) use ($date) {
            $q->where(function ($inner) use ($date) {
                $inner->whereNotNull('valid_until')
                      ->where('valid_until', '>=', $date);
            })->orWhereNull('valid_until');
        });
    }

    public function scopeApplicable(Builder $query, int $tenantId, ?Carbon $date = null): Builder
    {
        return $query->forTenant($tenantId)
            ->active()
            ->validOn($date);
    }

    // Helpers

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

    public function isFullExemption(): bool
    {
        return $this->exemption_percent >= 100;
    }

    public function getExemptionMultiplier(): float
    {
        return 1 - ($this->exemption_percent / 100);
    }

    public function appliesToScope(string $taxType): bool
    {
        if ($this->scope === 'all') {
            return true;
        }
        return $this->scope === $taxType;
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

    /**
     * Check if this exemption applies to a given entity
     */
    public function appliesTo($entity): bool
    {
        if (!$this->isCurrentlyValid()) {
            return false;
        }

        // Global exemption for type
        if (!$this->exemptable_id) {
            return true;
        }

        // Check if entity matches
        $entityClass = get_class($entity);
        $entityId = $entity->id ?? $entity->getKey();

        return $this->exemptable_type === $entityClass && $this->exemptable_id == $entityId;
    }

    /**
     * Get exemptions for a customer
     */
    public static function getForCustomer(int $tenantId, int $customerId, ?Carbon $date = null): ?self
    {
        return static::applicable($tenantId, $date)
            ->forType('customer')
            ->where(function ($q) use ($customerId) {
                $q->where('exemptable_id', $customerId)
                  ->orWhereNull('exemptable_id');
            })
            ->orderByDesc('exemption_percent')
            ->first();
    }

    /**
     * Get exemptions for a ticket type
     */
    public static function getForTicketType(int $tenantId, int $ticketTypeId, ?Carbon $date = null): ?self
    {
        return static::applicable($tenantId, $date)
            ->forType('ticket_type')
            ->where(function ($q) use ($ticketTypeId) {
                $q->where('exemptable_id', $ticketTypeId)
                  ->orWhereNull('exemptable_id');
            })
            ->orderByDesc('exemption_percent')
            ->first();
    }
}
