<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\CustomerBadgeResource\Pages;
use App\Models\Gamification\CustomerBadge;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CustomerBadgeResource extends Resource
{
    protected static ?string $model = CustomerBadge::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-star';

    protected static ?string $navigationLabel = 'Customer Badges';

    protected static \UnitEnum|string|null $navigationGroup = 'Gamification';

    protected static ?int $navigationSort = 52;

    protected static ?string $modelLabel = 'Customer Badge';

    protected static ?string $pluralModelLabel = 'Customer Badges';

    protected static ?string $slug = 'gamification-customer-badges';

    public static function getEloquentQuery(): Builder
    {
        $tenant = auth()->user()->tenant;
        return parent::getEloquentQuery()
            ->where('tenant_id', $tenant?->id)
            ->with(['badge', 'customer']);
    }

    public static function shouldRegisterNavigation(): bool
    {
        $tenant = auth()->user()->tenant;
        if (!$tenant) return false;

        return $tenant->microservices()
            ->where('slug', 'gamification')
            ->wherePivot('is_active', true)
            ->exists();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
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
                    ->label('Badge Name')
                    ->searchable(),

                Tables\Columns\TextColumn::make('badge.rarity_name')
                    ->label('Rarity')
                    ->badge()
                    ->color(fn ($record) => match ($record->badge?->rarity_level) {
                        1 => 'gray',
                        2 => 'success',
                        3 => 'info',
                        4 => 'warning',
                        5 => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('xp_awarded')
                    ->label('XP Awarded')
                    ->sortable(),

                Tables\Columns\TextColumn::make('points_awarded')
                    ->label('Points Awarded')
                    ->sortable(),
            ])
            ->defaultSort('earned_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('badge_id')
                    ->label('Badge')
                    ->relationship('badge', 'name->en'),
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
