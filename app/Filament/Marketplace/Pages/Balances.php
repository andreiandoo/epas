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

    public function table(Table $table): Table
    {
        $marketplace = static::getMarketplaceClient();

        return $table
            ->query(
                MarketplaceOrganizer::query()
                    ->where('marketplace_client_id', $marketplace?->id)
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Organizer')
                    ->searchable()
                    ->sortable()
                    ->url(fn ($record) => url('/marketplace/organizers/' . $record->id . '/balance'))
                    ->color('primary'),
                Tables\Columns\TextColumn::make('total_revenue')
                    ->label('Total Earned')
                    ->money('RON')
                    ->sortable()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('total_paid_out')
                    ->label('Total Paid')
                    ->money('RON')
                    ->sortable()
                    ->color('primary'),
                Tables\Columns\TextColumn::make('pending_balance')
                    ->label('Pending')
                    ->money('RON')
                    ->sortable()
                    ->color('warning'),
                Tables\Columns\TextColumn::make('available_balance')
                    ->label('Available')
                    ->money('RON')
                    ->sortable()
                    ->color('success')
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'pending' => 'warning',
                        'suspended' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->defaultSort('available_balance', 'desc');
    }
}
