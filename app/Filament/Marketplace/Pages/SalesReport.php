<?php

namespace App\Filament\Marketplace\Pages;

use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Models\Event;
use App\Models\MarketplaceOrganizer;
use App\Services\Marketplace\SalesReportService;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SalesReport extends Page implements HasForms
{
    use HasMarketplaceContext;
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-chart-bar';
    protected static ?string $navigationLabel = 'Raport Vânzări';
    protected static ?string $title = 'Raport Vânzări';
    protected static string|\UnitEnum|null $navigationGroup = 'Sales';
    protected static ?int $navigationSort = 5;
    protected string $view = 'filament.marketplace.pages.sales-report';

    /**
     * All filter state lives in $data so the Filament form owns it. Avoids
     * the TypeError class we saw when typed Livewire properties received
     * stale URL params from a previous filterBy mode.
     */
    public ?array $data = [];

    /** Result state — populated when the user clicks "Generează raport". */
    public ?array $compactData = null;
    /** @var array<int, array<string, mixed>> */
    public array $extendedRows = [];
    public ?int $extendedTotal = null;
    public int $extendedPage = 1;
    public int $extendedPerPage = 50;
    public ?array $summary = null;

    public function mount(): void
    {
        $this->form->fill([
            'period'      => '30d',
            'customFrom'  => null,
            'customTo'    => null,
            'filterBy'    => 'event',
            'eventIds'    => [],
            'organizerId' => null,
            'statuses'    => ['paid', 'confirmed', 'completed'],
            'viewMode'    => 'compact',
            'dateColumn'  => 'paid_at',
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Filtre raport')
                    ->description('Alege perioada, evenimentele și statusurile, apoi apasă "Generează raport".')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Radio::make('period')
                            ->label('Perioadă')
                            ->options([
                                'today'      => 'Azi',
                                '7d'         => '7 zile',
                                '30d'        => '30 zile',
                                'this_month' => 'Luna curentă',
                                'last_month' => 'Luna trecută',
                                'this_year'  => 'Anul curent',
                                'custom'     => 'Personalizat',
                            ])
                            ->inline()
                            ->columnSpanFull()
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set) {
                                if ($state !== 'custom') {
                                    $set('customFrom', null);
                                    $set('customTo', null);
                                }
                                $this->resetReport();
                            }),

                        Grid::make(2)
                            ->schema([
                                Forms\Components\DatePicker::make('customFrom')
                                    ->label('De la')
                                    ->live()
                                    ->afterStateUpdated(fn () => $this->resetReport()),
                                Forms\Components\DatePicker::make('customTo')
                                    ->label('Până la')
                                    ->live()
                                    ->afterStateUpdated(fn () => $this->resetReport()),
                            ])
                            ->visible(fn (Get $get) => $get('period') === 'custom')
                            ->columnSpanFull(),

                        Forms\Components\Radio::make('dateColumn')
                            ->label('Bază dată')
                            ->options([
                                'paid_at'    => 'După data plății',
                                'created_at' => 'După data creării',
                            ])
                            ->inline()
                            ->columnSpanFull()
                            ->live()
                            ->afterStateUpdated(fn () => $this->resetReport()),

                        Forms\Components\Radio::make('filterBy')
                            ->label('Selectează după')
                            ->options([
                                'event'     => 'Eveniment(e)',
                                'organizer' => 'Organizator',
                            ])
                            ->inline()
                            ->columnSpanFull()
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set) {
                                // Switching the mode wipes the other side's
                                // state so we don't leak ids across modes
                                // (also kills the URL TypeError class —
                                // organizerId can't end up an array).
                                if ($state === 'event') {
                                    $set('organizerId', null);
                                } else {
                                    $set('eventIds', []);
                                }
                                $this->resetReport();
                            }),

                        Forms\Components\Select::make('eventIds')
                            ->label('Evenimente')
                            ->multiple()
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search) => $this->searchEvents($search))
                            ->getOptionLabelsUsing(fn (array $values) => $this->resolveEventLabels($values))
                            ->placeholder('Caută evenimente după nume sau dată...')
                            ->helperText('Search server-side pe toate evenimentele clientului. Diacritice ignorate.')
                            ->columnSpanFull()
                            ->visible(fn (Get $get) => $get('filterBy') === 'event')
                            ->live()
                            ->afterStateUpdated(fn () => $this->resetReport()),

                        Forms\Components\Select::make('organizerId')
                            ->label('Organizator')
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search) => $this->searchOrganizers($search))
                            ->getOptionLabelUsing(fn ($value) => $this->resolveOrganizerLabel($value))
                            ->placeholder('Caută organizator după nume sau CUI...')
                            ->helperText('Toate evenimentele acestui organizator vor fi incluse.')
                            ->columnSpanFull()
                            ->visible(fn (Get $get) => $get('filterBy') === 'organizer')
                            ->live()
                            ->afterStateUpdated(fn () => $this->resetReport()),

                        Forms\Components\CheckboxList::make('statuses')
                            ->label('Status comenzi')
                            ->options([
                                'paid'                => 'Plătit',
                                'confirmed'           => 'Confirmat',
                                'completed'           => 'Finalizat',
                                'failed'              => 'Eșuat',
                                'expired'             => 'Expirat',
                                'cancelled'           => 'Anulat',
                                'refunded'            => 'Rambursat',
                                'partially_refunded'  => 'Rambursat parțial',
                                'pending'             => 'În așteptare',
                            ])
                            ->columns(3)
                            ->columnSpanFull()
                            ->live()
                            ->afterStateUpdated(fn () => $this->resetReport()),

                        Forms\Components\Radio::make('viewMode')
                            ->label('Mod afișare')
                            ->options([
                                'compact'  => 'Compact (per tip bilet)',
                                'extended' => 'Extins (per comandă)',
                            ])
                            ->inline()
                            ->columnSpanFull()
                            ->live()
                            ->afterStateUpdated(fn () => $this->resetReport()),
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('generate')
                ->label('Generează raport')
                ->icon('heroicon-o-play')
                ->color('primary')
                ->action(fn () => $this->generate()),
            Actions\Action::make('export')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->visible(fn () => $this->compactData !== null || !empty($this->extendedRows))
                ->action(fn () => $this->exportCsv()),
        ];
    }

    /**
     * Server-side event search. Uses Postgres' unaccent extension (already
     * enabled by 2026_03_23_100001) so a search like "dirtylicious" hits
     * "Dirtylicious Decade Tour" regardless of case or any diacritic noise.
     * Limit kept at 100 — Filament renders at most that many in the
     * dropdown anyway, and the user just types more to narrow.
     *
     * @return array<int, string>
     */
    protected function searchEvents(string $search): array
    {
        $marketplace = static::getMarketplaceClient();
        if (!$marketplace) return [];

        $needle = '%' . $search . '%';

        return Event::query()
            ->where('marketplace_client_id', $marketplace->id)
            ->whereNotNull('marketplace_organizer_id')
            ->whereRaw('LOWER(unaccent(title::text)) LIKE LOWER(unaccent(?))', [$needle])
            ->orderByDesc('event_date')
            ->limit(100)
            ->get(['id', 'title', 'event_date'])
            ->mapWithKeys(fn (Event $e) => [$e->id => $this->formatEventLabel($e)])
            ->toArray();
    }

    /**
     * Resolve labels for already-selected event ids so the chips render
     * with title + date instead of raw ids.
     *
     * @param array<int, int|string> $values
     * @return array<int, string>
     */
    protected function resolveEventLabels(array $values): array
    {
        if (empty($values)) return [];
        return Event::query()
            ->whereIn('id', $values)
            ->get(['id', 'title', 'event_date'])
            ->mapWithKeys(fn (Event $e) => [$e->id => $this->formatEventLabel($e)])
            ->toArray();
    }

    protected function formatEventLabel(Event $e): string
    {
        $title = is_array($e->title) ? ($e->title['ro'] ?? $e->title['en'] ?? reset($e->title)) : $e->title;
        $date = $e->event_date?->format('d.m.Y') ?? '—';
        return "{$title} ({$date})";
    }

    /** @return array<int, string> */
    protected function searchOrganizers(string $search): array
    {
        $marketplace = static::getMarketplaceClient();
        if (!$marketplace) return [];

        $needle = '%' . $search . '%';

        return MarketplaceOrganizer::query()
            ->where('marketplace_client_id', $marketplace->id)
            ->where(function ($q) use ($needle) {
                $q->whereRaw('LOWER(unaccent(name)) LIKE LOWER(unaccent(?))', [$needle])
                  ->orWhereRaw('LOWER(unaccent(coalesce(company_name, \'\'))) LIKE LOWER(unaccent(?))', [$needle])
                  ->orWhereRaw('coalesce(company_tax_id, \'\') ILIKE ?', [$needle]);
            })
            ->orderBy('name')
            ->limit(100)
            ->get(['id', 'name', 'company_name'])
            ->mapWithKeys(fn (MarketplaceOrganizer $o) => [
                $o->id => $o->name . ($o->company_name ? " ({$o->company_name})" : ''),
            ])
            ->toArray();
    }

    protected function resolveOrganizerLabel(int|string|null $value): ?string
    {
        if (!$value) return null;
        $org = MarketplaceOrganizer::find((int) $value);
        if (!$org) return null;
        return $org->name . ($org->company_name ? " ({$org->company_name})" : '');
    }

    /** @return array{0: \Carbon\Carbon, 1: \Carbon\Carbon} */
    protected function resolvePeriod(): array
    {
        $now = Carbon::now();
        $period = $this->data['period'] ?? '30d';
        return match ($period) {
            'today'      => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            '7d'         => [$now->copy()->subDays(7)->startOfDay(), $now->copy()->endOfDay()],
            '30d'        => [$now->copy()->subDays(30)->startOfDay(), $now->copy()->endOfDay()],
            'this_month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            'last_month' => [$now->copy()->subMonth()->startOfMonth(), $now->copy()->subMonth()->endOfMonth()],
            'this_year'  => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
            'custom'     => [
                !empty($this->data['customFrom']) ? Carbon::parse($this->data['customFrom'])->startOfDay() : $now->copy()->subDays(30)->startOfDay(),
                !empty($this->data['customTo']) ? Carbon::parse($this->data['customTo'])->endOfDay() : $now->copy()->endOfDay(),
            ],
            default      => [$now->copy()->subDays(30)->startOfDay(), $now->copy()->endOfDay()],
        };
    }

    /** @return array<int, int> */
    protected function resolveEventIds(): array
    {
        $marketplace = static::getMarketplaceClient();
        if (!$marketplace) return [];

        $filterBy = $this->data['filterBy'] ?? 'event';
        $organizerId = $this->data['organizerId'] ?? null;

        if ($filterBy === 'organizer' && $organizerId) {
            return Event::query()
                ->where('marketplace_client_id', $marketplace->id)
                ->where('marketplace_organizer_id', (int) $organizerId)
                ->pluck('id')
                ->all();
        }

        $eventIds = $this->data['eventIds'] ?? [];
        if (!is_array($eventIds)) {
            return [];
        }
        return array_values(array_filter(array_map('intval', $eventIds)));
    }

    public function generate(): void
    {
        $eventIds = $this->resolveEventIds();
        $statuses = $this->data['statuses'] ?? [];
        if (!is_array($statuses)) {
            $statuses = [];
        }
        if (empty($eventIds) || empty($statuses)) {
            \Filament\Notifications\Notification::make()
                ->title('Selecție incompletă')
                ->body('Alege cel puțin un eveniment/organizator și un status.')
                ->warning()
                ->send();
            return;
        }

        [$from, $to] = $this->resolvePeriod();
        $dateColumn = $this->data['dateColumn'] ?? 'paid_at';
        $viewMode = $this->data['viewMode'] ?? 'compact';
        $service = app(SalesReportService::class);

        if ($viewMode === 'compact') {
            $this->compactData = $service->compact($eventIds, $from, $to, $statuses, $dateColumn);
            $this->extendedRows = [];
            $this->extendedTotal = null;
            $this->summary = $this->compactData['totals'] + [
                'orders' => $this->countOrders($eventIds, $from, $to, $statuses, $dateColumn),
            ];
        } else {
            $query = $service->extendedQuery($eventIds, $from, $to, $statuses, $dateColumn);
            $this->extendedTotal = (clone $query)->count();
            $orders = $query
                ->skip(($this->extendedPage - 1) * $this->extendedPerPage)
                ->take($this->extendedPerPage)
                ->get();

            $this->extendedRows = $orders->map(fn ($o) => $service->extendedRow($o))->all();
            $this->compactData = null;

            // Summary must aggregate the *recomputed* commission/net per
            // order — order.commission_amount is unreliable across sources
            // (POS app writes per-ticket numbers, etc.) and would skew the
            // card. Walk all matching orders in chunks, run extendedRow on
            // each, and accumulate.
            $totals = ['orders' => 0, 'qty' => 0, 'gross' => 0.0, 'commission' => 0.0, 'discount' => 0.0, 'refund' => 0.0, 'net' => 0.0];
            (clone $query)->chunk(500, function ($chunk) use (&$totals, $service) {
                foreach ($chunk as $o) {
                    $r = $service->extendedRow($o);
                    $totals['orders']++;
                    $totals['qty']        += $r['tickets'];
                    $totals['gross']      += $r['gross'];
                    $totals['commission'] += $r['commission'];
                    $totals['discount']   += $r['discount'];
                    $totals['refund']     += $r['refund'];
                    $totals['net']        += $r['net'];
                }
            });
            $this->summary = [
                'orders'     => $totals['orders'],
                'qty'        => $totals['qty'],
                'gross'      => round($totals['gross'], 2),
                'commission' => round($totals['commission'], 2),
                'discount'   => round($totals['discount'], 2),
                'refund'     => round($totals['refund'], 2),
                'net'        => round($totals['net'], 2),
            ];
        }
    }

    public function changeExtendedPage(int $page): void
    {
        $this->extendedPage = max(1, $page);
        if (($this->data['viewMode'] ?? 'compact') === 'extended') {
            $this->generate();
        }
    }

    protected function countOrders(array $eventIds, Carbon $from, Carbon $to, array $statuses, string $dateColumn): int
    {
        // Orders may be linked through either column — same OR pattern as
        // SalesBreakdownService and SalesReportService::extendedQuery().
        // Without it the count showed 0 for events whose orders use the
        // legacy event_id column.
        return \App\Models\Order::query()
            ->where(fn ($q) => $q->whereIn('marketplace_event_id', $eventIds)
                                  ->orWhereIn('event_id', $eventIds))
            ->whereIn('status', $statuses)
            ->whereBetween($dateColumn, [$from, $to])
            ->count();
    }

    public function resetReport(): void
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
        $statuses = $this->data['statuses'] ?? [];
        if (empty($eventIds) || empty($statuses)) {
            abort(422, 'Selectează un eveniment/organizator și cel puțin un status.');
        }
        [$from, $to] = $this->resolvePeriod();
        $dateColumn = $this->data['dateColumn'] ?? 'paid_at';
        $viewMode = $this->data['viewMode'] ?? 'compact';
        $service = app(SalesReportService::class);

        $filename = sprintf(
            '%s_report_%s_to_%s.csv',
            $viewMode,
            $from->format('Y-m-d'),
            $to->format('Y-m-d')
        );

        return response()->streamDownload(function () use ($viewMode, $service, $eventIds, $from, $to, $statuses, $dateColumn) {
            $out = fopen('php://output', 'w');
            // UTF-8 BOM so Excel reads diacritics correctly.
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

            if ($viewMode === 'compact') {
                $data = $service->compact($eventIds, $from, $to, $statuses, $dateColumn);
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
                    'Client', 'Email', 'Bilete', 'Brut', 'Comision', 'Mod comision',
                    'Discount', 'Cod reducere', 'Refund', 'Net', 'Status',
                    'Payment', 'Sursa',
                ], ',', '"', '\\');
                $service->extendedQuery($eventIds, $from, $to, $statuses, $dateColumn)
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
                                $r['commission_mode'] ?? '',
                                number_format($r['discount'] ?? 0, 2, '.', ''),
                                $r['promo_code'] ?? '',
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
