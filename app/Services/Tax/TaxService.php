<?php

namespace App\Services\Tax;

use App\Models\Tax\GeneralTax;
use App\Models\Tax\LocalTax;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class TaxService
{
    /**
     * Calculate all applicable taxes for an order/transaction
     */
    public function calculateTaxes(
        int $tenantId,
        float $amount,
        ?int $eventTypeId = null,
        ?string $country = null,
        ?string $county = null,
        ?string $city = null,
        ?Carbon $date = null,
        ?string $currency = null
    ): TaxCalculationResult {
        $date = $date ?? Carbon::today();

        $generalTaxes = $this->getApplicableGeneralTaxes($tenantId, $eventTypeId, $date);
        $localTaxes = $country
            ? $this->getApplicableLocalTaxes($tenantId, $country, $county, $city, $eventTypeId, $date)
            : collect();

        $breakdown = [];
        $totalTax = 0.0;
        $taxableAmount = $amount;

        // Apply general taxes first (by priority)
        foreach ($generalTaxes as $tax) {
            // Skip fixed taxes with different currency
            if ($tax->isFixed() && $currency && $tax->currency && $tax->currency !== $currency) {
                continue;
            }

            $taxAmount = $tax->calculateTax($taxableAmount);
            $totalTax += $taxAmount;

            $breakdown[] = new TaxBreakdownItem(
                id: $tax->id,
                type: 'general',
                name: $tax->name,
                rate: $tax->value,
                rateType: $tax->value_type,
                amount: $taxAmount,
                currency: $tax->currency,
                priority: $tax->priority,
                eventTypeId: $tax->event_type_id
            );
        }

        // Apply local taxes (by priority)
        foreach ($localTaxes as $tax) {
            $taxAmount = $tax->calculateTax($taxableAmount);
            $totalTax += $taxAmount;

            $breakdown[] = new TaxBreakdownItem(
                id: $tax->id,
                type: 'local',
                name: $tax->getLocationString(),
                rate: $tax->value,
                rateType: 'percent',
                amount: $taxAmount,
                currency: null,
                priority: $tax->priority,
                location: [
                    'country' => $tax->country,
                    'county' => $tax->county,
                    'city' => $tax->city,
                ]
            );
        }

        return new TaxCalculationResult(
            subtotal: $amount,
            totalTax: $totalTax,
            total: $amount + $totalTax,
            breakdown: $breakdown,
            currency: $currency
        );
    }

    /**
     * Get applicable general taxes for a tenant
     */
    public function getApplicableGeneralTaxes(
        int $tenantId,
        ?int $eventTypeId = null,
        ?Carbon $date = null
    ): Collection {
        return GeneralTax::applicable($tenantId, $eventTypeId, $date)->get();
    }

    /**
     * Get applicable local taxes for a location
     */
    public function getApplicableLocalTaxes(
        int $tenantId,
        string $country,
        ?string $county = null,
        ?string $city = null,
        ?int $eventTypeId = null,
        ?Carbon $date = null
    ): Collection {
        return LocalTax::applicable($tenantId, $country, $county, $city, $eventTypeId, $date)->get();
    }

    /**
     * Get all applicable taxes (combined) for display
     */
    public function getAllApplicableTaxes(
        int $tenantId,
        ?int $eventTypeId = null,
        ?string $country = null,
        ?string $county = null,
        ?string $city = null,
        ?Carbon $date = null
    ): array {
        $generalTaxes = $this->getApplicableGeneralTaxes($tenantId, $eventTypeId, $date);
        $localTaxes = $country
            ? $this->getApplicableLocalTaxes($tenantId, $country, $county, $city, $eventTypeId, $date)
            : collect();

        return [
            'general' => $generalTaxes,
            'local' => $localTaxes,
            'total_count' => $generalTaxes->count() + $localTaxes->count(),
        ];
    }

    /**
     * Get effective tax rate (sum of all percentage taxes)
     */
    public function getEffectiveTaxRate(
        int $tenantId,
        ?int $eventTypeId = null,
        ?string $country = null,
        ?string $county = null,
        ?string $city = null,
        ?Carbon $date = null
    ): float {
        $rate = 0.0;

        $generalTaxes = $this->getApplicableGeneralTaxes($tenantId, $eventTypeId, $date);
        foreach ($generalTaxes as $tax) {
            if ($tax->isPercent()) {
                $rate += (float) $tax->value;
            }
        }

        if ($country) {
            $localTaxes = $this->getApplicableLocalTaxes($tenantId, $country, $county, $city, $eventTypeId, $date);
            foreach ($localTaxes as $tax) {
                $rate += (float) $tax->value;
            }
        }

        return $rate;
    }

    /**
     * Validate tax configuration for potential issues
     */
    public function validateTaxConfiguration(int $tenantId): array
    {
        $issues = [];

        // Check for overlapping general taxes
        $generalTaxes = GeneralTax::forTenant($tenantId)->active()->get();
        $grouped = $generalTaxes->groupBy('event_type_id');

        foreach ($grouped as $eventTypeId => $taxes) {
            if ($taxes->count() > 1) {
                // Check for date overlaps
                foreach ($taxes as $i => $tax1) {
                    foreach ($taxes->slice($i + 1) as $tax2) {
                        if ($this->datesOverlap($tax1, $tax2)) {
                            $issues[] = [
                                'type' => 'overlap',
                                'severity' => 'warning',
                                'message' => "General taxes '{$tax1->name}' and '{$tax2->name}' have overlapping validity periods",
                                'taxes' => [$tax1->id, $tax2->id],
                            ];
                        }
                    }
                }
            }
        }

        // Check for local taxes without event types (applies to all)
        $localTaxesAll = LocalTax::forTenant($tenantId)
            ->active()
            ->whereDoesntHave('eventTypes')
            ->get();

        if ($localTaxesAll->count() > 5) {
            $issues[] = [
                'type' => 'info',
                'severity' => 'info',
                'message' => "You have {$localTaxesAll->count()} local taxes that apply to all event types",
            ];
        }

        return $issues;
    }

    /**
     * Check if two taxes have overlapping validity periods
     */
    protected function datesOverlap($tax1, $tax2): bool
    {
        $start1 = $tax1->valid_from ?? Carbon::minValue();
        $end1 = $tax1->valid_until ?? Carbon::maxValue();
        $start2 = $tax2->valid_from ?? Carbon::minValue();
        $end2 = $tax2->valid_until ?? Carbon::maxValue();

        return $start1 <= $end2 && $start2 <= $end1;
    }

    /**
     * Get tax summary for a tenant
     */
    public function getTaxSummary(int $tenantId): array
    {
        $generalActive = GeneralTax::forTenant($tenantId)->active()->validOn()->count();
        $generalTotal = GeneralTax::forTenant($tenantId)->count();
        $localActive = LocalTax::forTenant($tenantId)->active()->validOn()->count();
        $localTotal = LocalTax::forTenant($tenantId)->count();

        return [
            'general' => [
                'active' => $generalActive,
                'total' => $generalTotal,
                'inactive' => $generalTotal - $generalActive,
            ],
            'local' => [
                'active' => $localActive,
                'total' => $localTotal,
                'inactive' => $localTotal - $localActive,
            ],
            'total_active' => $generalActive + $localActive,
            'total' => $generalTotal + $localTotal,
        ];
    }
}

