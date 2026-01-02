<?php

namespace App\Filament\Resources\Gamification;

use App\Filament\Resources\Gamification\ExperienceActionResource\Pages;
use App\Models\Gamification\ExperienceAction;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ExperienceActionResource extends Resource
{
    protected static ?string $model = ExperienceAction::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-bolt';

    protected static ?string $navigationLabel = 'XP Actions';

    protected static \UnitEnum|string|null $navigationGroup = 'Gamification';

    protected static ?int $navigationSort = 96;

    protected static ?string $slug = 'gamification/xp-actions';

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

                Tables\Columns\TextColumn::make('action_type_label')
                    ->label('Action'),

                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable(),

                Tables\Columns\TextColumn::make('xp_type_label')
                    ->label('XP Type')
                    ->badge(),

                Tables\Columns\TextColumn::make('xp_amount')
                    ->label('XP Amount'),

                Tables\Columns\TextColumn::make('max_times_per_day')
                    ->label('Daily Limit')
                    ->placeholder('Unlimited'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
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
            'index' => Pages\ListExperienceActions::route('/'),
        ];
    }
}
