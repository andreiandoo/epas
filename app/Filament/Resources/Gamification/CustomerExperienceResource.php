<?php

namespace App\Filament\Resources\Gamification;

use App\Filament\Resources\Gamification\CustomerExperienceResource\Pages;
use App\Models\Gamification\CustomerExperience;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CustomerExperienceResource extends Resource
{
    protected static ?string $model = CustomerExperience::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'Customer Levels';

    protected static \UnitEnum|string|null $navigationGroup = 'Gamification';

    protected static ?int $navigationSort = 97;

    protected static ?string $slug = 'gamification/customer-levels';

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

                Tables\Columns\TextColumn::make('customer.email')
                    ->label('Customer')
                    ->searchable(),

                Tables\Columns\TextColumn::make('current_level')
                    ->label('Level')
                    ->sortable()
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('current_level_group')
                    ->label('Group')
                    ->badge(),

                Tables\Columns\TextColumn::make('total_xp')
                    ->label('Total XP')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_badges_earned')
                    ->label('Badges'),

                Tables\Columns\TextColumn::make('events_attended')
                    ->label('Events'),

                Tables\Columns\TextColumn::make('last_xp_earned_at')
                    ->label('Last Activity')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('total_xp', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('tenant_id')
                    ->label('Tenant')
                    ->relationship('tenant', 'name'),
                Tables\Filters\SelectFilter::make('marketplace_client_id')
                    ->label('Marketplace')
                    ->relationship('marketplaceClient', 'name'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomerExperiences::route('/'),
        ];
    }
}
