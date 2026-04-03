<?php

namespace App\Filament\Tenant\Resources\Cashless;

use App\Enums\TenantType;
use App\Filament\Tenant\Resources\Cashless\CashlessAccountResource\Pages;
use App\Models\Cashless\CashlessAccount;
use Filament\Actions;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CashlessAccountResource extends Resource
{
    protected static ?string $model = CashlessAccount::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-wallet';

    protected static ?string $navigationLabel = 'Cashless Accounts';

    protected static \UnitEnum|string|null $navigationGroup = 'Cashless';

    protected static ?int $navigationSort = 10;

    public static function shouldRegisterNavigation(): bool
    {
        $tenant = auth()->user()?->tenant;
        return $tenant && $tenant->tenant_type === TenantType::Festival;
    }

    public static function getEloquentQuery(): Builder
    {
        $tenant = auth()->user()->tenant;
        return parent::getEloquentQuery()->where('tenant_id', $tenant?->id);
    }

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
                    ->formatStateUsing(fn ($state) => number_format(($state ?? 0) / 100, 2) . ' RON')->toggleable(),
                Tables\Columns\TextColumn::make('total_spent_cents')->label('Total Spent')
                    ->formatStateUsing(fn ($state) => number_format(($state ?? 0) / 100, 2) . ' RON')->toggleable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors(['success' => 'active', 'warning' => 'frozen', 'gray' => 'closed']),
                Tables\Columns\TextColumn::make('edition.name')->label('Edition')->sortable(),
                Tables\Columns\TextColumn::make('activated_at')->dateTime('d M Y H:i')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(['active' => 'Active', 'frozen' => 'Frozen', 'closed' => 'Closed']),
                Tables\Filters\TernaryFilter::make('has_balance')
                    ->label('Available Balance')
                    ->queries(
                        true: fn (Builder $query) => $query->where('balance_cents', '>', 0),
                        false: fn (Builder $query) => $query->where('balance_cents', '<=', 0),
                    ),
                Tables\Filters\SelectFilter::make('festival_edition_id')
                    ->label('Edition')->relationship('edition', 'name'),
            ])
            ->actions([
                Actions\ViewAction::make()
                    ->infolist([
                        Infolists\Components\Grid::make(2)->schema([
                            Infolists\Components\Section::make('Account')->schema([
                                Infolists\Components\TextEntry::make('account_number')->label('Account'),
                                Infolists\Components\TextEntry::make('status')->badge()
                                    ->color(fn ($state) => match($state?->value ?? $state) {
                                        'active' => 'success', 'frozen' => 'warning', 'closed' => 'gray', default => 'gray',
                                    }),
                                Infolists\Components\TextEntry::make('edition.name')->label('Edition'),
                                Infolists\Components\TextEntry::make('wristband.uid')->label('Wristband'),
                                Infolists\Components\TextEntry::make('activated_at')->label('Activated')->dateTime('d M Y H:i'),
                            ]),
                            Infolists\Components\Section::make('Customer')->schema([
                                Infolists\Components\TextEntry::make('customer.first_name')->label('First Name'),
                                Infolists\Components\TextEntry::make('customer.last_name')->label('Last Name'),
                                Infolists\Components\TextEntry::make('customer.email')->label('Email'),
                                Infolists\Components\TextEntry::make('customer.phone')->label('Phone'),
                            ]),
                        ]),
                        Infolists\Components\Grid::make(4)->schema([
                            Infolists\Components\TextEntry::make('balance_cents')->label('Balance')
                                ->formatStateUsing(fn ($state) => number_format(($state ?? 0) / 100, 2) . ' RON'),
                            Infolists\Components\TextEntry::make('total_topped_up_cents')->label('Top-ups')
                                ->formatStateUsing(fn ($state) => number_format(($state ?? 0) / 100, 2) . ' RON'),
                            Infolists\Components\TextEntry::make('total_spent_cents')->label('Spent')
                                ->formatStateUsing(fn ($state) => number_format(($state ?? 0) / 100, 2) . ' RON'),
                            Infolists\Components\TextEntry::make('total_cashed_out_cents')->label('Cashed Out')
                                ->formatStateUsing(fn ($state) => number_format(($state ?? 0) / 100, 2) . ' RON'),
                        ]),
                    ]),
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
