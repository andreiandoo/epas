<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Resources\CustomerPointsResource\Pages;
use App\Models\Gamification\CustomerPoints;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;

class CustomerPointsResource extends Resource
{
    use HasMarketplaceContext;

    protected static ?string $model = CustomerPoints::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Customer Points';

    protected static ?string $navigationParentItem = 'Gamification Settings';

    protected static \UnitEnum|string|null $navigationGroup = 'Services';

    protected static ?int $navigationSort = 47;

    protected static ?string $modelLabel = 'Customer Points';

    protected static ?string $pluralModelLabel = 'Customer Points';

    protected static ?string $slug = 'customer-points';

    public static function getEloquentQuery(): Builder
    {
        $marketplace = static::getMarketplaceClient();
        return parent::getEloquentQuery()
            ->where('marketplace_client_id', $marketplace?->id)
            ->with(['marketplaceCustomer']);
    }

        public static function shouldRegisterNavigation(): bool
    {
        return static::marketplaceHasMicroservice('gamification');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                SC\Section::make('Customer Information')
                    ->schema([
                        Forms\Components\Select::make('marketplace_customer_id')
                            ->relationship('marketplaceCustomer', 'email')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->disabled(fn ($record) => $record !== null),

                        Forms\Components\TextInput::make('referral_code')
                            ->label('Referral Code')
                            ->disabled(),
                    ])->columns(2),

                SC\Section::make('Points Balance')
                    ->schema([
                        Forms\Components\TextInput::make('current_balance')
                            ->label('Current Balance')
                            ->numeric()
                            ->disabled(),

                        Forms\Components\TextInput::make('total_earned')
                            ->label('Total Earned')
                            ->numeric()
                            ->disabled(),

                        Forms\Components\TextInput::make('total_spent')
                            ->label('Total Spent')
                            ->numeric()
                            ->disabled(),

                        Forms\Components\TextInput::make('total_expired')
                            ->label('Total Expired')
                            ->numeric()
                            ->disabled(),

                        Forms\Components\TextInput::make('pending_points')
                            ->label('Pending Points')
                            ->numeric()
                            ->disabled(),
                    ])->columns(5),

                SC\Section::make('Tier Information')
                    ->schema([
                        Forms\Components\TextInput::make('current_tier')
                            ->label('Current Tier')
                            ->disabled(),

                        Forms\Components\TextInput::make('tier_points')
                            ->label('Tier Points')
                            ->numeric()
                            ->disabled(),

                        Forms\Components\DateTimePicker::make('tier_updated_at')
                            ->label('Tier Updated')
                            ->disabled(),
                    ])->columns(3),

                SC\Section::make('Referral Stats')
                    ->schema([
                        Forms\Components\TextInput::make('referral_count')
                            ->label('Referrals')
                            ->numeric()
                            ->disabled(),

                        Forms\Components\TextInput::make('referral_points_earned')
                            ->label('Points from Referrals')
                            ->numeric()
                            ->disabled(),
                    ])->columns(2),

                SC\Section::make('Manual Adjustment')
                    ->description('Add or remove points manually')
                    ->schema([
                        Forms\Components\TextInput::make('adjustment_points')
                            ->label('Points to Add/Remove')
                            ->numeric()
                            ->helperText('Use negative number to remove points')
                            ->live(),

                        Forms\Components\Textarea::make('adjustment_reason')
                            ->label('Reason')
                            ->rows(2),
                    ])->columns(2)
                    ->visible(fn ($record) => $record !== null),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('marketplaceCustomer.email')
                    ->label('Customer')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('marketplaceCustomer.first_name')
                    ->label('Name')
                    ->formatStateUsing(fn ($record) => trim(($record->marketplaceCustomer->first_name ?? '') . ' ' . ($record->marketplaceCustomer->last_name ?? '')) ?: '-')
                    ->searchable(['marketplaceCustomer.first_name', 'marketplaceCustomer.last_name']),

                Tables\Columns\TextColumn::make('current_balance')
                    ->label('Balance')
                    ->sortable()
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'success' : 'gray'),

                Tables\Columns\TextColumn::make('total_earned')
                    ->label('Earned')
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_spent')
                    ->label('Spent')
                    ->sortable(),

                Tables\Columns\TextColumn::make('current_tier')
                    ->label('Tier')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('referral_code')
                    ->label('Referral Code')
                    ->copyable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('referral_count')
                    ->label('Referrals')
                    ->sortable(),

                Tables\Columns\TextColumn::make('last_earned_at')
                    ->label('Last Activity')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('current_tier')
                    ->label('Tier')
                    ->options(fn () => CustomerPoints::query()
                        ->distinct()
                        ->whereNotNull('current_tier')
                        ->pluck('current_tier', 'current_tier')
                        ->toArray()
                    ),
                Tables\Filters\Filter::make('has_balance')
                    ->label('Has Points')
                    ->query(fn (Builder $query) => $query->where('current_balance', '>', 0)),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('adjust')
                    ->label('Adjust Points')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->form([
                        Forms\Components\TextInput::make('points')
                            ->label('Points')
                            ->numeric()
                            ->required()
                            ->helperText('Use negative to remove points'),
                        Forms\Components\Textarea::make('reason')
                            ->label('Reason')
                            ->required(),
                    ])
                    ->action(function (CustomerPoints $record, array $data): void {
                        $record->adjustPoints(
                            (int) $data['points'],
                            $data['reason'],
                            auth()->id()
                        );

                        Notification::make()
                            ->title('Points adjusted successfully')
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('bulk_adjust')
                        ->label('Bulk Adjust Points')
                        ->icon('heroicon-o-adjustments-horizontal')
                        ->form([
                            Forms\Components\TextInput::make('points')
                                ->label('Points')
                                ->numeric()
                                ->required(),
                            Forms\Components\Textarea::make('reason')
                                ->label('Reason')
                                ->required(),
                        ])
                        ->action(function ($records, array $data): void {
                            foreach ($records as $record) {
                                $record->adjustPoints(
                                    (int) $data['points'],
                                    $data['reason'],
                                    auth()->id()
                                );
                            }

                            Notification::make()
                                ->title('Points adjusted for ' . $records->count() . ' customers')
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->defaultSort('current_balance', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomerPoints::route('/'),
            'view' => Pages\ViewCustomerPoints::route('/{record}'),
        ];
    }
}
