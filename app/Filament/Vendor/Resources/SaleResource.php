<?php

namespace App\Filament\Vendor\Resources;

use App\Filament\Vendor\Resources\SaleResource\Pages;
use App\Models\Cashless\CashlessSale;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class SaleResource extends Resource
{
    protected static ?string $model = CashlessSale::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Sales';

    protected static ?int $navigationSort = 20;

    protected static ?string $slug = 'sales';

    public static function canAccess(): bool
    {
        $employee = Auth::guard('vendor_employee')->user();

        return $employee && in_array($employee->role, ['manager', 'supervisor', 'admin']);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $employee = Auth::guard('vendor_employee')->user();

        return parent::getEloquentQuery()
            ->where('vendor_id', $employee->vendor_id);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sale_number')
                    ->label('Sale #')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('sold_at')
                    ->label('Date')
                    ->dateTime('d M Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('items_count')
                    ->label('Items')
                    ->sortable(),

                Tables\Columns\TextColumn::make('subtotal_cents')
                    ->label('Subtotal')
                    ->formatStateUsing(fn ($state) => number_format($state / 100, 2))
                    ->sortable(),

                Tables\Columns\TextColumn::make('tax_cents')
                    ->label('Tax')
                    ->formatStateUsing(fn ($state) => number_format($state / 100, 2))
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('total_cents')
                    ->label('Total')
                    ->formatStateUsing(fn ($state) => number_format($state / 100, 2) . ' RON')
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('tip_cents')
                    ->label('Tip')
                    ->formatStateUsing(fn ($state) => $state > 0 ? number_format($state / 100, 2) : '-')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('commission_cents')
                    ->label('Commission')
                    ->formatStateUsing(fn ($state) => number_format($state / 100, 2))
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('employee.name')
                    ->label('Employee')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => 'completed',
                        'danger'  => 'voided',
                        'warning' => 'partial_refund',
                        'gray'    => 'refunded',
                    ]),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'completed'      => 'Completed',
                        'refunded'       => 'Refunded',
                        'partial_refund' => 'Partial Refund',
                        'voided'         => 'Voided',
                    ]),

                Tables\Filters\Filter::make('today')
                    ->label('Today')
                    ->query(fn (Builder $q) => $q->whereDate('sold_at', today()))
                    ->toggle(),
            ])
            ->defaultSort('sold_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSales::route('/'),
            'view'  => Pages\ViewSale::route('/{record}'),
        ];
    }
}
