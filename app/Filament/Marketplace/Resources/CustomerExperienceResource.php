<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Filament\Marketplace\Resources\CustomerExperienceResource\Pages;
use App\Models\Gamification\CustomerExperience;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CustomerExperienceResource extends Resource
{
    use HasMarketplaceContext;

    protected static ?string $model = CustomerExperience::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'Customer Levels';

    protected static \UnitEnum|string|null $navigationGroup = 'Gamification';

    protected static ?int $navigationSort = 53;

    protected static ?string $modelLabel = 'Customer Experience';

    protected static ?string $pluralModelLabel = 'Customer Levels';

    protected static ?string $slug = 'gamification-customer-levels';

    public static function getEloquentQuery(): Builder
    {
        $marketplaceClientId = static::getMarketplaceClientId();
        return parent::getEloquentQuery()
            ->where('marketplace_client_id', $marketplaceClientId)
            ->with(['customer']);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::marketplaceHasMicroservice('gamification');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
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
                    ->badge()
                    ->color(fn ($record) => $record->level_group_color ?? 'gray'),

                Tables\Columns\TextColumn::make('total_xp')
                    ->label('Total XP')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('level_progress')
                    ->label('Progress')
                    ->suffix('%')
                    ->formatStateUsing(fn ($state) => number_format($state, 1)),

                Tables\Columns\TextColumn::make('total_badges_earned')
                    ->label('Badges')
                    ->sortable(),

                Tables\Columns\TextColumn::make('events_attended')
                    ->label('Events')
                    ->sortable(),

                Tables\Columns\TextColumn::make('reviews_submitted')
                    ->label('Reviews')
                    ->sortable(),

                Tables\Columns\TextColumn::make('referrals_converted')
                    ->label('Referrals')
                    ->sortable(),

                Tables\Columns\TextColumn::make('last_xp_earned_at')
                    ->label('Last Activity')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('total_xp', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('current_level_group')
                    ->label('Level Group')
                    ->options(fn () => CustomerExperience::query()
                        ->whereNotNull('current_level_group')
                        ->distinct()
                        ->pluck('current_level_group', 'current_level_group')
                        ->toArray()
                    ),
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
