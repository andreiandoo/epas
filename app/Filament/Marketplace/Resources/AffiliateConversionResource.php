<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Resources\AffiliateConversionResource\Pages;
use App\Models\AffiliateConversion;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AffiliateConversionResource extends Resource
{
    protected static ?string $model = AffiliateConversion::class;
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-currency-dollar';
    protected static ?string $navigationLabel = 'Conversions';
    protected static ?string $navigationParentItem = 'Affiliates';
    protected static \UnitEnum|string|null $navigationGroup = 'Marketing';
    protected static ?int $navigationSort = 2;

    public static function getEloquentQuery(): Builder
    {
        $tenant = auth()->user()->tenant;

        return parent::getEloquentQuery()
            ->where('tenant_id', $tenant?->id)
            ->with(['affiliate']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order_ref')
                    ->label('Order Ref')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('affiliate.name')
                    ->label('Affiliate')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('affiliate.code')
                    ->label('Code')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Order Amount')
                    ->money('RON')
                    ->sortable(),

                Tables\Columns\TextColumn::make('commission_value')
                    ->label('Commission')
                    ->money('RON')
                    ->sortable()
                    ->color('success'),

                Tables\Columns\BadgeColumn::make('attributed_by')
                    ->label('Attribution')
                    ->colors([
                        'primary' => 'link',
                        'success' => 'coupon',
                    ])
                    ->formatStateUsing(fn ($state) => ucfirst($state)),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'approved',
                        'danger' => 'reversed',
                    ])
                    ->formatStateUsing(fn ($state) => ucfirst($state)),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'reversed' => 'Reversed',
                    ]),

                Tables\Filters\SelectFilter::make('attributed_by')
                    ->label('Attribution Type')
                    ->options([
                        'link' => 'Link',
                        'coupon' => 'Coupon',
                    ]),

                Tables\Filters\SelectFilter::make('affiliate_id')
                    ->label('Affiliate')
                    ->relationship('affiliate', 'name'),
            ])
            ->actions([])
            ->bulkActions([])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('approve_selected')
                        ->label('Approve Selected')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $records->each(function ($record) {
                                if ($record->status === 'pending') {
                                    $record->update(['status' => 'approved']);
                                }
                            });
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAffiliateConversions::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
