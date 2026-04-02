<?php

namespace App\Filament\Tenant\Resources\Cashless;

use App\Filament\Tenant\Resources\Cashless\CustomerProfileResource\Pages;
use App\Models\Cashless\CustomerProfile;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CustomerProfileResource extends Resource
{
    protected static ?string $model = CustomerProfile::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-user-circle';

    protected static ?string $navigationLabel = 'Customer Profiles';

    protected static \UnitEnum|string|null $navigationGroup = 'Cashless';

    protected static ?int $navigationSort = 60;

    protected static ?string $slug = 'cashless-profiles';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('customer.first_name')->label('Customer')
                    ->formatStateUsing(fn ($record) => ($record->customer?->first_name ?? '') . ' ' . ($record->customer?->last_name ?? ''))
                    ->searchable(['customer.first_name', 'customer.last_name']),
                Tables\Columns\BadgeColumn::make('segment')
                    ->colors(['success' => 'whale', 'primary' => 'regular', 'warning' => 'occasional', 'gray' => 'minimal']),
                Tables\Columns\TextColumn::make('overall_score')->label('Score')
                    ->sortable()->badge()
                    ->color(fn ($state) => match (true) {
                        $state >= 80 => 'success',
                        $state >= 50 => 'primary',
                        $state >= 20 => 'warning',
                        default      => 'gray',
                    }),
                Tables\Columns\TextColumn::make('total_spent_cents')->label('Total Spent')
                    ->formatStateUsing(fn ($state) => number_format($state / 100, 2) . ' RON')
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_transactions')->label('Transactions')->sortable(),
                Tables\Columns\TextColumn::make('avg_transaction_cents')->label('Avg Basket')
                    ->formatStateUsing(fn ($state) => number_format($state / 100, 2)),
                Tables\Columns\TextColumn::make('age_group')->toggleable(),
                Tables\Columns\TextColumn::make('gender')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('peak_hour')->label('Peak Hour')
                    ->formatStateUsing(fn ($state) => $state !== null ? sprintf('%02d:00', $state) : '-')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('tags')->label('Tags')
                    ->formatStateUsing(fn ($state) => is_array($state) ? implode(', ', $state) : '-')
                    ->wrap()->toggleable(),
                Tables\Columns\IconColumn::make('is_minor')->boolean()->label('Minor')->toggleable(),
                Tables\Columns\IconColumn::make('flagged_for_review')->boolean()->label('Flagged')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('segment')
                    ->options(['whale' => 'Whale', 'regular' => 'Regular', 'occasional' => 'Occasional', 'minimal' => 'Minimal']),
                Tables\Filters\Filter::make('high_score')
                    ->label('High Score (80+)')
                    ->query(fn (Builder $q) => $q->where('overall_score', '>=', 80))
                    ->toggle(),
                Tables\Filters\TernaryFilter::make('is_minor'),
                Tables\Filters\TernaryFilter::make('flagged_for_review'),
            ])
            ->defaultSort('overall_score', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomerProfiles::route('/'),
        ];
    }
}
