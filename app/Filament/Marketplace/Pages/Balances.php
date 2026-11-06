<?php

namespace App\Filament\Marketplace\Pages;

use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Models\MarketplaceOrganizer;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
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
            ->defaultSort('available_balance', 'desc');
    }
}
