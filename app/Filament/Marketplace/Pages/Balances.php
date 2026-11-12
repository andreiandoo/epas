<?php

namespace App\Filament\Marketplace\Pages;

use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Jobs\LegacySettlementJob;
use App\Models\MarketplaceOrganizer;
use App\Services\Marketplace\LegacySettlementService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;

class Balances extends Page implements HasForms, HasTable
{
    use HasMarketplaceContext;
    use InteractsWithForms;
    use InteractsWithTable;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel = 'Balanțe';
    protected static ?string $title = 'Balanțe organizatori';
    protected static \UnitEnum|string|null $navigationGroup = 'Organizers';
    protected static ?int $navigationSort = 3;
    protected string $view = 'filament.marketplace.pages.balances';

    /** Filtru rapid: all | pending | available */
    #[Url]
    public string $quick = 'all';

    public function updatedQuick(): void
    {
        $this->resetPage();
    }

    protected function baseQuery(): Builder
    {
        return MarketplaceOrganizer::query()
            ->where('marketplace_client_id', static::getMarketplaceClient()?->id);
    }

    protected function applyQuickFilter(Builder $query, string $quick): Builder
    {
        return match ($quick) {
            'pending' => $query->where('pending_balance', '>', 0.004),
            'available' => $query->where('available_balance', '>', 0.004),
            default => $query,
        };
    }

    /** Header propriu: titlul în stânga, cardurile cu totaluri în dreapta. */
    public function getHeader(): ?\Illuminate\Contracts\View\View
    {
        $totals = $this->baseQuery()
            ->selectRaw('COALESCE(SUM(pending_balance), 0) as pending, COALESCE(SUM(available_balance), 0) as available')
            ->toBase()
            ->first();

        $pending = (float) ($totals->pending ?? 0);
        $available = (float) ($totals->available ?? 0);

        return view('filament.marketplace.pages.balances-header', [
            'heading' => $this->getHeading(),
            'cards' => [
                ['label' => 'În procesare', 'value' => $pending, 'color' => '#d97706'],
                ['label' => 'Sold disponibil', 'value' => $available, 'color' => '#059669'],
                ['label' => 'Total (procesare + disponibil)', 'value' => $pending + $available, 'color' => null],
            ],
        ]);
    }

    /** Filtrele rapide de deasupra tabelului, cu numărul de organizatori. */
    public function quickFilters(): array
    {
        return [
            'all' => ['label' => 'Toți', 'count' => $this->baseQuery()->count()],
            'pending' => ['label' => 'Cu sume în procesare', 'count' => $this->applyQuickFilter($this->baseQuery(), 'pending')->count()],
            'available' => ['label' => 'Cu sold disponibil', 'count' => $this->applyQuickFilter($this->baseQuery(), 'available')->count()],
        ];
    }

    // ---------------------------------------------------------------
    // Soldurile din trecut (LegacySettlementService) — doar AmBilet
    // ---------------------------------------------------------------

    public const LEGACY_VERDICTS = [
        LegacySettlementService::VERDICT_OK => ['OK', 'success'],
        LegacySettlementService::VERDICT_WARN => ['Atenție', 'warning'],
        LegacySettlementService::VERDICT_BLOCKED => ['Blocat', 'danger'],
        LegacySettlementService::VERDICT_SETTLED => ['Rezolvat', 'info'],
        LegacySettlementService::VERDICT_NONE => ['—', 'gray'],
    ];

    public function legacyEnabled(): bool
    {
        return LegacySettlementService::appliesTo(static::getMarketplaceClient()?->id);
    }

    protected function currentAdmin(): ?\Illuminate\Database\Eloquent\Model
    {
        return Auth::guard('marketplace_admin')->user();
    }

    protected function legacyQueueKey(): string
    {
        return 'legacy_settle:queue:' . static::getMarketplaceClient()?->id;
    }

    /** Câți organizatori mai sunt în coada de analiză / rezolvare. */
    public function legacyQueueCount(): int
    {
        return $this->legacyEnabled() ? max(0, (int) Cache::get($this->legacyQueueKey(), 0)) : 0;
    }

