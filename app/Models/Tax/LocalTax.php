<?php

namespace App\Models\Tax;

use App\Models\Tenant;
use App\Models\EventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class LocalTax extends Model
{
    use LogsActivity;

    protected $table = 'local_taxes';

    protected $fillable = [
        'tenant_id',
        'country',
        'county',
        'city',
        'value',
        'explanation',
        'source_url',
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
            ->logOnly(['country', 'county', 'city', 'value', 'explanation', 'source_url', 'is_active'])
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
            return $query->where('county', $county);
        }
        return $query->whereNull('county');
    }

    public function scopeForCity(Builder $query, ?string $city): Builder
    {
        if ($city) {
            return $query->where('city', $city);
        }
        return $query->whereNull('city');
    }

    public function scopeForLocation(Builder $query, string $country, ?string $county = null, ?string $city = null): Builder
    {
        return $query->forCountry($country)
            ->forCounty($county)
            ->forCity($city);
    }

    // Helpers

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

    public function hasEventType(int $eventTypeId): bool
    {
        return $this->eventTypes()->where('event_types.id', $eventTypeId)->exists();
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
