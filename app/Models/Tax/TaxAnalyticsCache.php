<?php

namespace App\Models\Tax;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class TaxAnalyticsCache extends Model
{
    protected $table = 'tax_analytics_cache';

    protected $fillable = [
        'tenant_id',
        'period_type',
        'period_start',
        'period_end',
        'tax_type',
        'tax_id',
        'transaction_count',
        'total_taxable_amount',
        'total_tax_collected',
        'average_effective_rate',
        'currency',
        'breakdown',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'total_taxable_amount' => 'decimal:2',
        'total_tax_collected' => 'decimal:2',
        'average_effective_rate' => 'decimal:4',
        'breakdown' => 'array',
    ];

    // Scopes

    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeForPeriodType(Builder $query, string $type): Builder
    {
        return $query->where('period_type', $type);
    }

    public function scopeForDateRange(Builder $query, Carbon $start, Carbon $end): Builder
    {
        return $query->where('period_start', '>=', $start)
            ->where('period_end', '<=', $end);
    }

    public function scopeAllTaxes(Builder $query): Builder
    {
        return $query->whereNull('tax_type')->whereNull('tax_id');
    }

    // Static helpers

    public static function getOrGenerate(
        int $tenantId,
        string $periodType,
        Carbon $periodStart,
        ?string $taxType = null,
        ?int $taxId = null
    ): self {
        $periodEnd = static::calculatePeriodEnd($periodType, $periodStart);

        $existing = static::forTenant($tenantId)
            ->where('period_type', $periodType)
            ->where('period_start', $periodStart)
            ->where('tax_type', $taxType)
            ->where('tax_id', $taxId)
            ->first();

        if ($existing) {
            // Check if cache is stale (older than 1 hour for current period)
            if ($periodEnd->isFuture() && $existing->updated_at->diffInHours(now()) > 1) {
                return static::regenerate($existing, $periodStart, $periodEnd);
            }
            return $existing;
        }

        return static::generate($tenantId, $periodType, $periodStart, $periodEnd, $taxType, $taxId);
    }

    protected static function calculatePeriodEnd(string $periodType, Carbon $start): Carbon
    {
        return match ($periodType) {
            'daily' => $start->copy()->endOfDay(),
            'weekly' => $start->copy()->endOfWeek(),
            'monthly' => $start->copy()->endOfMonth(),
            'yearly' => $start->copy()->endOfYear(),
            default => $start->copy()->endOfDay(),
        };
    }

    protected static function generate(
        int $tenantId,
        string $periodType,
        Carbon $periodStart,
        Carbon $periodEnd,
        ?string $taxType,
        ?int $taxId
    ): self {
        $query = TaxCollectionRecord::forTenant($tenantId)
            ->forPeriod($periodStart, $periodEnd);

        if ($taxType) {
            $query->forTaxType($taxType);
        }

        if ($taxId) {
            $query->where('tax_id', $taxId);
        }

        $stats = $query->selectRaw('
            COUNT(*) as transaction_count,
            COALESCE(SUM(taxable_amount), 0) as total_taxable_amount,
            COALESCE(SUM(tax_amount), 0) as total_tax_collected
        ')->first();

        $effectiveRate = $stats->total_taxable_amount > 0
            ? ($stats->total_tax_collected / $stats->total_taxable_amount) * 100
            : 0;

        // Get breakdown by individual taxes
        $breakdown = $query->selectRaw('
            tax_type, tax_id, tax_name,
            COUNT(*) as count,
            SUM(tax_amount) as total
        ')
            ->groupBy('tax_type', 'tax_id', 'tax_name')
            ->orderByDesc('total')
            ->get()
            ->toArray();

        return static::create([
            'tenant_id' => $tenantId,
            'period_type' => $periodType,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'tax_type' => $taxType,
            'tax_id' => $taxId,
            'transaction_count' => $stats->transaction_count,
            'total_taxable_amount' => $stats->total_taxable_amount,
            'total_tax_collected' => $stats->total_tax_collected,
            'average_effective_rate' => $effectiveRate,
            'breakdown' => $breakdown,
        ]);
    }

    protected static function regenerate(self $cache, Carbon $periodStart, Carbon $periodEnd): self
    {
        $query = TaxCollectionRecord::forTenant($cache->tenant_id)
            ->forPeriod($periodStart, $periodEnd);

        if ($cache->tax_type) {
            $query->forTaxType($cache->tax_type);
        }

        if ($cache->tax_id) {
            $query->where('tax_id', $cache->tax_id);
        }

        $stats = $query->selectRaw('
            COUNT(*) as transaction_count,
            COALESCE(SUM(taxable_amount), 0) as total_taxable_amount,
            COALESCE(SUM(tax_amount), 0) as total_tax_collected
        ')->first();

        $effectiveRate = $stats->total_taxable_amount > 0
            ? ($stats->total_tax_collected / $stats->total_taxable_amount) * 100
            : 0;

        $breakdown = $query->selectRaw('
            tax_type, tax_id, tax_name,
            COUNT(*) as count,
            SUM(tax_amount) as total
        ')
            ->groupBy('tax_type', 'tax_id', 'tax_name')
            ->orderByDesc('total')
            ->get()
            ->toArray();

        $cache->update([
            'transaction_count' => $stats->transaction_count,
            'total_taxable_amount' => $stats->total_taxable_amount,
            'total_tax_collected' => $stats->total_tax_collected,
            'average_effective_rate' => $effectiveRate,
            'breakdown' => $breakdown,
        ]);

        return $cache->fresh();
    }

    public static function invalidateForTenant(int $tenantId): void
    {
        static::forTenant($tenantId)
            ->where('period_end', '>=', now()->startOfDay())
            ->delete();
    }
}
