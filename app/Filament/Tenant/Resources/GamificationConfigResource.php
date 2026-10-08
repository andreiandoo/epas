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

    public static function getNavigationLabel(): string
    {
        return __('Gamification Settings');
    }

    protected static \UnitEnum|string|null $navigationGroup = 'Services';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('Services');
    }

    protected static ?int $navigationSort = 46;

    protected static ?string $modelLabel = 'Gamification Settings';

    public static function getModelLabel(): string
    {
        return __('Gamification Settings');
    }

    protected static ?string $pluralModelLabel = 'Gamification Settings';

    public static function getPluralModelLabel(): string
    {
        return __('Gamification Settings');
    }

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

                SC\Section::make(__('Point Value Configuration'))
                    ->description(__('Configure how points are valued and earned'))
                    ->schema([
                        Forms\Components\TextInput::make('point_value')
                            ->label(__('Point Value'))
                            ->numeric()
                            ->step(0.01)
                            ->default(0.01)
                            ->required()
                            ->helperText(__('How much is 1 point worth for redemption (e.g., 0.01 = 1 point = 0.01 RON)')),

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
                            ->label(__('Earn Percentage'))
                            ->numeric()
                            ->suffix('%')
                            ->default(5.00)
                            ->helperText(__('Percentage of order value converted to points')),

                        Forms\Components\Toggle::make('earn_on_subtotal')
                            ->label(__('Earn on Subtotal'))
                            ->default(true)
                            ->helperText(__('Calculate points based on subtotal (vs total with fees)')),

                        Forms\Components\TextInput::make('min_order_for_earning')
                            ->label(__('Minimum Order'))
                            ->numeric()
                            ->step(0.01)
                            ->default(0)
                            ->helperText(__('Minimum order value to earn points (e.g., 10.00)')),
                    ])->columns(3),

                SC\Section::make(__('Redemption Settings'))
                    ->description(__('Configure how points can be redeemed'))
                    ->schema([
                        Forms\Components\TextInput::make('min_redeem_points')
                            ->label(__('Minimum Points to Redeem'))
                            ->numeric()
                            ->default(100)
                            ->required(),

                        Forms\Components\TextInput::make('max_redeem_percentage')
                            ->label(__('Max Redemption (%)'))
                            ->numeric()
                            ->suffix('%')
                            ->default(50.00)
                            ->helperText(__('Maximum percentage of order that can be paid with points')),

                        Forms\Components\TextInput::make('max_redeem_points_per_order')
                            ->label(__('Max Points Per Order'))
                            ->numeric()
                            ->nullable()
                            ->helperText(__('Leave empty for no limit')),
                    ])->columns(3),

                SC\Section::make(__('Bonus Points'))
                    ->description(__('Configure bonus points for special actions'))
                    ->schema([
                        Forms\Components\TextInput::make('birthday_bonus_points')
                            ->label(__('Birthday Bonus'))
                            ->numeric()
                            ->default(100),

                        Forms\Components\TextInput::make('signup_bonus_points')
                            ->label(__('Signup Bonus'))
                            ->numeric()
                            ->default(50),

                        Forms\Components\TextInput::make('referral_bonus_points')
                            ->label(__('Referral Bonus (Referrer)'))
                            ->numeric()
                            ->default(200)
                            ->helperText(__('Points awarded to the person who refers')),

                        Forms\Components\TextInput::make('referred_bonus_points')
                            ->label(__('Referral Bonus (Referred)'))
                            ->numeric()
                            ->default(100)
                            ->helperText(__('Points awarded to the new customer')),
                    ])->columns(4),

                SC\Section::make(__('Expiration Settings'))
                    ->schema([
                        Forms\Components\TextInput::make('points_expire_days')
                            ->label(__('Points Expire After (days)'))
                            ->numeric()
                            ->nullable()
                            ->helperText(__('Leave empty if points never expire')),

                        Forms\Components\Toggle::make('expire_on_inactivity')
                            ->label(__('Expire on Inactivity'))
                            ->default(false),

                        Forms\Components\TextInput::make('inactivity_days')
                            ->label(__('Inactivity Period (days)'))
                            ->numeric()
                            ->default(365)
                            ->visible(fn (callable $get) => $get('expire_on_inactivity')),
                    ])->columns(3),

                SC\Section::make(__('Display Settings'))
                    ->schema([
                        Forms\Components\TextInput::make('points_name')
                            ->label(__('Points Name (plural)'))
                            ->default('puncte')
                            ->required(),

                        Forms\Components\TextInput::make('points_name_singular')
                            ->label(__('Points Name (singular)'))
                            ->default('punct')
                            ->required(),

                        Forms\Components\Select::make('icon')
                            ->options([
                                'star' => __('Star'),
                                'sparkles' => __('Sparkles'),
                                'gift' => __('Gift'),
                                'currency-dollar' => __('Dollar'),
                                'trophy' => __('Trophy'),
                                'heart' => __('Heart'),
                            ])
                            ->default('star'),
                    ])->columns(3),

                SC\Section::make(__('Customer Tiers'))
                    ->description(__('Define customer loyalty tiers (optional)'))
                    ->schema([
                        Forms\Components\Repeater::make('tiers')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label(__('Tier Name'))
                                    ->required(),
                                Forms\Components\TextInput::make('min_points')
                                    ->label(__('Minimum Points'))
                                    ->numeric()
                                    ->required(),
                                Forms\Components\TextInput::make('multiplier')
                                    ->label(__('Points Multiplier'))
                                    ->numeric()
                                    ->default(1.0)
                                    ->helperText(__('e.g., 1.5 for 50% bonus')),
                                Forms\Components\TextInput::make('color')
                                    ->label(__('Badge Color'))
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
                    ->label(__('Earn %'))
                    ->suffix('%'),

                Tables\Columns\TextColumn::make('point_value')
                    ->label(__('Point Value'))
                    ->formatStateUsing(fn ($state, $record) => number_format($state, 2) . ' ' . ($record->currency ?? 'RON')),

                Tables\Columns\TextColumn::make('min_redeem_points')
                    ->label(__('Min Redeem')),

                Tables\Columns\TextColumn::make('max_redeem_percentage')
                    ->label(__('Max Redeem %'))
                    ->suffix('%'),

                Tables\Columns\TextColumn::make('birthday_bonus_points')
                    ->label(__('Birthday')),

                Tables\Columns\TextColumn::make('referral_bonus_points')
                    ->label(__('Referral')),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('Last Updated'))
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
