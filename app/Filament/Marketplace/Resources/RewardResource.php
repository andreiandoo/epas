<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Filament\Marketplace\Resources\RewardResource\Pages;
use App\Models\Gamification\Reward;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RewardResource extends Resource
{
    use \App\Filament\Marketplace\Concerns\TranslatesResourceLabels;

    use HasMarketplaceContext;

    protected static ?string $model = Reward::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-gift';

    protected static ?string $navigationLabel = 'Rewards';

    protected static \UnitEnum|string|null $navigationGroup = 'Gamification';

    protected static ?int $navigationSort = 47;

    protected static ?string $modelLabel = 'Reward';

    protected static ?string $pluralModelLabel = 'Rewards';

    protected static ?string $slug = 'gamification-rewards';

    public static function getEloquentQuery(): Builder
    {
        $marketplaceClientId = static::getMarketplaceClientId();
        return parent::getEloquentQuery()->where('marketplace_client_id', $marketplaceClientId);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::marketplaceHasMicroservice('gamification');
    }

    public static function form(Schema $schema): Schema
    {
        $marketplace = static::getMarketplaceClient();

        return $schema
            ->schema([
                Forms\Components\Hidden::make('marketplace_client_id')
                    ->default($marketplace?->id),

                SC\Section::make(__('Basic Information'))
                    ->schema([
                        Forms\Components\TextInput::make('name.en')
                            ->label(__('Name (English)'))
                            ->required(),

                        Forms\Components\TextInput::make('name.ro')
                            ->label(__('Name (Romanian)'))
                            ->required(),

                        Forms\Components\TextInput::make('slug')
                            ->label(__('Slug'))
                            ->unique(ignoreRecord: true)
                            ->helperText(__('Auto-generated if left empty')),

                        Forms\Components\Textarea::make('description.en')
                            ->label(__('Description (English)'))
                            ->rows(2),

                        Forms\Components\Textarea::make('description.ro')
                            ->label(__('Description (Romanian)'))
                            ->rows(2),

                        Forms\Components\FileUpload::make('image_url')
                            ->label(__('Image'))
                            ->image()
                            ->directory('rewards'),
                    ])->columns(2),

                SC\Section::make(__('Reward Configuration'))
                    ->schema([
                        Forms\Components\Select::make('type')
                            ->options([
                                'fixed_discount' => __('Fixed Discount'),
                                'percentage_discount' => __('Percentage Discount'),
                                'free_item' => __('Free Item'),
                                'voucher_code' => __('Voucher Code'),
                            ])
                            ->default('fixed_discount')
                            ->required()
                            ->live(),

                        Forms\Components\TextInput::make('points_cost')
                            ->label(__('Points Cost'))
                            ->numeric()
                            ->required()
                            ->helperText(__('How many points needed to redeem')),

                        Forms\Components\TextInput::make('value')
                            ->label(__('Value'))
                            ->numeric()
                            ->required()
                            ->helperText(fn (callable $get) => match ($get('type')) {
                                'fixed_discount', 'voucher_code' => 'Discount amount in currency',
                                'percentage_discount' => 'Discount percentage (e.g., 10 for 10%)',
                                default => 'Reward value',
                            }),

                        Forms\Components\Select::make('currency')
                            ->options([
                                'RON' => __('RON'),
                                'EUR' => __('EUR'),
                                'USD' => __('USD'),
                            ])
                            ->default('RON')
                            ->required()
                            ->visible(fn (callable $get) => in_array($get('type'), ['fixed_discount', 'voucher_code'])),

                        Forms\Components\TextInput::make('voucher_prefix')
                            ->label(__('Voucher Prefix'))
                            ->maxLength(10)
                            ->helperText(__('Prefix for generated voucher codes'))
                            ->visible(fn (callable $get) => $get('type') === 'voucher_code'),
                    ])->columns(3),

                SC\Section::make(__('Restrictions'))
                    ->schema([
                        Forms\Components\TextInput::make('min_order_value')
                            ->label(__('Minimum Order Value'))
                            ->numeric()
                            ->nullable()
                            ->helperText(__('Minimum order value to use this reward')),

                        Forms\Components\TextInput::make('max_redemptions_total')
                            ->label(__('Total Redemption Limit'))
                            ->numeric()
                            ->nullable()
                            ->helperText(__('Leave empty for unlimited')),

                        Forms\Components\TextInput::make('max_redemptions_per_customer')
                            ->label(__('Per Customer Limit'))
                            ->numeric()
                            ->nullable()
                            ->helperText(__('Leave empty for unlimited')),

                        Forms\Components\TextInput::make('min_level_required')
                            ->label(__('Minimum Level Required'))
                            ->numeric()
                            ->nullable()
                            ->helperText(__('Minimum XP level to redeem')),
                    ])->columns(4),

                SC\Section::make(__('Validity Period'))
                    ->schema([
                        Forms\Components\DateTimePicker::make('valid_from')
                            ->label(__('Valid From'))
                            ->nullable(),

                        Forms\Components\DateTimePicker::make('valid_until')
                            ->label(__('Valid Until'))
                            ->nullable(),
                    ])->columns(2),

                SC\Section::make(__('Display Settings'))
                    ->schema([
                        Forms\Components\Toggle::make('is_active')
                            ->label(__('Active'))
                            ->default(true),

                        Forms\Components\Toggle::make('is_featured')
                            ->label(__('Featured'))
                            ->default(false),

                        Forms\Components\TextInput::make('sort_order')
                            ->label(__('Sort Order'))
                            ->numeric()
                            ->default(0),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image_url')
                    ->label(__('Image'))
                    ->circular(),

                Tables\Columns\TextColumn::make('name')
                    ->label(__('Name'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('type')
                    ->label(__('Type'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'fixed_discount' => 'Fixed Discount',
                        'percentage_discount' => 'Percentage',
                        'free_item' => 'Free Item',
                        'voucher_code' => 'Voucher',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('points_cost')
                    ->label(__('Points Cost'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('formatted_value')
                    ->label(__('Value')),

                Tables\Columns\TextColumn::make('remaining_redemptions')
                    ->label(__('Remaining'))
                    ->placeholder(__('Unlimited')),

                Tables\Columns\IconColumn::make('is_featured')
                    ->label(__('Featured'))
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('Active'))
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'fixed_discount' => __('Fixed Discount'),
                        'percentage_discount' => __('Percentage Discount'),
                        'free_item' => __('Free Item'),
                        'voucher_code' => __('Voucher Code'),
                    ]),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('Active')),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->defaultSort('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRewards::route('/'),
            'create' => Pages\CreateReward::route('/create'),
            'edit' => Pages\EditReward::route('/{record}/edit'),
        ];
    }
}