    /** @return array<int> organizatorii cu verdictul dat (din cache) */
    protected function organizerIdsWithVerdict(string $verdict): array
    {
        $ids = $this->baseQuery()->pluck('id')->all();
        $cached = Cache::many(array_map(fn ($id) => LegacySettlementService::verdictKey($id), $ids));

        return collect($ids)->filter(function ($id) use ($cached, $verdict) {
            $v = $cached[LegacySettlementService::verdictKey($id)]['verdict'] ?? null;

            return $verdict === 'unknown' ? $v === null : $v === $verdict;
        })->values()->all();
    }

    /** Randat în view (pagina are header propriu, fără acțiuni de header). */
    public function legacyAnalyzeAllAction(): Actions\Action
    {
        return Actions\Action::make('legacyAnalyzeAll')
                ->label('Analizează soldurile din trecut')
                ->icon('heroicon-o-magnifying-glass')
                ->color('gray')
                ->visible(fn () => $this->legacyEnabled())
                ->requiresConfirmation()
                ->modalHeading('Analizează soldurile din trecut')
                ->modalDescription('Verifică, pentru fiecare organizator, deconturile rămase deschise pe evenimentele încheiate până pe '
                    . \Carbon\Carbon::parse(LegacySettlementService::CUTOFF_DATE)->format('d.m.Y')
                    . '. Rulează în fundal; rezultatul apare în coloana „Trecut”. Nu modifică nimic.')
                ->modalSubmitActionLabel('Pornește analiza')
                ->action(function () {
                    $marketplaceId = (int) static::getMarketplaceClient()->id;
                    $candidates = LegacySettlementService::candidateOrganizerIds($marketplaceId);

                    // Ultima acțiune pe fiecare organizator (rezolvat / anulat).
                    $lastRuns = DB::table('activity_log')
                        ->where('log_name', LegacySettlementService::LOG_NAME)
                        ->where('subject_type', (new MarketplaceOrganizer)->getMorphClass())
                        ->orderBy('id')
                        ->get(['subject_id', 'description'])
                        ->keyBy('subject_id');

                    // Fără deconturi vechi deschise: verdict direct, fără job.
                    foreach ($this->baseQuery()->whereNotIn('id', $candidates)->pluck('id') as $id) {
                        $settled = ($lastRuns[$id]->description ?? null) === 'settled';
                        Cache::put(LegacySettlementService::verdictKey($id), [
                            'verdict' => $settled ? LegacySettlementService::VERDICT_SETTLED : LegacySettlementService::VERDICT_NONE,
                            'events' => 0, 'payouts' => 0, 'settle_amount' => 0, 'issues' => 0,
                            'at' => now()->toIso8601String(),
                        ], now()->addDays(30));
                    }

                    $ids = $this->baseQuery()->whereIn('id', $candidates)->pluck('id');
                    Cache::put($this->legacyQueueKey(), $ids->count(), now()->addDay());
                    foreach ($ids as $id) {
                        LegacySettlementJob::dispatch((int) $id, 'analyze');
                    }

                    Notification::make()->success()
                        ->title('Analiza a pornit')
                        ->body($ids->count() . ' organizatori cu deconturi vechi deschise se analizează în fundal. Coloana „Trecut” se completează pe măsură ce se termină.')
                        ->send();
                });
    }

