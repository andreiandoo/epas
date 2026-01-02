<?php

namespace App\Filament\Resources\Gamification;

use App\Filament\Resources\Gamification\CustomerBadgeResource\Pages;
use App\Models\Gamification\CustomerBadge;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CustomerBadgeResource extends Resource
{
    protected static ?string $model = CustomerBadge::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-star';

    protected static ?string $navigationLabel = 'Customer Badges';

    protected static \UnitEnum|string|null $navigationGroup = 'Gamification';

    protected static ?int $navigationSort = 94;

    protected static ?string $slug = 'gamification/customer-badges';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('tenant.name')
                    ->label('Tenant')
                    ->placeholder('(Marketplace)')
                    ->sortable(),

                Tables\Columns\TextColumn::make('marketplaceClient.name')
                    ->label('Marketplace')
                    ->placeholder('(Tenant)')
                    ->sortable(),

                Tables\Columns\TextColumn::make('earned_at')
                    ->label('Earned At')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('customer.email')
                    ->label('Customer')
                    ->searchable(),

                Tables\Columns\ImageColumn::make('badge.icon_url')
                    ->label('Badge')
                    ->circular(),

                Tables\Columns\TextColumn::make('badge.name')
                    ->label('Badge Name'),

                Tables\Columns\TextColumn::make('xp_awarded')
                    ->label('XP'),

                Tables\Columns\TextColumn::make('points_awarded')
                    ->label('Points'),
            ])
            ->defaultSort('earned_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('tenant_id')
                    ->label('Tenant')
                    ->relationship('tenant', 'name'),
                Tables\Filters\SelectFilter::make('marketplace_client_id')
                    ->label('Marketplace')
                    ->relationship('marketplaceClient', 'name'),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomerBadges::route('/'),
        ];
    }
}
