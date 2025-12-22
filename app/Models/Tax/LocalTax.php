<?php

namespace App\Models\Tax;

use App\Models\Tenant;
use App\Models\EventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Carbon\Carbon;

class LocalTax extends Model
{
    use LogsActivity, SoftDeletes;

    protected $table = 'local_taxes';

    protected $fillable = [
        'tenant_id',
        'country',
        'county',
        'city',
        'value',
        'explanation',
        'source_url',
        'priority',
        'is_compound',
        'compound_order',
        'valid_from',
        'valid_until',
        'is_active',
    ];

    protected $casts = [
        'value' => 'decimal:4',
        'priority' => 'integer',
        'is_compound' => 'boolean',
        'compound_order' => 'integer',
        'valid_from' => 'date',
        'valid_until' => 'date',
        'is_active' => 'boolean',
    ];

    // Activity Log Configuration
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'country', 'county', 'city', 'value', 'explanation',
                'source_url', 'priority', 'is_compound', 'compound_order',
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
        return $this->belongsToMany(
            EventType::class,
            'local_tax_event_type',
            'local_tax_id',
            'event_type_id'
        )->withTimestamps();
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

    public function scopeForCountry(Builder $query, string $country): Builder
    {
        return $query->where('country', $country);
    }

    public function scopeForCounty(Builder $query, ?string $county): Builder
    {
        if ($county) {
            return $query->where(function ($q) use ($county) {
                $q->where('county', $county)
                  ->orWhereNull('county'); // Include country-wide taxes
            });
        }
        return $query->whereNull('county');
    }

    public function scopeForCity(Builder $query, ?string $city): Builder
    {
        if ($city) {
            return $query->where(function ($q) use ($city) {
                $q->where('city', $city)
                  ->orWhereNull('city'); // Include county/country-wide taxes
            });
        }
        return $query->whereNull('city');
    }

    public function scopeForLocation(Builder $query, string $country, ?string $county = null, ?string $city = null): Builder
    {
        return $query->forCountry($country)
            ->forCounty($county)
            ->forCity($city);
    }

    public function scopeForEventType(Builder $query, ?int $eventTypeId): Builder
    {
        if ($eventTypeId) {
            return $query->where(function ($q) use ($eventTypeId) {
                // Has no event types (applies to all) OR has the specific event type
                $q->whereDoesntHave('eventTypes')
                  ->orWhereHas('eventTypes', function ($inner) use ($eventTypeId) {
                      $inner->where('event_types.id', $eventTypeId);
                  });
            });
        }
        return $query->whereDoesntHave('eventTypes');
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

    public function scopeApplicable(
        Builder $query,
        int $tenantId,
        string $country,
        ?string $county = null,
        ?string $city = null,
        ?int $eventTypeId = null,
        ?Carbon $date = null
    ): Builder {
        return $query->forTenant($tenantId)
            ->active()
            ->forLocation($country, $county, $city)
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

    // Helpers

    public function isCompound(): bool
    {
        return (bool) $this->is_compound;
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
        return $amount * ($this->value / 100);
    }

    public function getFormattedValue(): string
    {
        return number_format($this->value, 2) . '%';
    }

    public function getLocationString(): string
    {
        $parts = array_filter([$this->city, $this->county, $this->country]);
        return implode(', ', $parts);
    }

    public function getLocationLevel(): string
    {
        if ($this->city) {
            return 'city';
        }
        if ($this->county) {
            return 'county';
        }
        return 'country';
    }

    public function hasEventType(int $eventTypeId): bool
    {
        return $this->eventTypes()->where('event_types.id', $eventTypeId)->exists();
    }

    public function appliesToAllEventTypes(): bool
    {
        return $this->eventTypes()->count() === 0;
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

    /**
     * Get all unique cities for a given country and county
     */
    public static function getCitiesForLocation(int $tenantId, string $country, ?string $county = null): array
    {
        $query = static::where('tenant_id', $tenantId)
            ->where('country', $country)
            ->whereNotNull('city');

        if ($county) {
            $query->where('county', $county);
        }

        return $query->distinct()
            ->pluck('city')
            ->filter()
            ->sort()
            ->values()
            ->toArray();
    }

    /**
     * Get all unique counties for a given country
     */
    public static function getCountiesForCountry(int $tenantId, string $country): array
    {
        return static::where('tenant_id', $tenantId)
            ->where('country', $country)
            ->whereNotNull('county')
            ->distinct()
            ->pluck('county')
            ->filter()
            ->sort()
            ->values()
            ->toArray();
    }
}
