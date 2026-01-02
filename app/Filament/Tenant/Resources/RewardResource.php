<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\RewardResource\Pages;
use App\Models\Gamification\Reward;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RewardResource extends Resource
{
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

                SC\Section::make('Basic Information')
                    ->schema([
                        Forms\Components\TextInput::make('name.en')
                            ->label('Name (English)')
                            ->required(),

                        Forms\Components\TextInput::make('name.ro')
                            ->label('Name (Romanian)')
                            ->required(),

                        Forms\Components\TextInput::make('slug')
                            ->label('Slug')
                            ->unique(ignoreRecord: true)
                            ->helperText('Auto-generated if left empty'),

                        Forms\Components\Textarea::make('description.en')
                            ->label('Description (English)')
                            ->rows(2),

                        Forms\Components\Textarea::make('description.ro')
                            ->label('Description (Romanian)')
                            ->rows(2),

                        Forms\Components\FileUpload::make('image_url')
                            ->label('Image')
                            ->image()
                            ->directory('rewards'),
                    ])->columns(2),

                SC\Section::make('Reward Configuration')
                    ->schema([
                        Forms\Components\Select::make('type')
                            ->options([
                                'fixed_discount' => 'Fixed Discount',
                                'percentage_discount' => 'Percentage Discount',
                                'free_item' => 'Free Item',
                                'voucher_code' => 'Voucher Code',
                            ])
                            ->default('fixed_discount')
                            ->required()
                            ->live(),

                        Forms\Components\TextInput::make('points_cost')
                            ->label('Points Cost')
                            ->numeric()
                            ->required()
                            ->helperText('How many points needed to redeem'),

                        Forms\Components\TextInput::make('value')
                            ->label('Value')
                            ->numeric()
                            ->required()
                            ->helperText(fn (callable $get) => match ($get('type')) {
                                'fixed_discount', 'voucher_code' => 'Discount amount in currency',
                                'percentage_discount' => 'Discount percentage (e.g., 10 for 10%)',
                                default => 'Reward value',
                            }),

                        Forms\Components\Select::make('currency')
                            ->options([
                                'RON' => 'RON',
                                'EUR' => 'EUR',
                                'USD' => 'USD',
                            ])
                            ->default('RON')
                            ->required()
                            ->visible(fn (callable $get) => in_array($get('type'), ['fixed_discount', 'voucher_code'])),

                        Forms\Components\TextInput::make('voucher_prefix')
                            ->label('Voucher Prefix')
                            ->maxLength(10)
                            ->helperText('Prefix for generated voucher codes')
                            ->visible(fn (callable $get) => $get('type') === 'voucher_code'),
                    ])->columns(3),

                SC\Section::make('Restrictions')
                    ->schema([
                        Forms\Components\TextInput::make('min_order_value')
                            ->label('Minimum Order Value')
                            ->numeric()
                            ->nullable()
                            ->helperText('Minimum order value to use this reward'),

                        Forms\Components\TextInput::make('max_redemptions_total')
                            ->label('Total Redemption Limit')
                            ->numeric()
                            ->nullable()
                            ->helperText('Leave empty for unlimited'),

                        Forms\Components\TextInput::make('max_redemptions_per_customer')
                            ->label('Per Customer Limit')
                            ->numeric()
                            ->nullable()
                            ->helperText('Leave empty for unlimited'),

                        Forms\Components\TextInput::make('min_level_required')
                            ->label('Minimum Level Required')
                            ->numeric()
                            ->nullable()
                            ->helperText('Minimum XP level to redeem'),
                    ])->columns(4),

                SC\Section::make('Validity Period')
                    ->schema([
                        Forms\Components\DateTimePicker::make('valid_from')
                            ->label('Valid From')
                            ->nullable(),

                        Forms\Components\DateTimePicker::make('valid_until')
                            ->label('Valid Until')
                            ->nullable(),
                    ])->columns(2),

                SC\Section::make('Display Settings')
                    ->schema([
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),

                        Forms\Components\Toggle::make('is_featured')
                            ->label('Featured')
                            ->default(false),

                        Forms\Components\TextInput::make('sort_order')
                            ->label('Sort Order')
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
                    ->label('Image')
                    ->circular(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'fixed_discount' => 'Fixed Discount',
                        'percentage_discount' => 'Percentage',
                        'free_item' => 'Free Item',
                        'voucher_code' => 'Voucher',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('points_cost')
                    ->label('Points Cost')
                    ->sortable(),

                Tables\Columns\TextColumn::make('formatted_value')
                    ->label('Value'),

                Tables\Columns\TextColumn::make('remaining_redemptions')
                    ->label('Remaining')
                    ->placeholder('Unlimited'),

                Tables\Columns\IconColumn::make('is_featured')
                    ->label('Featured')
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'fixed_discount' => 'Fixed Discount',
                        'percentage_discount' => 'Percentage Discount',
                        'free_item' => 'Free Item',
                        'voucher_code' => 'Voucher Code',
                    ]),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
                Tables\Filters\TernaryFilter::make('is_featured')
                    ->label('Featured'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
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
