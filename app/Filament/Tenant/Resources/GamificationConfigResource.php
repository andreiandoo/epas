<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\GamificationConfigResource\Pages;
use App\Models\Gamification\GamificationConfig;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class GamificationConfigResource extends Resource
{
    protected static ?string $model = GamificationConfig::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-star';

    protected static ?string $navigationLabel = 'Gamification Settings';

    protected static \UnitEnum|string|null $navigationGroup = 'Services';

    protected static ?int $navigationSort = 46;

    protected static ?string $modelLabel = 'Gamification Settings';

    protected static ?string $pluralModelLabel = 'Gamification Settings';

    protected static ?string $slug = 'gamification-settings';

    public static function getEloquentQuery(): Builder
    {
        $tenant = auth()->user()->tenant;
        return parent::getEloquentQuery()->where('tenant_id', $tenant?->id);
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
        $tenant = auth()->user()->tenant;

        return $schema
            ->schema([
                Forms\Components\Hidden::make('tenant_id')
                    ->default($tenant?->id),

                SC\Section::make('Point Value Configuration')
                    ->description('Configure how points are valued and earned')
                    ->schema([
                        Forms\Components\TextInput::make('point_value')
                            ->label('Point Value')
                            ->numeric()
                            ->step(0.01)
                            ->default(0.01)
                            ->required()
                            ->helperText('How much is 1 point worth for redemption (e.g., 0.01 = 1 point = 0.01 RON)'),

                        Forms\Components\Select::make('currency')
                            ->options([
                                'RON' => 'RON',
                                'EUR' => 'EUR',
                                'USD' => 'USD',
                                'GBP' => 'GBP',
                            ])
                            ->default('RON')
                            ->required(),

                        Forms\Components\TextInput::make('earn_percentage')
                            ->label('Earn Percentage')
                            ->numeric()
                            ->suffix('%')
                            ->default(5.00)
                            ->helperText('Percentage of order value converted to points'),

                        Forms\Components\Toggle::make('earn_on_subtotal')
                            ->label('Earn on Subtotal')
                            ->default(true)
                            ->helperText('Calculate points based on subtotal (vs total with fees)'),

                        Forms\Components\TextInput::make('min_order_for_earning')
                            ->label('Minimum Order')
                            ->numeric()
                            ->step(0.01)
                            ->default(0)
                            ->helperText('Minimum order value to earn points (e.g., 10.00)'),
                    ])->columns(3),

                SC\Section::make('Redemption Settings')
                    ->description('Configure how points can be redeemed')
                    ->schema([
                        Forms\Components\TextInput::make('min_redeem_points')
                            ->label('Minimum Points to Redeem')
                            ->numeric()
                            ->default(100)
                            ->required(),

                        Forms\Components\TextInput::make('max_redeem_percentage')
                            ->label('Max Redemption (%)')
                            ->numeric()
                            ->suffix('%')
                            ->default(50.00)
                            ->helperText('Maximum percentage of order that can be paid with points'),

                        Forms\Components\TextInput::make('max_redeem_points_per_order')
                            ->label('Max Points Per Order')
                            ->numeric()
                            ->nullable()
                            ->helperText('Leave empty for no limit'),
                    ])->columns(3),

                SC\Section::make('Bonus Points')
                    ->description('Configure bonus points for special actions')
                    ->schema([
                        Forms\Components\TextInput::make('birthday_bonus_points')
                            ->label('Birthday Bonus')
                            ->numeric()
                            ->default(100),

                        Forms\Components\TextInput::make('signup_bonus_points')
                            ->label('Signup Bonus')
                            ->numeric()
                            ->default(50),

                        Forms\Components\TextInput::make('referral_bonus_points')
                            ->label('Referral Bonus (Referrer)')
                            ->numeric()
                            ->default(200)
                            ->helperText('Points awarded to the person who refers'),

                        Forms\Components\TextInput::make('referred_bonus_points')
                            ->label('Referral Bonus (Referred)')
                            ->numeric()
                            ->default(100)
                            ->helperText('Points awarded to the new customer'),
                    ])->columns(4),

                SC\Section::make('Expiration Settings')
                    ->schema([
                        Forms\Components\TextInput::make('points_expire_days')
                            ->label('Points Expire After (days)')
                            ->numeric()
                            ->nullable()
                            ->helperText('Leave empty if points never expire'),

                        Forms\Components\Toggle::make('expire_on_inactivity')
                            ->label('Expire on Inactivity')
                            ->default(false),

                        Forms\Components\TextInput::make('inactivity_days')
                            ->label('Inactivity Period (days)')
                            ->numeric()
                            ->default(365)
                            ->visible(fn (callable $get) => $get('expire_on_inactivity')),
                    ])->columns(3),

                SC\Section::make('Display Settings')
                    ->schema([
                        Forms\Components\TextInput::make('points_name')
                            ->label('Points Name (plural)')
                            ->default('puncte')
                            ->required(),

                        Forms\Components\TextInput::make('points_name_singular')
                            ->label('Points Name (singular)')
                            ->default('punct')
                            ->required(),

                        Forms\Components\Select::make('icon')
                            ->options([
                                'star' => 'Star',
                                'sparkles' => 'Sparkles',
                                'gift' => 'Gift',
                                'currency-dollar' => 'Dollar',
                                'trophy' => 'Trophy',
                                'heart' => 'Heart',
                            ])
                            ->default('star'),
                    ])->columns(3),

                SC\Section::make('Customer Tiers')
                    ->description('Define customer loyalty tiers (optional)')
                    ->schema([
                        Forms\Components\Repeater::make('tiers')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Tier Name')
                                    ->required(),
                                Forms\Components\TextInput::make('min_points')
                                    ->label('Minimum Points')
                                    ->numeric()
                                    ->required(),
                                Forms\Components\TextInput::make('multiplier')
                                    ->label('Points Multiplier')
                                    ->numeric()
                                    ->default(1.0)
                                    ->helperText('e.g., 1.5 for 50% bonus'),
                                Forms\Components\TextInput::make('color')
                                    ->label('Badge Color')
                                    ->default('#6366f1'),
                            ])
                            ->columns(4)
                            ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                            ->collapsible()
                            ->defaultItems(0),
                    ]),

                Forms\Components\Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                Tables\Columns\TextColumn::make('earn_percentage')
                    ->label('Earn %')
                    ->suffix('%'),

                Tables\Columns\TextColumn::make('point_value')
                    ->label('Point Value')
                    ->formatStateUsing(fn ($state, $record) => number_format($state, 2) . ' ' . ($record->currency ?? 'RON')),

                Tables\Columns\TextColumn::make('min_redeem_points')
                    ->label('Min Redeem'),

                Tables\Columns\TextColumn::make('max_redeem_percentage')
                    ->label('Max Redeem %')
                    ->suffix('%'),

                Tables\Columns\TextColumn::make('birthday_bonus_points')
                    ->label('Birthday'),

                Tables\Columns\TextColumn::make('referral_bonus_points')
                    ->label('Referral'),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGamificationConfigs::route('/'),
            'create' => Pages\CreateGamificationConfig::route('/create'),
            'edit' => Pages\EditGamificationConfig::route('/{record}/edit'),
        ];
    }
}
