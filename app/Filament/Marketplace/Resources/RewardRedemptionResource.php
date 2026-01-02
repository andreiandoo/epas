<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Filament\Marketplace\Resources\RewardRedemptionResource\Pages;
use App\Models\Gamification\RewardRedemption;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RewardRedemptionResource extends Resource
{
    use HasMarketplaceContext;

    protected static ?string $model = RewardRedemption::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-receipt-percent';

    protected static ?string $navigationLabel = 'Redemption History';

    protected static \UnitEnum|string|null $navigationGroup = 'Gamification';

    protected static ?int $navigationSort = 51;

    protected static ?string $modelLabel = 'Redemption';

    protected static ?string $pluralModelLabel = 'Redemption History';

    protected static ?string $slug = 'gamification-redemptions';

    public static function getEloquentQuery(): Builder
    {
        $marketplaceClientId = static::getMarketplaceClientId();
        return parent::getEloquentQuery()
            ->where('marketplace_client_id', $marketplaceClientId)
            ->with(['reward', 'customer']);
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
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('customer.email')
                    ->label('Customer')
                    ->searchable(),

                Tables\Columns\TextColumn::make('reward.name')
                    ->label('Reward')
                    ->searchable(),

                Tables\Columns\TextColumn::make('points_spent')
                    ->label('Points Spent')
                    ->sortable(),

                Tables\Columns\TextColumn::make('voucher_code')
                    ->label('Voucher Code')
                    ->copyable()
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'pending' => 'warning',
                        'active' => 'success',
                        'used' => 'info',
                        'expired' => 'gray',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('discount_applied')
                    ->label('Discount Applied')
                    ->money('RON')
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('voucher_used_at')
                    ->label('Used At')
                    ->dateTime()
                    ->placeholder('-'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'active' => 'Active',
                        'used' => 'Used',
                        'expired' => 'Expired',
                        'cancelled' => 'Cancelled',
                    ]),
                Tables\Filters\SelectFilter::make('reward_id')
                    ->label('Reward')
                    ->relationship('reward', 'name->en'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('cancel')
                    ->label('Cancel')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => $record->status === 'active')
                    ->action(fn ($record) => $record->cancel(auth()->id())),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRewardRedemptions::route('/'),
        ];
    }
}
