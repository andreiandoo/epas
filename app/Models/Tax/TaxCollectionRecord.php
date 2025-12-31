<?php

namespace App\Models\Tax;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class TaxCollectionRecord extends Model
{
    protected $fillable = [
        'tenant_id',
        'taxable_type',
        'taxable_id',
        'tax_type',
        'tax_id',
        'tax_name',
        'taxable_amount',
        'tax_amount',
        'rate',
        'rate_type',
        'currency',
        'is_compound',
        'exemption_applied',
        'exemption_name',
        'original_tax_amount',
        'country',
        'county',
        'city',
        'event_type_id',
        'collection_date',
    ];

    protected $casts = [
        'taxable_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'rate' => 'decimal:4',
        'original_tax_amount' => 'decimal:2',
        'is_compound' => 'boolean',
        'exemption_applied' => 'boolean',
        'collection_date' => 'date',
    ];

    // Relationships

    public function taxable(): MorphTo
    {
        return $this->morphTo();
    }

    public function generalTax()
    {
        return $this->belongsTo(GeneralTax::class, 'tax_id')
            ->where('tax_type', 'general');
    }

    public function localTax()
    {
        return $this->belongsTo(LocalTax::class, 'tax_id')
            ->where('tax_type', 'local');
    }

    // Scopes

    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeForPeriod(Builder $query, Carbon $start, Carbon $end): Builder
    {
        return $query->whereBetween('collection_date', [$start, $end]);
    }

    public function scopeForTaxType(Builder $query, string $type): Builder
    {
        return $query->where('tax_type', $type);
    }

    public function scopeGeneral(Builder $query): Builder
    {
        return $query->where('tax_type', 'general');
    }

    public function scopeLocal(Builder $query): Builder
    {
        return $query->where('tax_type', 'local');
    }

    public function scopeWithExemptions(Builder $query): Builder
    {
        return $query->where('exemption_applied', true);
    }

    public function scopeForCountry(Builder $query, string $country): Builder
    {
        return $query->where('country', $country);
    }

    // Static factory

    public static function recordFromBreakdown(
        int $tenantId,
        string $taxableType,
        int $taxableId,
        array $breakdownItem,
        float $taxableAmount,
        Carbon $date
    ): self {
        return static::create([
            'tenant_id' => $tenantId,
            'taxable_type' => $taxableType,
            'taxable_id' => $taxableId,
            'tax_type' => $breakdownItem['type'],
            'tax_id' => $breakdownItem['id'],
            'tax_name' => $breakdownItem['name'],
            'taxable_amount' => $taxableAmount,
            'tax_amount' => $breakdownItem['amount'],
            'rate' => $breakdownItem['rate'],
            'rate_type' => $breakdownItem['rate_type'],
            'currency' => $breakdownItem['currency'] ?? 'EUR',
            'is_compound' => $breakdownItem['is_compound'] ?? false,
            'exemption_applied' => !empty($breakdownItem['exemption_applied']),
            'exemption_name' => $breakdownItem['exemption_applied'] ?? null,
            'original_tax_amount' => $breakdownItem['original_amount'] ?? null,
            'country' => $breakdownItem['location']['country'] ?? null,
            'county' => $breakdownItem['location']['county'] ?? null,
            'city' => $breakdownItem['location']['city'] ?? null,
            'event_type_id' => $breakdownItem['event_type_id'] ?? null,
            'collection_date' => $date,
        ]);
    }

    // Aggregation helpers

    public static function getTotalCollected(int $tenantId, Carbon $start, Carbon $end): float
    {
        return static::forTenant($tenantId)
            ->forPeriod($start, $end)
            ->sum('tax_amount');
    }

    public static function getCollectionByTaxType(int $tenantId, Carbon $start, Carbon $end): array
    {
        return static::forTenant($tenantId)
            ->forPeriod($start, $end)
            ->selectRaw('tax_type, SUM(tax_amount) as total, COUNT(*) as count')
            ->groupBy('tax_type')
            ->get()
            ->keyBy('tax_type')
            ->toArray();
    }

    public static function getCollectionByCountry(int $tenantId, Carbon $start, Carbon $end): array
    {
        return static::forTenant($tenantId)
            ->forPeriod($start, $end)
            ->whereNotNull('country')
            ->selectRaw('country, SUM(tax_amount) as total, COUNT(*) as count')
            ->groupBy('country')
            ->orderByDesc('total')
            ->get()
            ->toArray();
    }

    public static function getDailyCollection(int $tenantId, Carbon $start, Carbon $end): array
    {
        return static::forTenant($tenantId)
            ->forPeriod($start, $end)
            ->selectRaw('collection_date, SUM(tax_amount) as total, COUNT(*) as count')
            ->groupBy('collection_date')
            ->orderBy('collection_date')
            ->get()
            ->toArray();
    }
}
