<?php

namespace App\Filament\Marketplace\Pages;

use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Models\Event;
use App\Models\MarketplaceOrganizer;
use App\Services\Marketplace\SalesReportService;
use BackedEnum;
use Carbon\Carbon;
use Filament\Pages\Page;
use Livewire\Attributes\Url;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SalesReport extends Page
{
    use HasMarketplaceContext;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-chart-bar';
    protected static ?string $navigationLabel = 'Raport Vânzări';
    protected static ?string $title = 'Raport Vânzări';
    protected static string|\UnitEnum|null $navigationGroup = 'Sales';
    protected static ?int $navigationSort = 5;
    protected string $view = 'filament.marketplace.pages.sales-report';

    /** Period preset: today, 7d, 30d, this_month, last_month, this_year, custom. */
    #[Url]
    public string $period = '30d';

    #[Url]
    public ?string $customFrom = null;

    #[Url]
    public ?string $customTo = null;

    /** Filter mode: 'event' or 'organizer'. */
    #[Url]
    public string $filterBy = 'event';

    /** @var array<int, int> */
    #[Url]
    public array $eventIds = [];

    #[Url]
    public ?int $organizerId = null;

    /** @var array<int, string> */
    #[Url]
    public array $statuses = ['paid', 'confirmed', 'completed'];

    #[Url]
    public string $viewMode = 'compact';

    #[Url]
    public string $dateColumn = 'paid_at';

    /** Filled after Generate is clicked. Null = no report yet. */
    public ?array $compactData = null;

    /** Page index for extended view. */
    public int $extendedPage = 1;

    /** Extended page size. */
    public int $extendedPerPage = 50;

    public ?int $extendedTotal = null;

    /** @var array<int, array<string, mixed>> */
    public array $extendedRows = [];

    public ?array $summary = null;

    public function mount(): void
    {
        // No auto-run — wait for the user to click Generate so we don't
        // burn N event-scope queries on every page load.
    }

    public function updatedPeriod(): void
    {
        if ($this->period !== 'custom') {
            $this->customFrom = null;
            $this->customTo = null;
        }
        $this->resetReport();
    }

    public function updatedFilterBy(): void
    {
        // Keep the dropdowns from being orphaned when switching modes —
        // organizer mode auto-resolves event ids when generating, and
        // vice-versa.
        if ($this->filterBy === 'event') {
            $this->organizerId = null;
        } else {
            $this->eventIds = [];
        }
        $this->resetReport();
    }

    public function updatedViewMode(): void
    {
        $this->resetReport();
    }

    public function updatedStatuses(): void
    {
        $this->resetReport();
    }

    /**
     * Multi-select options for the Events dropdown — scoped to the current
     * marketplace, sorted by event_date desc, capped to 500.
     *
     * @return array<int, string>
     */
    public function getEventOptionsProperty(): array
    {
        $marketplace = static::getMarketplaceClient();
        if (!$marketplace) return [];

        return Event::query()
            ->where('marketplace_client_id', $marketplace->id)
            ->whereNotNull('marketplace_organizer_id')
            ->orderByDesc('event_date')
            ->limit(500)
            ->get(['id', 'title', 'event_date'])
            ->mapWithKeys(function (Event $e) {
                $title = is_array($e->title) ? ($e->title['ro'] ?? $e->title['en'] ?? reset($e->title)) : $e->title;
                $date = $e->event_date?->format('d.m.Y') ?? '—';
                return [$e->id => "{$title} ({$date})"];
            })
            ->toArray();
    }

    /**
     * @return array<int, string>
     */
    public function getOrganizerOptionsProperty(): array
    {
        $marketplace = static::getMarketplaceClient();
        if (!$marketplace) return [];

        return MarketplaceOrganizer::query()
            ->where('marketplace_client_id', $marketplace->id)
            ->orderBy('name')
            ->get(['id', 'name', 'company_name'])
            ->mapWithKeys(fn (MarketplaceOrganizer $o) => [
                $o->id => $o->name . ($o->company_name ? " ({$o->company_name})" : ''),
            ])
            ->toArray();
    }

    /** @return array{0: \Carbon\Carbon, 1: \Carbon\Carbon} */
    protected function resolvePeriod(): array
    {
        $now = Carbon::now();
        return match ($this->period) {
            'today'      => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            '7d'         => [$now->copy()->subDays(7)->startOfDay(), $now->copy()->endOfDay()],
            '30d'        => [$now->copy()->subDays(30)->startOfDay(), $now->copy()->endOfDay()],
            'this_month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            'last_month' => [$now->copy()->subMonth()->startOfMonth(), $now->copy()->subMonth()->endOfMonth()],
            'this_year'  => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
            'custom'     => [
                $this->customFrom ? Carbon::parse($this->customFrom)->startOfDay() : $now->copy()->subDays(30)->startOfDay(),
                $this->customTo ? Carbon::parse($this->customTo)->endOfDay() : $now->copy()->endOfDay(),
            ],
            default      => [$now->copy()->subDays(30)->startOfDay(), $now->copy()->endOfDay()],
        };
    }

    /**
     * Resolve the event id list from the filter mode. Organizer-mode pulls
     * all events for that organizer scoped to the current marketplace.
     *
     * @return array<int, int>
     */
    protected function resolveEventIds(): array
    {
        $marketplace = static::getMarketplaceClient();
        if (!$marketplace) return [];

        if ($this->filterBy === 'organizer' && $this->organizerId) {
            return Event::query()
                ->where('marketplace_client_id', $marketplace->id)
                ->where('marketplace_organizer_id', $this->organizerId)
                ->pluck('id')
                ->all();
        }

        return array_values(array_filter(array_map('intval', $this->eventIds)));
    }

    public function generate(): void
    {
        $eventIds = $this->resolveEventIds();
        if (empty($eventIds) || empty($this->statuses)) {
            return;
        }

        [$from, $to] = $this->resolvePeriod();
        $service = app(SalesReportService::class);

        if ($this->viewMode === 'compact') {
            $this->compactData = $service->compact($eventIds, $from, $to, $this->statuses, $this->dateColumn);
            $this->extendedRows = [];
            $this->extendedTotal = null;
            $this->summary = $this->compactData['totals'] + ['orders' => $this->countOrders($eventIds, $from, $to)];
        } else {
            $query = $service->extendedQuery($eventIds, $from, $to, $this->statuses, $this->dateColumn);
            $this->extendedTotal = (clone $query)->count();
            $orders = $query
                ->skip(($this->extendedPage - 1) * $this->extendedPerPage)
                ->take($this->extendedPerPage)
                ->get();

            $this->extendedRows = $orders->map(fn ($o) => $service->extendedRow($o))->all();
            $this->compactData = null;

            $sumGross = (clone $query)->sum('total');
            $sumCommission = (clone $query)->sum('commission_amount');
            $this->summary = [
                'orders'     => $this->extendedTotal,
                'qty'        => array_sum(array_column($this->extendedRows, 'tickets')), // page-only — totals on the table footer represent the rendered page
                'gross'      => round((float) $sumGross, 2),
                'commission' => round((float) $sumCommission, 2),
                'net'        => round((float) $sumGross - (float) $sumCommission, 2),
            ];
        }
    }

    public function changeExtendedPage(int $page): void
    {
        $this->extendedPage = max(1, $page);
        if ($this->viewMode === 'extended') {
            $this->generate();
        }
    }

    protected function countOrders(array $eventIds, Carbon $from, Carbon $to): int
    {
        return \App\Models\Order::query()
            ->whereIn('marketplace_event_id', $eventIds)
            ->whereIn('status', $this->statuses)
            ->whereBetween($this->dateColumn, [$from, $to])
            ->count();
    }

    protected function resetReport(): void
    {
        $this->compactData = null;
        $this->extendedRows = [];
        $this->extendedTotal = null;
        $this->summary = null;
        $this->extendedPage = 1;
    }

    public function exportCsv(): StreamedResponse
    {
        $eventIds = $this->resolveEventIds();
        if (empty($eventIds) || empty($this->statuses)) {
            abort(422, 'Selectează un eveniment/organizator și cel puțin un status.');
        }
        [$from, $to] = $this->resolvePeriod();
        $service = app(SalesReportService::class);

        $mode = $this->viewMode;
        $filename = sprintf(
            '%s_report_%s_to_%s.csv',
            $mode,
            $from->format('Y-m-d'),
            $to->format('Y-m-d')
        );

        return response()->streamDownload(function () use ($mode, $service, $eventIds, $from, $to) {
            $out = fopen('php://output', 'w');
            // UTF-8 BOM so Excel reads diacritics correctly.
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

            if ($mode === 'compact') {
                $data = $service->compact($eventIds, $from, $to, $this->statuses, $this->dateColumn);
                fputcsv($out, ['Eveniment', 'Tip bilet', 'POS', 'Qty', 'Preț unitar', 'Brut', 'Comision', 'Discount', 'Extras', 'Net', 'Mod comision'], ',', '"', '\\');
                foreach ($data['rows'] as $r) {
                    fputcsv($out, [
                        $r['event_title'],
                        $r['ticket_type_name'],
                        $r['is_pos'] ? 'da' : 'nu',
                        $r['qty'],
                        number_format($r['price'], 2, '.', ''),
                        number_format($r['gross'], 2, '.', ''),
                        number_format($r['commission'], 2, '.', ''),
                        number_format($r['discount'], 2, '.', ''),
                        number_format($r['extras'], 2, '.', ''),
                        number_format($r['net'], 2, '.', ''),
                        $this->formatCommissionMode($r),
                    ], ',', '"', '\\');
                }
                fputcsv($out, [], ',', '"', '\\');
                fputcsv($out, [
                    'TOTAL (excl. POS)', '', '',
                    $data['totals']['qty'],
                    '',
                    number_format($data['totals']['gross'], 2, '.', ''),
                    number_format($data['totals']['commission'], 2, '.', ''),
                    number_format($data['totals']['discount'], 2, '.', ''),
                    number_format($data['totals']['extras'], 2, '.', ''),
                    number_format($data['totals']['net'], 2, '.', ''),
                    '',
                ], ',', '"', '\\');
            } else {
                fputcsv($out, [
                    '# Comandă', 'Data plății', 'Data creării', 'Eveniment',
                    'Client', 'Email', 'Bilete', 'Brut', 'Comision', 'Refund',
                    'Net', 'Status', 'Payment', 'Sursa',
                ], ',', '"', '\\');
                $service->extendedQuery($eventIds, $from, $to, $this->statuses, $this->dateColumn)
                    ->chunk(500, function ($orders) use ($out, $service) {
                        foreach ($orders as $o) {
                            $r = $service->extendedRow($o);
                            fputcsv($out, [
                                $r['order_number'],
                                $r['paid_at']?->format('d.m.Y H:i') ?? '',
                                $r['created_at']?->format('d.m.Y H:i') ?? '',
                                $r['event_title'],
                                $r['customer_name'],
                                $r['customer_email'],
                                $r['tickets'],
                                number_format($r['gross'], 2, '.', ''),
                                number_format($r['commission'], 2, '.', ''),
                                number_format($r['refund'], 2, '.', ''),
                                number_format($r['net'], 2, '.', ''),
                                $r['status'],
                                $r['payment_status'],
                                $r['source'] ?? '',
                            ], ',', '"', '\\');
                        }
                    });
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    protected function formatCommissionMode(array $row): string
    {
        $mode = $row['commission_mode'] ?? '';
        $label = match ($mode) {
            'added_on_top', 'on_top' => 'Peste preț',
            'included' => 'Inclus',
            default => $mode,
        };
        $type = $row['commission_type'] ?? null;
        $rate = $row['commission_rate'] ?? null;
        $fixed = $row['commission_fixed'] ?? null;
        $tail = match (true) {
            $type === 'percentage' && $rate !== null => "{$rate}%",
            $type === 'fixed' && $fixed !== null => number_format((float) $fixed, 2) . ' RON',
            $type === 'both' => trim(($rate !== null ? "{$rate}%" : '') . ($rate !== null && $fixed !== null ? ' + ' : '') . ($fixed !== null ? number_format((float) $fixed, 2) . ' RON' : '')),
            $rate !== null => "{$rate}%",
            default => '',
        };
        return $tail ? "{$label} ({$tail})" : $label;
    }
}
