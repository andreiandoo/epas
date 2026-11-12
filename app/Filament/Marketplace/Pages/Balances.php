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
        LegacySettlementService::VERDICT_SETTLED => ['Aprobat', 'info'],
        LegacySettlementService::VERDICT_NONE => ['—', 'gray'],
        'queued' => ['În lucru…', 'gray'],
    ];

    public function legacyEnabled(): bool
    {
        // Până rulează migrarea tabelului de verdicte, funcția rămâne ascunsă.
        return LegacySettlementService::appliesTo(static::getMarketplaceClient()?->id)
            && LegacySettlementService::tableReady();
    }

    protected function currentAdmin(): ?\Illuminate\Database\Eloquent\Model
    {
        return Auth::guard('marketplace_admin')->user();
    }

    /** Câți organizatori mai sunt în coada de analiză / rezolvare. */
    public function legacyQueueCount(): int
    {
        return $this->legacyEnabled() ? LegacySettlementService::queuedCount($this->baseQuery()->pluck('id')->all()) : 0;
    }

    /** @return array<int> organizatorii cu verdictul dat */
    protected function organizerIdsWithVerdict(string $verdict): array
    {
        $all = LegacySettlementService::allVerdicts();

        return $this->baseQuery()->pluck('id')->filter(function ($id) use ($all, $verdict) {
            $v = $all[$id]['verdict'] ?? null;

            return $verdict === 'unknown' ? $v === null : $v === $verdict;
        })->values()->all();
    }

    /** Randat în view (pagina are header propriu, fără acțiuni de header). */
    public function legacyAnalyzeAllAction(): Actions\Action
    {
        return Actions\Action::make('legacyAnalyzeAll')
                ->label('Analizează ciornele vechi')
                ->icon('heroicon-o-magnifying-glass')
                ->color('gray')
                ->visible(fn () => $this->legacyEnabled())
                ->requiresConfirmation()
                ->modalHeading('Analizează ciornele vechi')
                ->modalDescription('Verifică, pentru fiecare organizator, ciornele de decont rămase „În așteptare” pe evenimentele încheiate până pe '
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
                        LegacySettlementService::putVerdict((int) $id, $settled ? LegacySettlementService::VERDICT_SETTLED : LegacySettlementService::VERDICT_NONE);
                    }

                    $ids = $this->baseQuery()->whereIn('id', $candidates)->pluck('id');
                    LegacySettlementService::markQueued($ids->all(), 'analyze');
                    foreach ($ids as $id) {
                        LegacySettlementJob::dispatch((int) $id, 'analyze');
                    }

                    Notification::make()->success()
                        ->title('Analiza a pornit')
                        ->body($ids->count() . ' organizatori cu ciorne vechi se analizează în fundal. Coloana „Trecut” se completează pe măsură ce se termină.')
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
            ->modalHeading(fn ($record) => 'Ciornele vechi — ' . $record->name)
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
            ->label('Aprobă')
            ->icon('heroicon-m-check-badge')
            ->color('success')
            ->size('sm')
            ->visible(fn ($record) => $this->legacyEnabled()
                && in_array(LegacySettlementService::cachedVerdict($record->id)['verdict'] ?? null, [LegacySettlementService::VERDICT_OK, LegacySettlementService::VERDICT_WARN], true))
            ->requiresConfirmation()
            ->modalHeading(fn ($record) => 'Aprobă ciornele vechi — ' . $record->name)
            ->modalDescription(function ($record) {
                $v = LegacySettlementService::cachedVerdict($record->id) ?? [];

                return 'Aprob ' . ($v['payouts'] ?? 0) . ' ciorne, în total '
                    . number_format((float) ($v['settle_amount'] ?? 0), 2, ',', '.') . ' lei, pe evenimente încheiate până pe '
                    . \Carbon\Carbon::parse(LegacySettlementService::CUTOFF_DATE)->format('d.m.Y')
                    . '. Trec pe organizatorul evenimentului, cu suma adusă la netul evenimentului, și intră „în procesare”. Plata se finalizează apoi manual. Fără PDF, fără email. Analiza se reface înainte de scriere.';
            })
            ->form(fn ($record) => (LegacySettlementService::cachedVerdict($record->id)['verdict'] ?? null) === LegacySettlementService::VERDICT_WARN
                ? [Forms\Components\Checkbox::make('confirm_warn')
                    ->label('Am verificat ajustările de peste ' . number_format(LegacySettlementService::WARN_ADJUSTMENT, 0, ',', '.') . ' lei din analiză')
                    ->accepted()]
                : [])
            ->modalSubmitActionLabel('Aprobă')
            ->action(function ($record, array $data) {
                try {
                    $a = app(LegacySettlementService::class)->settle($record, $this->currentAdmin(), (bool) ($data['confirm_warn'] ?? false));
                    Notification::make()->success()->title('Aprobat')
                        ->body($a['totals']['payouts'] . ' ciorne aprobate (' . number_format($a['totals']['settle_amount'], 2, ',', '.') . ' lei în procesare). Soldul a fost recalculat.')
                        ->send();
                } catch (\Throwable $e) {
                    Notification::make()->danger()->title('Nu s-a aprobat')->body($e->getMessage())->persistent()->send();
                }
            });
    }

    protected function legacyRevertAction(): Actions\Action
    {
        return Actions\Action::make('legacy_revert')
            ->label('Anulează aprobarea')
            ->icon('heroicon-m-arrow-uturn-left')
            ->color('danger')
            ->size('sm')
            ->link()
            ->visible(fn ($record) => $this->legacyEnabled()
                && (LegacySettlementService::cachedVerdict($record->id)['verdict'] ?? null) === LegacySettlementService::VERDICT_SETTLED)
            ->requiresConfirmation()
            ->modalHeading(fn ($record) => 'Anulează aprobarea — ' . $record->name)
            ->modalDescription('Ciornele aprobate și încă neplătite revin exact cum erau: „În așteptare”, cu suma, organizatorul și notele inițiale. Cele deja finalizate ca plătite rămân. Soldul se recalculează.')
            ->modalSubmitActionLabel('Anulează aprobarea')
            ->action(function ($record) {
                try {
                    $n = app(LegacySettlementService::class)->revert($record, $this->currentAdmin());
                    Notification::make()->success()->title('Aprobare anulată')->body($n . ' ciorne readuse la starea anterioară.')->send();
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
                    ->state(function ($record) {
                        $v = LegacySettlementService::allVerdicts()[$record->id] ?? null;

                        return ($v['queued'] ?? false) ? 'queued' : ($v['verdict'] ?? 'unknown');
                    })
                    ->formatStateUsing(fn (string $state) => static::LEGACY_VERDICTS[$state][0] ?? 'Neanalizat')
                    ->color(fn (string $state) => static::LEGACY_VERDICTS[$state][1] ?? 'gray')
                    ->tooltip(function ($record) {
                        $v = LegacySettlementService::cachedVerdict($record->id);
                        if (! $v) {
                            return 'Neanalizat încă';
                        }
                        $at = $v['at'] ? \Carbon\Carbon::parse($v['at'])->format('d.m.Y H:i') : '—';

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
                        LegacySettlementService::VERDICT_SETTLED => 'Aprobat',
                        LegacySettlementService::VERDICT_NONE => 'Nimic de aprobat',
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
                    ->label('Aprobă selectate (doar OK)')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn () => $this->legacyEnabled())
                    ->requiresConfirmation()
                    ->modalHeading('Aprobă ciornele vechi')
                    ->modalDescription('Se aprobă doar organizatorii selectați cu verdictul OK; ceilalți sunt săriți. Rulează în fundal, iar fiecare organizator e reanalizat înainte de scriere. Fără PDF, fără email.')
                    ->modalSubmitActionLabel('Aprobă')
                    ->action(function (Collection $records) {
                        $ok = $records->filter(fn ($r) => (LegacySettlementService::cachedVerdict($r->id)['verdict'] ?? null) === LegacySettlementService::VERDICT_OK);
                        $adminId = $this->currentAdmin()?->getKey();

                        LegacySettlementService::markQueued($ok->pluck('id')->all(), 'settle');
                        foreach ($ok as $r) {
                            LegacySettlementJob::dispatch((int) $r->id, 'settle', $adminId);
                        }

                        $skipped = $records->count() - $ok->count();
                        Notification::make()->success()
                            ->title('Aprobarea a pornit')
                            ->body($ok->count() . ' organizatori se aprobă în fundal' . ($skipped > 0 ? '; ' . $skipped . ' săriți (nu sunt OK)' : '') . '.')
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion(),
            ])
            ->defaultSort('available_balance', 'desc');
    }
}
