<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\CustomerPointsResource\Pages;
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

class CustomerPointsResource extends Resource
{
    protected static ?string $model = CustomerPoints::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Customer Points';

    public static function getNavigationLabel(): string
    {
        return __('Customer Points');
    }

    protected static ?string $navigationParentItem = 'Gamification Settings';

    public static function getNavigationParentItem(): ?string
    {
        return __('Gamification Settings');
    }

    protected static \UnitEnum|string|null $navigationGroup = 'Services';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('Services');
    }

    protected static ?int $navigationSort = 47;

    protected static ?string $modelLabel = 'Customer Points';

    public static function getModelLabel(): string
    {
        return __('Customer Points');
    }

    protected static ?string $pluralModelLabel = 'Customer Points';

    public static function getPluralModelLabel(): string
    {
        return __('Customer Points');
    }

    protected static ?string $slug = 'customer-points';

    public static function getEloquentQuery(): Builder
    {
        $tenant = auth()->user()->tenant;
        return parent::getEloquentQuery()
            ->where('tenant_id', $tenant?->id)
            ->with(['customer']);
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

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                SC\Section::make(__('Customer Information'))
                    ->schema([
                        Forms\Components\Select::make('customer_id')
                            ->relationship('customer', 'email')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->disabled(fn ($record) => $record !== null),

                        Forms\Components\TextInput::make('referral_code')
                            ->label(__('Referral Code'))
                            ->disabled(),
                    ])->columns(2),

                SC\Section::make(__('Points Balance'))
                    ->schema([
                        Forms\Components\TextInput::make('current_balance')
                            ->label(__('Current Balance'))
                            ->numeric()
                            ->disabled(),

                        Forms\Components\TextInput::make('total_earned')
                            ->label(__('Total Earned'))
                            ->numeric()
                            ->disabled(),

                        Forms\Components\TextInput::make('total_spent')
                            ->label(__('Total Spent'))
                            ->numeric()
                            ->disabled(),

                        Forms\Components\TextInput::make('total_expired')
                            ->label(__('Total Expired'))
                            ->numeric()
                            ->disabled(),

                        Forms\Components\TextInput::make('pending_points')
                            ->label(__('Pending Points'))
                            ->numeric()
                            ->disabled(),
                    ])->columns(5),

                SC\Section::make(__('Tier Information'))
                    ->schema([
                        Forms\Components\TextInput::make('current_tier')
                            ->label(__('Current Tier'))
                            ->disabled(),

                        Forms\Components\TextInput::make('tier_points')
                            ->label(__('Tier Points'))
                            ->numeric()
                            ->disabled(),

                        Forms\Components\DateTimePicker::make('tier_updated_at')
                            ->label(__('Tier Updated'))
                            ->disabled(),
                    ])->columns(3),

                SC\Section::make(__('Referral Stats'))
                    ->schema([
                        Forms\Components\TextInput::make('referral_count')
                            ->label(__('Referrals'))
                            ->numeric()
                            ->disabled(),

                        Forms\Components\TextInput::make('referral_points_earned')
                            ->label(__('Points from Referrals'))
                            ->numeric()
                            ->disabled(),
                    ])->columns(2),

                SC\Section::make(__('Manual Adjustment'))
                    ->description(__('Add or remove points manually'))
                    ->schema([
                        Forms\Components\TextInput::make('adjustment_points')
                            ->label(__('Points to Add/Remove'))
                            ->numeric()
                            ->helperText(__('Use negative number to remove points'))
                            ->live(),

                        Forms\Components\Textarea::make('adjustment_reason')
                            ->label(__('Reason'))
                            ->rows(2),
                    ])->columns(2)
                    ->visible(fn ($record) => $record !== null),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('customer.email')
                    ->label('Customer')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('customer.first_name')
                    ->label('Name')
                    ->formatStateUsing(fn ($record) => trim(($record->customer->first_name ?? '') . ' ' . ($record->customer->last_name ?? '')) ?: '-')
                    ->searchable(['customer.first_name', 'customer.last_name']),

                Tables\Columns\TextColumn::make('current_balance')
                    ->label(__('Balance'))
                    ->sortable()
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'success' : 'gray'),

                Tables\Columns\TextColumn::make('total_earned')
                    ->label(__('Earned'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_spent')
                    ->label(__('Spent'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('current_tier')
                    ->label(__('Tier'))
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('referral_code')
                    ->label(__('Referral Code'))
                    ->copyable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('referral_count')
                    ->label(__('Referrals'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('last_earned_at')
                    ->label(__('Last Activity'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('current_tier')
                    ->label(__('Tier'))
                    ->options(fn () => CustomerPoints::query()
                        ->distinct()
                        ->whereNotNull('current_tier')
                        ->pluck('current_tier', 'current_tier')
                        ->toArray()
                    ),
                Tables\Filters\Filter::make('has_balance')
                    ->label(__('Has Points'))
                    ->query(fn (Builder $query) => $query->where('current_balance', '>', 0)),
            ])
            ->actions([
                ViewAction::make(),
                Action::make('adjust')
                    ->label(__('Adjust Points'))
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->form([
                        Forms\Components\TextInput::make('points')
                            ->label(__('Points'))
                            ->numeric()
                            ->required()
                            ->helperText(__('Use negative to remove points')),
                        Forms\Components\Textarea::make('reason')
                            ->label(__('Reason'))
                            ->required(),
                    ])
                    ->action(function (CustomerPoints $record, array $data): void {
                        $record->adjustPoints(
                            (int) $data['points'],
                            $data['reason'],
                            auth()->id()
                        );

                        Notification::make()
                            ->title(__('Points adjusted successfully'))
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('bulk_adjust')
                        ->label(__('Bulk Adjust Points'))
                        ->icon('heroicon-o-adjustments-horizontal')
                        ->form([
                            Forms\Components\TextInput::make('points')
                                ->label(__('Points'))
                                ->numeric()
                                ->required(),
                            Forms\Components\Textarea::make('reason')
                                ->label(__('Reason'))
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