    protected function legacyAnalyzeAction(): Actions\Action
    {
        return Actions\Action::make('legacy_analyze')
            ->label('Analizează')
            ->icon('heroicon-m-magnifying-glass')
            ->color('gray')
            ->size('sm')
            ->visible(fn () => $this->legacyEnabled())
            ->slideOver()
            ->modalWidth('5xl')
            ->modalHeading(fn ($record) => 'Soldurile din trecut — ' . $record->name)
            ->modalContent(fn ($record) => view('filament.marketplace.pages.legacy-settlement-analysis', [
                'a' => app(LegacySettlementService::class)->analyze($record),
                'cutoff' => \Carbon\Carbon::parse(LegacySettlementService::CUTOFF_DATE)->format('d.m.Y'),
                'warnLimit' => LegacySettlementService::WARN_ADJUSTMENT,
            ]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Închide');
    }

    protected function legacySettleAction(): Actions\Action
    {
        return Actions\Action::make('legacy_settle')
            ->label('Rezolvă')
            ->icon('heroicon-m-check-badge')
            ->color('success')
            ->size('sm')
            ->visible(fn ($record) => $this->legacyEnabled()
                && in_array(LegacySettlementService::cachedVerdict($record->id)['verdict'] ?? null, [LegacySettlementService::VERDICT_OK, LegacySettlementService::VERDICT_WARN], true))
            ->requiresConfirmation()
            ->modalHeading(fn ($record) => 'Rezolvă soldurile din trecut — ' . $record->name)
            ->modalDescription(function ($record) {
                $v = LegacySettlementService::cachedVerdict($record->id) ?? [];

                return 'Marchez plătite ' . ($v['payouts'] ?? 0) . ' deconturi, în total '
                    . number_format((float) ($v['settle_amount'] ?? 0), 2, ',', '.') . ' lei, pentru ' . ($v['events'] ?? 0)
                    . ' evenimente încheiate până pe ' . \Carbon\Carbon::parse(LegacySettlementService::CUTOFF_DATE)->format('d.m.Y')
                    . '. Soldul acestor evenimente se închide la 0. Organizatorul nu primește email. Analiza se reface înainte de scriere.';
            })
            ->form(fn ($record) => (LegacySettlementService::cachedVerdict($record->id)['verdict'] ?? null) === LegacySettlementService::VERDICT_WARN
                ? [Forms\Components\Checkbox::make('confirm_warn')
                    ->label('Am verificat ajustările de peste ' . number_format(LegacySettlementService::WARN_ADJUSTMENT, 0, ',', '.') . ' lei din analiză')
                    ->accepted()]
                : [])
            ->modalSubmitActionLabel('Rezolvă')
            ->action(function ($record, array $data) {
                try {
                    $a = app(LegacySettlementService::class)->settle($record, $this->currentAdmin(), (bool) ($data['confirm_warn'] ?? false));
                    Notification::make()->success()->title('Rezolvat')
                        ->body($a['totals']['payouts'] . ' deconturi marcate plătite (' . number_format($a['totals']['settle_amount'], 2, ',', '.') . ' lei). Soldul a fost recalculat.')
                        ->send();
                } catch (\Throwable $e) {
                    Notification::make()->danger()->title('Nu s-a rezolvat')->body($e->getMessage())->persistent()->send();
                }
            });
    }

    protected function legacyRevertAction(): Actions\Action
    {
        return Actions\Action::make('legacy_revert')
            ->label('Anulează rezolvarea')
            ->icon('heroicon-m-arrow-uturn-left')
            ->color('danger')
            ->size('sm')
            ->link()
            ->visible(fn ($record) => $this->legacyEnabled()
                && (LegacySettlementService::cachedVerdict($record->id)['verdict'] ?? null) === LegacySettlementService::VERDICT_SETTLED)
            ->requiresConfirmation()
            ->modalHeading(fn ($record) => 'Anulează rezolvarea — ' . $record->name)
            ->modalDescription('Deconturile marcate plătite la rezolvare revin exact cum erau (status, sumă, note). Soldul se recalculează.')
            ->modalSubmitActionLabel('Anulează rezolvarea')
            ->action(function ($record) {
                try {
                    $n = app(LegacySettlementService::class)->revert($record, $this->currentAdmin());
                    Notification::make()->success()->title('Rezolvare anulată')->body($n . ' deconturi readuse la starea anterioară.')->send();
                } catch (\Throwable $e) {
                    Notification::make()->danger()->title('Nu s-a anulat')->body($e->getMessage())->persistent()->send();
                }
            });
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => $this->applyQuickFilter($this->baseQuery(), $this->quick))
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Organizator')
                    ->searchable()
                    ->sortable()
                    ->url(fn ($record) => url('/marketplace/organizers/' . $record->id . '/balance'))
                    ->color('primary'),
                // Total vânzări (net) = disponibil + în procesare + plătit — aceeași
                // relație ca pe pagina organizatorului. Coloana veche total_revenue
                // (Σ orders.total, cu comision) nu avea legătură cu soldul.
                Tables\Columns\TextColumn::make('net_sales')
                    ->label('Total vânzări (net)')
                    ->state(fn ($record) => (float) $record->available_balance + (float) $record->pending_balance + (float) $record->total_paid_out)
                    ->money('RON', locale: 'ro')
                    ->sortable(query: fn ($query, string $direction) => $query->orderByRaw(
                        '(COALESCE(available_balance, 0) + COALESCE(pending_balance, 0) + COALESCE(total_paid_out, 0)) ' . ($direction === 'asc' ? 'asc' : 'desc')
                    ))
                    ->color('gray'),
                Tables\Columns\TextColumn::make('total_paid_out')
                    ->label('Total plătit')
                    ->money('RON', locale: 'ro')
                    ->sortable()
                    ->color('primary'),
                Tables\Columns\TextColumn::make('pending_balance')
                    ->label('În procesare')
                    ->money('RON', locale: 'ro')
                    ->sortable()
                    ->color('warning'),
                Tables\Columns\TextColumn::make('available_balance')
                    ->label('Sold disponibil')
                    ->money('RON', locale: 'ro')
                    ->sortable()
                    ->color(fn ($state) => (float) $state < 0 ? 'danger' : 'success')
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('legacy_verdict')
                    ->label('Trecut')
                    ->visible(fn () => $this->legacyEnabled())
                    ->badge()
                    ->state(fn ($record) => LegacySettlementService::cachedVerdict($record->id)['verdict'] ?? 'unknown')
                    ->formatStateUsing(fn (string $state) => static::LEGACY_VERDICTS[$state][0] ?? 'Neanalizat')
                    ->color(fn (string $state) => static::LEGACY_VERDICTS[$state][1] ?? 'gray')
                    ->tooltip(function ($record) {
                        $v = LegacySettlementService::cachedVerdict($record->id);
                        if (! $v) {
                            return 'Neanalizat încă';
                        }
                        $at = \Carbon\Carbon::parse($v['at'])->format('d.m.Y H:i');

                        return $v['events'] > 0
                            ? "{$v['events']} evenimente, {$v['payouts']} deconturi, " . number_format((float) $v['settle_amount'], 2, ',', '.') . ' lei'
                                . ($v['issues'] ? ", {$v['issues']} probleme" : '') . " · analizat {$at}"
                            : "Analizat {$at}";
                    }),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'active' => 'Activ',
                        'pending' => 'În așteptare',
                        'suspended' => 'Suspendat',
                        'inactive' => 'Inactiv',
                        default => (string) $state,
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'active' => 'success',
                        'pending' => 'warning',
                        'suspended' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('legacy_verdict')
                    ->label('Trecut')
                    ->visible(fn () => $this->legacyEnabled())
                    ->options([
                        LegacySettlementService::VERDICT_OK => 'OK',
                        LegacySettlementService::VERDICT_WARN => 'Atenție',
                        LegacySettlementService::VERDICT_BLOCKED => 'Blocat',
                        LegacySettlementService::VERDICT_SETTLED => 'Rezolvat',
                        LegacySettlementService::VERDICT_NONE => 'Nimic de rezolvat',
                        'unknown' => 'Neanalizat',
                    ])
                    ->query(fn (Builder $query, array $data) => filled($data['value'] ?? null)
                        ? $query->whereIn('id', $this->organizerIdsWithVerdict($data['value']))
                        : $query),
            ])
            ->recordActions([
                $this->legacyAnalyzeAction(),
                $this->legacySettleAction(),
                $this->legacyRevertAction(),
            ])
            ->toolbarActions([
                Actions\BulkAction::make('legacy_settle_selected')
                    ->label('Rezolvă selectate (doar OK)')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn () => $this->legacyEnabled())
                    ->requiresConfirmation()
                    ->modalHeading('Rezolvă soldurile din trecut')
                    ->modalDescription('Se rezolvă doar organizatorii selectați cu verdictul OK; ceilalți sunt săriți. Rulează în fundal, iar fiecare organizator e reanalizat înainte de scriere. Organizatorii nu primesc email.')
                    ->modalSubmitActionLabel('Rezolvă')
                    ->action(function (Collection $records) {
                        $ok = $records->filter(fn ($r) => (LegacySettlementService::cachedVerdict($r->id)['verdict'] ?? null) === LegacySettlementService::VERDICT_OK);
                        $adminId = $this->currentAdmin()?->getKey();

                        Cache::put($this->legacyQueueKey(), $this->legacyQueueCount() + $ok->count(), now()->addDay());
                        foreach ($ok as $r) {
                            LegacySettlementJob::dispatch((int) $r->id, 'settle', $adminId);
                        }

                        $skipped = $records->count() - $ok->count();
                        Notification::make()->success()
                            ->title('Rezolvarea a pornit')
                            ->body($ok->count() . ' organizatori se rezolvă în fundal' . ($skipped > 0 ? '; ' . $skipped . ' săriți (nu sunt OK)' : '') . '.')
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion(),
            ])
            ->defaultSort('available_balance', 'desc');
    }
}
