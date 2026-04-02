<?php

namespace App\Filament\Tenant\Resources\Cashless;

use App\Filament\Tenant\Resources\Cashless\CashlessAccountResource\Pages;
use App\Models\Cashless\CashlessAccount;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CashlessAccountResource extends Resource
{
    protected static ?string $model = CashlessAccount::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-wallet';

    protected static ?string $navigationLabel = 'Cashless Accounts';

    protected static \UnitEnum|string|null $navigationGroup = 'Cashless';

    protected static ?int $navigationSort = 10;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('account_number')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('customer.first_name')->label('Customer')->searchable(),
                Tables\Columns\TextColumn::make('balance_cents')->label('Balance')
                    ->formatStateUsing(fn ($state) => number_format($state / 100, 2) . ' RON')->sortable(),
                Tables\Columns\TextColumn::make('total_topped_up_cents')->label('Total Top-ups')
                    ->formatStateUsing(fn ($state) => number_format($state / 100, 2))->toggleable(),
                Tables\Columns\TextColumn::make('total_spent_cents')->label('Total Spent')
                    ->formatStateUsing(fn ($state) => number_format($state / 100, 2))->toggleable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors(['success' => 'active', 'warning' => 'frozen', 'gray' => 'closed']),
                Tables\Columns\TextColumn::make('edition.name')->label('Edition')->sortable(),
                Tables\Columns\TextColumn::make('activated_at')->dateTime('d M Y H:i')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(['active' => 'Active', 'frozen' => 'Frozen', 'closed' => 'Closed']),
                Tables\Filters\SelectFilter::make('festival_edition_id')
                    ->label('Edition')->relationship('edition', 'name'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCashlessAccounts::route('/'),
        ];
    }
}
