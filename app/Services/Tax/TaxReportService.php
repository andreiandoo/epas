<?php

namespace App\Services\Tax;

use App\Models\Event;
use App\Models\Tenant;
use App\Models\Tax\GeneralTax;
use App\Models\Tax\LocalTax;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class TaxReportService
{
    public function __construct(
        protected TaxService $taxService
    ) {}

    /**
     * Get comprehensive tax report for all events of a tenant
     */
    public function getEventsTaxReport(Tenant $tenant): array
    {
        $events = Event::where('tenant_id', $tenant->id)
            ->with(['venue', 'eventTypes', 'ticketTypes'])
            ->orderByDesc('event_date')
            ->orderByDesc('range_start_date')
            ->get();

        $reports = [];
        $grandTotals = [
            'total_revenue' => 0,
            'total_tax' => 0,
            'taxes_by_type' => [],
        ];

        foreach ($events as $event) {
            $report = $this->calculateEventTaxes($event, $tenant);
            $reports[] = $report;

            // Aggregate totals
            $grandTotals['total_revenue'] += $report['estimated_revenue'];
            $grandTotals['total_tax'] += $report['total_tax'];

            foreach ($report['taxes'] as $tax) {
                $key = $tax['name'];
                if (!isset($grandTotals['taxes_by_type'][$key])) {
                    $grandTotals['taxes_by_type'][$key] = [
                        'name' => $tax['name'],
                        'total_amount' => 0,
                        'type' => $tax['type'],
                        'payment_info' => $tax['payment_info'] ?? null,
                    ];
                }
                $grandTotals['taxes_by_type'][$key]['total_amount'] += $tax['amount'];
            }
        }

        return [
            'tenant' => $tenant,
            'events' => $reports,
            'grand_totals' => $grandTotals,
            'is_vat_payer' => (bool) $tenant->vat_payer,
            'generated_at' => now(),
        ];
    }

    /**
     * Calculate taxes for a single event
     */
    public function calculateEventTaxes(Event $event, Tenant $tenant): array
    {
        $eventDate = $event->start_date;
        $venue = $event->venue;

        // Get location info from venue
        $country = $venue?->country ?? 'Romania';
        $county = $venue?->state ?? null;
        $city = $venue?->city ?? null;

        // Get event type(s)
        $eventTypes = $event->eventTypes;
        $primaryEventType = $eventTypes->first();

        // Calculate estimated revenue from ticket types
        $estimatedRevenue = $this->calculateEstimatedRevenue($event);

        // Get applicable taxes
        $taxes = $this->getApplicableTaxesForEvent(
            $tenant,
            $primaryEventType?->id,
            $country,
            $county,
            $city,
            $eventDate
        );

        // Calculate tax amounts
        $taxBreakdown = [];
        $totalTax = 0;

        foreach ($taxes as $tax) {
            // Skip VAT if tenant is not a VAT payer and this is a VAT tax
            if (!$tenant->vat_payer && $this->isVatTax($tax)) {
                continue;
            }

            $amount = $this->calculateTaxAmount($tax, $estimatedRevenue);
            $totalTax += $amount;

            $taxBreakdown[] = [
                'id' => $tax->id,
                'type' => $tax instanceof GeneralTax ? 'general' : 'local',
                'name' => $tax->name ?? $tax->getLocationString(),
                'value' => (float) $tax->value,
                'value_type' => $tax->value_type ?? 'percent',
                'amount' => round($amount, 2),
                'formatted_value' => $tax->getFormattedValue(),
                'explanation' => strip_tags($tax->explanation ?? ''),
                'legal_basis' => $tax->legal_basis ?? null,
                'payment_info' => $this->getPaymentInfo($tax),
                'payment_deadline' => $this->calculatePaymentDeadline($tax, $eventDate),
                'is_added_to_price' => $tax->is_added_to_price ?? false,
            ];
        }

        return [
            'event' => [
                'id' => $event->id,
                'title' => $event->getTranslation('title', 'ro') ?: $event->getTranslation('title', 'en'),
                'date' => $eventDate?->format('d M Y'),
                'date_raw' => $eventDate,
                'status' => $this->getEventStatus($event),
                'venue' => $venue?->getTranslation('name', 'ro') ?: $venue?->getTranslation('name', 'en') ?: 'N/A',
                'location' => $this->formatLocation($country, $county, $city),
            ],
            'event_types' => $eventTypes->map(fn($et) => [
                'id' => $et->id,
                'name' => $et->getTranslation('name', 'ro') ?: $et->getTranslation('name', 'en'),
            ])->toArray(),
            'estimated_revenue' => round($estimatedRevenue, 2),
            'taxes' => $taxBreakdown,
            'total_tax' => round($totalTax, 2),
            'effective_tax_rate' => $estimatedRevenue > 0
                ? round(($totalTax / $estimatedRevenue) * 100, 2)
                : 0,
            'net_revenue' => round($estimatedRevenue - $totalTax, 2),
        ];
    }

    /**
     * Get all applicable taxes for an event context
     */
    protected function getApplicableTaxesForEvent(
        Tenant $tenant,
        ?int $eventTypeId,
        string $country,
        ?string $county,
        ?string $city,
        ?Carbon $date
    ): Collection {
        $date = $date ?? Carbon::today();
        $taxes = collect();

        // Get global general taxes (no tenant_id)
        $generalTaxes = GeneralTax::query()
            ->where(function ($q) use ($tenant) {
                $q->whereNull('tenant_id')
                  ->orWhere('tenant_id', $tenant->id);
            })
            ->active()
            ->validOn($date)
            ->where(function ($q) use ($eventTypeId) {
                if ($eventTypeId) {
                    $q->where('event_type_id', $eventTypeId)
                      ->orWhereNull('event_type_id');
                } else {
                    $q->whereNull('event_type_id');
                }
            })
            ->orderByDesc('priority')
            ->get();

        $taxes = $taxes->merge($generalTaxes);

        // Get local taxes based on location
        $localTaxes = LocalTax::query()
            ->where(function ($q) use ($tenant) {
                $q->whereNull('tenant_id')
                  ->orWhere('tenant_id', $tenant->id);
            })
            ->active()
            ->validOn($date)
            ->where('country', $country)
            ->where(function ($q) use ($county, $city) {
                $q->where(function ($inner) use ($county, $city) {
                    // Exact match
                    $inner->where('county', $county)
                          ->where('city', $city);
                })
                ->orWhere(function ($inner) use ($county) {
                    // County level (all cities)
                    $inner->where('county', $county)
                          ->whereNull('city');
                })
                ->orWhere(function ($inner) {
                    // Country level (all counties/cities)
                    $inner->whereNull('county')
                          ->whereNull('city');
                });
            })
            ->orderByDesc('priority')
            ->get();

        $taxes = $taxes->merge($localTaxes);

        return $taxes;
    }

    /**
     * Calculate estimated revenue from ticket sales
     */
    protected function calculateEstimatedRevenue(Event $event): float
    {
        $revenue = 0;

        foreach ($event->ticketTypes as $ticketType) {
            // Use actual sold tickets if available, otherwise estimate at 50% capacity
            $soldTickets = $ticketType->tickets()->count();
            $estimatedTickets = $soldTickets > 0 ? $soldTickets : (int) (($ticketType->quantity ?? 0) * 0.5);
            $price = (float) ($ticketType->price ?? 0);
            $revenue += $estimatedTickets * $price;
        }

        return $revenue;
    }

    /**
     * Calculate tax amount based on type
     */
    protected function calculateTaxAmount($tax, float $amount): float
    {
        $valueType = $tax->value_type ?? 'percent';

        if ($valueType === 'percent') {
            return $amount * ((float) $tax->value / 100);
        }

        return (float) $tax->value;
    }

    /**
     * Check if a tax is a VAT tax (by name pattern)
     */
    protected function isVatTax($tax): bool
    {
        $name = strtolower($tax->name ?? '');
        return str_contains($name, 'tva') || str_contains($name, 'vat');
    }

    /**
     * Get payment information for a tax
     */
    protected function getPaymentInfo($tax): ?array
    {
        if (empty($tax->beneficiary) && empty($tax->iban) && empty($tax->where_to_pay)) {
            return null;
        }

        return [
            'beneficiary' => $tax->beneficiary ?? null,
            'iban' => $tax->iban ?? null,
            'address' => $tax->beneficiary_address ?? null,
            'where_to_pay' => $tax->where_to_pay ?? null,
            'declaration' => $tax->declaration ?? null,
            'before_event_instructions' => $tax->before_event_instructions ?? null,
            'after_event_instructions' => $tax->after_event_instructions ?? null,
        ];
    }

    /**
     * Calculate payment deadline based on payment terms
     */
    protected function calculatePaymentDeadline($tax, ?Carbon $eventDate): ?array
    {
        if (!$eventDate) {
            return null;
        }

        $paymentTerm = $tax->payment_term ?? null;
        $paymentTermType = $tax->payment_term_type ?? 'after_event';

        if (!$paymentTerm) {
            return null;
        }

        $deadline = null;
        $description = '';

        switch ($paymentTerm) {
            case 'before_event':
                $daysAfter = (int) ($tax->payment_term_days_after ?? 0);
                $deadline = $eventDate->copy()->subDays($daysAfter);
                $description = "Cu {$daysAfter} zile înainte de eveniment";
                break;

            case 'after_event':
                $daysAfter = (int) ($tax->payment_term_days_after ?? 15);
                $deadline = $eventDate->copy()->addDays($daysAfter);
                $description = "În {$daysAfter} zile după eveniment";
                break;

            case 'fixed_day':
                $day = (int) ($tax->payment_term_day ?? 25);
                // Next occurrence of the day after event
                $deadline = $eventDate->copy()->addMonth()->day($day);
                $description = "Până pe {$day} luna următoare";
                break;

            case 'quarterly':
                // End of the quarter following the event
                $quarter = ceil($eventDate->month / 3);
                $deadline = Carbon::create($eventDate->year, $quarter * 3, 1)->endOfMonth();
                if ($deadline->lte($eventDate)) {
                    $deadline = $deadline->addMonths(3);
                }
                $description = "Până la sfârșitul trimestrului următor";
                break;

            case 'monthly':
                $day = (int) ($tax->payment_term_day ?? 25);
                $deadline = $eventDate->copy()->addMonth()->day($day);
                $description = "Până pe {$day} luna următoare evenimentului";
                break;
        }

        if (!$deadline) {
            return null;
        }

        return [
            'date' => $deadline->format('d M Y'),
            'date_raw' => $deadline,
            'description' => $description,
            'is_overdue' => $deadline->isPast(),
            'days_remaining' => $deadline->isFuture() ? now()->diffInDays($deadline) : 0,
        ];
    }

    /**
     * Get event status string
     */
    protected function getEventStatus(Event $event): string
    {
        if ($event->is_cancelled) {
            return 'cancelled';
        }
        if ($event->is_postponed) {
            return 'postponed';
        }
        if ($event->isPast()) {
            return 'past';
        }
        return 'upcoming';
    }

    /**
     * Format location string
     */
    protected function formatLocation(?string $country, ?string $county, ?string $city): string
    {
        $parts = array_filter([$city, $county, $country]);
        return implode(', ', $parts) ?: 'N/A';
    }

    /**
     * Get tax summary grouped by tax type
     */
    public function getTaxSummaryByType(Tenant $tenant): array
    {
        $report = $this->getEventsTaxReport($tenant);
        $summary = [];

        foreach ($report['grand_totals']['taxes_by_type'] as $taxName => $taxData) {
            $summary[] = [
                'name' => $taxData['name'],
                'type' => $taxData['type'],
                'total_amount' => round($taxData['total_amount'], 2),
                'payment_info' => $taxData['payment_info'],
            ];
        }

        // Sort by amount descending
        usort($summary, fn($a, $b) => $b['total_amount'] <=> $a['total_amount']);

        return $summary;
    }

    /**
     * Get upcoming payment deadlines
     */
    public function getUpcomingDeadlines(Tenant $tenant, int $days = 30): array
    {
        $report = $this->getEventsTaxReport($tenant);
        $deadlines = [];

        foreach ($report['events'] as $eventReport) {
            foreach ($eventReport['taxes'] as $tax) {
                if (!empty($tax['payment_deadline']) && !$tax['payment_deadline']['is_overdue']) {
                    $deadline = $tax['payment_deadline'];
                    if ($deadline['days_remaining'] <= $days) {
                        $deadlines[] = [
                            'event' => $eventReport['event']['title'],
                            'event_date' => $eventReport['event']['date'],
                            'tax_name' => $tax['name'],
                            'amount' => $tax['amount'],
                            'deadline' => $deadline['date'],
                            'days_remaining' => $deadline['days_remaining'],
                            'payment_info' => $tax['payment_info'],
                        ];
                    }
                }
            }
        }

        // Sort by days remaining
        usort($deadlines, fn($a, $b) => $a['days_remaining'] <=> $b['days_remaining']);

        return $deadlines;
    }

    /**
     * Get overdue tax payments
     */
    public function getOverduePayments(Tenant $tenant): array
    {
        $report = $this->getEventsTaxReport($tenant);
        $overdue = [];

        foreach ($report['events'] as $eventReport) {
            foreach ($eventReport['taxes'] as $tax) {
                if (!empty($tax['payment_deadline']) && $tax['payment_deadline']['is_overdue']) {
                    $overdue[] = [
                        'event' => $eventReport['event']['title'],
                        'event_date' => $eventReport['event']['date'],
                        'tax_name' => $tax['name'],
                        'amount' => $tax['amount'],
                        'deadline' => $tax['payment_deadline']['date'],
                        'payment_info' => $tax['payment_info'],
                    ];
                }
            }
        }

        return $overdue;
    }
}