/**
 * Data class for tax calculation result
 */
class TaxCalculationResult
{
    public function __construct(
        public readonly float $subtotal,
        public readonly float $totalTax,
        public readonly float $total,
        public readonly array $breakdown,
        public readonly ?string $currency = null
    ) {}

    public function toArray(): array
    {
        return [
            'subtotal' => round($this->subtotal, 2),
            'total_tax' => round($this->totalTax, 2),
            'total' => round($this->total, 2),
            'currency' => $this->currency,
            'breakdown' => array_map(fn ($item) => $item->toArray(), $this->breakdown),
        ];
    }

    public function getEffectiveRate(): float
    {
        if ($this->subtotal == 0) {
            return 0;
        }
        return ($this->totalTax / $this->subtotal) * 100;
    }
}

/**
 * Data class for individual tax breakdown item
 */
class TaxBreakdownItem
{
    public function __construct(
        public readonly int $id,
        public readonly string $type,
        public readonly string $name,
        public readonly float $rate,
        public readonly string $rateType,
        public readonly float $amount,
        public readonly ?string $currency = null,
        public readonly int $priority = 0,
        public readonly ?int $eventTypeId = null,
        public readonly ?array $location = null
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'name' => $this->name,
            'rate' => $this->rate,
            'rate_type' => $this->rateType,
            'amount' => round($this->amount, 2),
            'currency' => $this->currency,
            'priority' => $this->priority,
            'event_type_id' => $this->eventTypeId,
            'location' => $this->location,
        ];
    }

    public function getFormattedRate(): string
    {
        if ($this->rateType === 'percent') {
            return number_format($this->rate, 2) . '%';
        }
        return number_format($this->rate, 2) . ($this->currency ? " {$this->currency}" : '');
    }
}
