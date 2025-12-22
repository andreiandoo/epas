<?php

namespace App\Services\Tax;

use App\Models\Tax\GeneralTax;
use App\Models\Tax\LocalTax;
use App\Models\Tax\TaxExemption;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class TaxService
{
    /**
     * Calculate all applicable taxes for an order/transaction
     * Supports compound taxes (tax on tax) and exemptions
     */
    public function calculateTaxes(
        int $tenantId,
        float $amount,
        ?int $eventTypeId = null,
        ?string $country = null,
        ?string $county = null,
        ?string $city = null,
        ?Carbon $date = null,
        ?string $currency = null,
        array $exemptionContext = []
    ): TaxCalculationResult {
        $date = $date ?? Carbon::today();

        // Get all applicable taxes
        $generalTaxes = $this->getApplicableGeneralTaxes($tenantId, $eventTypeId, $date);
        $localTaxes = $country
            ? $this->getApplicableLocalTaxes($tenantId, $country, $county, $city, $eventTypeId, $date)
            : collect();

        // Get applicable exemptions
        $exemptions = $this->getApplicableExemptions($tenantId, $exemptionContext);

        $breakdown = [];
        $totalTax = 0.0;
        $taxableAmount = $amount;
        $runningTotal = $amount; // For compound taxes

        // Step 1: Apply non-compound general taxes first (on original amount)
        $nonCompoundGeneralTaxes = $generalTaxes->filter(fn($t) => !$t->isCompound());
        foreach ($nonCompoundGeneralTaxes as $tax) {
            // Skip fixed taxes with different currency
            if ($tax->isFixed() && $currency && $tax->currency && $tax->currency !== $currency) {
                continue;
            }

            $taxAmount = $tax->calculateTax($taxableAmount);

            // Apply exemptions
            $exemptionResult = $this->applyExemptions($exemptions, $tax, 'general', $taxAmount);
            $taxAmount = $exemptionResult['amount'];

            $totalTax += $taxAmount;
            $runningTotal += $taxAmount;

            $breakdown[] = new TaxBreakdownItem(
                id: $tax->id,
                type: 'general',
                name: $tax->name,
                rate: $tax->value,
                rateType: $tax->value_type,
                amount: $taxAmount,
                currency: $tax->currency,
                priority: $tax->priority,
                eventTypeId: $tax->event_type_id,
                isCompound: false,
                exemptionApplied: $exemptionResult['exemption_applied'],
                originalAmount: $exemptionResult['original_amount']
            );
        }

        // Step 2: Apply non-compound local taxes (on original amount)
        $nonCompoundLocalTaxes = $localTaxes->filter(fn($t) => !$t->isCompound());
        foreach ($nonCompoundLocalTaxes as $tax) {
            $taxAmount = $tax->calculateTax($taxableAmount);

            // Apply exemptions
            $exemptionResult = $this->applyExemptions($exemptions, $tax, 'local', $taxAmount);
            $taxAmount = $exemptionResult['amount'];

            $totalTax += $taxAmount;
            $runningTotal += $taxAmount;

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
                ],
                isCompound: false,
                exemptionApplied: $exemptionResult['exemption_applied'],
                originalAmount: $exemptionResult['original_amount']
            );
        }

        // Step 3: Apply compound general taxes (on amount + previous taxes)
        $compoundGeneralTaxes = $generalTaxes->filter(fn($t) => $t->isCompound())
            ->sortBy('compound_order');
        foreach ($compoundGeneralTaxes as $tax) {
            // Skip fixed taxes with different currency
            if ($tax->isFixed() && $currency && $tax->currency && $tax->currency !== $currency) {
                continue;
            }

            $taxAmount = $tax->calculateTax($runningTotal);

            // Apply exemptions
            $exemptionResult = $this->applyExemptions($exemptions, $tax, 'general', $taxAmount);
            $taxAmount = $exemptionResult['amount'];

            $totalTax += $taxAmount;
            $runningTotal += $taxAmount;

            $breakdown[] = new TaxBreakdownItem(
                id: $tax->id,
                type: 'general',
                name: $tax->name . ' (Compound)',
                rate: $tax->value,
                rateType: $tax->value_type,
                amount: $taxAmount,
                currency: $tax->currency,
                priority: $tax->priority,
                eventTypeId: $tax->event_type_id,
                isCompound: true,
                compoundOrder: $tax->compound_order,
                exemptionApplied: $exemptionResult['exemption_applied'],
                originalAmount: $exemptionResult['original_amount']
            );
        }

        // Step 4: Apply compound local taxes (on amount + previous taxes)
        $compoundLocalTaxes = $localTaxes->filter(fn($t) => $t->isCompound())
            ->sortBy('compound_order');
        foreach ($compoundLocalTaxes as $tax) {
            $taxAmount = $tax->calculateTax($runningTotal);

            // Apply exemptions
            $exemptionResult = $this->applyExemptions($exemptions, $tax, 'local', $taxAmount);
            $taxAmount = $exemptionResult['amount'];

            $totalTax += $taxAmount;
            $runningTotal += $taxAmount;

            $breakdown[] = new TaxBreakdownItem(
                id: $tax->id,
                type: 'local',
                name: $tax->getLocationString() . ' (Compound)',
                rate: $tax->value,
                rateType: 'percent',
                amount: $taxAmount,
                currency: null,
                priority: $tax->priority,
                location: [
                    'country' => $tax->country,
                    'county' => $tax->county,
                    'city' => $tax->city,
                ],
                isCompound: true,
                compoundOrder: $tax->compound_order,
                exemptionApplied: $exemptionResult['exemption_applied'],
                originalAmount: $exemptionResult['original_amount']
            );
        }

        return new TaxCalculationResult(
            subtotal: $amount,
            totalTax: $totalTax,
            total: $amount + $totalTax,
            breakdown: $breakdown,
            currency: $currency,
            exemptionsApplied: !empty(array_filter($breakdown, fn($b) => $b->exemptionApplied))
        );
    }

    /**
     * Get applicable exemptions for the given context
     */
    protected function getApplicableExemptions(int $tenantId, array $context): Collection
    {
        if (empty($context)) {
            return collect();
        }

        $query = TaxExemption::forTenant($tenantId)->active()->validOn();

        // Build conditions based on context
        $query->where(function ($q) use ($context) {
            // Customer exemption
            if (!empty($context['customer_id'])) {
                $q->orWhere(function ($inner) use ($context) {
                    $inner->where('exemption_type', 'customer')
                          ->where('exemptable_type', $context['customer_type'] ?? 'App\\Models\\Customer')
                          ->where('exemptable_id', $context['customer_id']);
                });
            }

            // Ticket type exemption
            if (!empty($context['ticket_type_id'])) {
                $q->orWhere(function ($inner) use ($context) {
                    $inner->where('exemption_type', 'ticket_type')
                          ->where('exemptable_type', 'App\\Models\\TicketType')
                          ->where('exemptable_id', $context['ticket_type_id']);
                });
            }

            // Event exemption
            if (!empty($context['event_id'])) {
                $q->orWhere(function ($inner) use ($context) {
                    $inner->where('exemption_type', 'event')
                          ->where('exemptable_type', 'App\\Models\\Event')
                          ->where('exemptable_id', $context['event_id']);
                });
            }

            // Product exemption
            if (!empty($context['product_id'])) {
                $q->orWhere(function ($inner) use ($context) {
                    $inner->where('exemption_type', 'product')
                          ->where('exemptable_type', 'App\\Models\\Product')
                          ->where('exemptable_id', $context['product_id']);
                });
            }

            // Category exemption
            if (!empty($context['category_id'])) {
                $q->orWhere(function ($inner) use ($context) {
                    $inner->where('exemption_type', 'category')
                          ->where('exemptable_type', 'App\\Models\\Category')
                          ->where('exemptable_id', $context['category_id']);
                });
            }
        });

        return $query->get();
    }

    /**
     * Apply exemptions to a tax amount
     */
    protected function applyExemptions(Collection $exemptions, $tax, string $taxType, float $amount): array
    {
        $originalAmount = $amount;
        $exemptionApplied = null;

        foreach ($exemptions as $exemption) {
            // Check scope
            if ($exemption->scope !== 'all' && $exemption->scope !== $taxType) {
                continue;
            }

            // Apply exemption percentage
            $exemptionFactor = 1 - ($exemption->exemption_percent / 100);
            $amount = $amount * $exemptionFactor;
            $exemptionApplied = $exemption->name;
            break; // Only apply the first matching exemption
        }

        return [
            'amount' => $amount,
            'original_amount' => $originalAmount,
            'exemption_applied' => $exemptionApplied,
        ];
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
     * Note: This is a simplified calculation that doesn't account for compound taxes
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

        // Check for compound taxes without non-compound taxes
        $hasNonCompound = GeneralTax::forTenant($tenantId)->active()->nonCompound()->exists()
            || LocalTax::forTenant($tenantId)->active()->nonCompound()->exists();
        $hasCompound = GeneralTax::forTenant($tenantId)->active()->compound()->exists()
            || LocalTax::forTenant($tenantId)->active()->compound()->exists();

        if ($hasCompound && !$hasNonCompound) {
            $issues[] = [
                'type' => 'warning',
                'severity' => 'warning',
                'message' => 'You have compound taxes but no non-compound taxes. Compound taxes will be applied on the base amount only.',
            ];
        }

        // Check for expiring taxes
        $expiringGeneral = GeneralTax::forTenant($tenantId)
            ->active()
            ->whereNotNull('valid_until')
            ->where('valid_until', '<=', Carbon::today()->addDays(30))
            ->where('valid_until', '>=', Carbon::today())
            ->count();
        $expiringLocal = LocalTax::forTenant($tenantId)
            ->active()
            ->whereNotNull('valid_until')
            ->where('valid_until', '<=', Carbon::today()->addDays(30))
            ->where('valid_until', '>=', Carbon::today())
            ->count();

        if ($expiringGeneral + $expiringLocal > 0) {
            $issues[] = [
                'type' => 'expiring',
                'severity' => 'warning',
                'message' => sprintf(
                    '%d tax(es) will expire in the next 30 days (%d general, %d local)',
                    $expiringGeneral + $expiringLocal,
                    $expiringGeneral,
                    $expiringLocal
                ),
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
        $exemptionsActive = TaxExemption::forTenant($tenantId)->active()->validOn()->count();
        $exemptionsTotal = TaxExemption::forTenant($tenantId)->count();

        // Compound taxes count
        $compoundGeneral = GeneralTax::forTenant($tenantId)->active()->compound()->count();
        $compoundLocal = LocalTax::forTenant($tenantId)->active()->compound()->count();

        return [
            'general' => [
                'active' => $generalActive,
                'total' => $generalTotal,
                'inactive' => $generalTotal - $generalActive,
                'compound' => $compoundGeneral,
            ],
            'local' => [
                'active' => $localActive,
                'total' => $localTotal,
                'inactive' => $localTotal - $localActive,
                'compound' => $compoundLocal,
            ],
            'exemptions' => [
                'active' => $exemptionsActive,
                'total' => $exemptionsTotal,
            ],
            'total_active' => $generalActive + $localActive,
            'total' => $generalTotal + $localTotal,
            'total_compound' => $compoundGeneral + $compoundLocal,
        ];
    }

    /**
     * Check for duplicate local taxes
     */
    public function checkDuplicateLocalTax(
        int $tenantId,
        string $country,
        ?string $county,
        ?string $city,
        ?int $excludeId = null
    ): ?LocalTax {
        $query = LocalTax::forTenant($tenantId)
            ->where('country', $country)
            ->where('county', $county)
            ->where('city', $city);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->first();
    }

    /**
     * Get taxes expiring within a given number of days
     */
    public function getExpiringTaxes(int $tenantId, int $days = 30): array
    {
        $date = Carbon::today()->addDays($days);

        $generalTaxes = GeneralTax::forTenant($tenantId)
            ->active()
            ->whereNotNull('valid_until')
            ->where('valid_until', '<=', $date)
            ->where('valid_until', '>=', Carbon::today())
            ->orderBy('valid_until')
            ->get();

        $localTaxes = LocalTax::forTenant($tenantId)
            ->active()
            ->whereNotNull('valid_until')
            ->where('valid_until', '<=', $date)
            ->where('valid_until', '>=', Carbon::today())
            ->orderBy('valid_until')
            ->get();

        return [
            'general' => $generalTaxes,
            'local' => $localTaxes,
            'total' => $generalTaxes->count() + $localTaxes->count(),
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
        public readonly ?string $currency = null,
        public readonly bool $exemptionsApplied = false
    ) {}

    public function toArray(): array
    {
        return [
            'subtotal' => round($this->subtotal, 2),
            'total_tax' => round($this->totalTax, 2),
            'total' => round($this->total, 2),
            'currency' => $this->currency,
            'exemptions_applied' => $this->exemptionsApplied,
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
        public readonly ?array $location = null,
        public readonly bool $isCompound = false,
        public readonly ?int $compoundOrder = null,
        public readonly ?string $exemptionApplied = null,
        public readonly ?float $originalAmount = null
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
            'is_compound' => $this->isCompound,
            'compound_order' => $this->compoundOrder,
            'exemption_applied' => $this->exemptionApplied,
            'original_amount' => $this->originalAmount ? round($this->originalAmount, 2) : null,
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
