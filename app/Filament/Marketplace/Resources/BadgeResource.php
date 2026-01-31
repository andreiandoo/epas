<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Filament\Marketplace\Resources\BadgeResource\Pages;
use App\Models\Gamification\Badge;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BadgeResource extends Resource
{
    use HasMarketplaceContext;

    protected static ?string $model = Badge::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-trophy';

    protected static ?string $navigationLabel = 'Badges';

    protected static \UnitEnum|string|null $navigationGroup = 'Gamification';

    protected static ?int $navigationSort = 48;

    protected static ?string $modelLabel = 'Badge';

    protected static ?string $pluralModelLabel = 'Badges';

    protected static ?string $slug = 'gamification-badges';

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

                        Forms\Components\FileUpload::make('icon_url')
                            ->label('Icon Image')
                            ->image()
                            ->directory('badges'),

                        Forms\Components\ColorPicker::make('color')
                            ->label('Badge Color')
                            ->default('#6366F1'),
                    ])->columns(2),

                SC\Section::make('Category & Rarity')
                    ->schema([
                        Forms\Components\Select::make('category')
                            ->options(Badge::CATEGORIES)
                            ->default('milestone')
                            ->required(),

                        Forms\Components\Select::make('rarity_level')
                            ->label('Rarity')
                            ->options(Badge::RARITIES)
                            ->default(1)
                            ->required(),
                    ])->columns(2),

                SC\Section::make('Rewards')
                    ->description('XP and bonus points awarded when badge is earned')
                    ->schema([
                        Forms\Components\TextInput::make('xp_reward')
                            ->label('XP Reward')
                            ->numeric()
                            ->default(0)
                            ->helperText('Experience points awarded'),

                        Forms\Components\TextInput::make('bonus_points')
                            ->label('Bonus Points')
                            ->numeric()
                            ->default(0)
                            ->helperText('Loyalty points awarded'),
                    ])->columns(2),

                SC\Section::make('Conditions')
                    ->description('Define conditions for automatic badge awarding')
                    ->schema([
                        Forms\Components\Repeater::make('conditions.rules')
                            ->label('Rules')
                            ->schema([
                                Forms\Components\Select::make('metric')
                                    ->options([
                                        'events_attended' => 'Events Attended',
                                        'reviews_submitted' => 'Reviews Submitted',
                                        'referrals_converted' => 'Referrals Converted',
                                        'total_badges_earned' => 'Total Badges Earned',
                                        'current_level' => 'Current Level',
                                        'total_xp' => 'Total XP',
                                        'orders_count' => 'Orders Count',
                                        'total_spent' => 'Total Spent',
                                        'first_purchase' => 'First Purchase',
                                    ])
                                    ->required(),

                                Forms\Components\Select::make('operator')
                                    ->options([
                                        '>=' => 'Greater than or equal',
                                        '>' => 'Greater than',
                                        '=' => 'Equal to',
                                        '<' => 'Less than',
                                        '<=' => 'Less than or equal',
                                    ])
                                    ->default('>=')
                                    ->required(),

                                Forms\Components\TextInput::make('value')
                                    ->numeric()
                                    ->required(),
                            ])
                            ->columns(3)
                            ->defaultItems(0)
                            ->helperText('All conditions must be met for badge to be awarded'),
                    ]),

                SC\Section::make('Display Settings')
                    ->schema([
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),

                        Forms\Components\Toggle::make('is_featured')
                            ->label('Featured')
                            ->default(false),

                        Forms\Components\Toggle::make('is_secret')
                            ->label('Secret Badge')
                            ->default(false)
                            ->helperText('Hidden until earned'),

                        Forms\Components\TextInput::make('sort_order')
                            ->label('Sort Order')
                            ->numeric()
                            ->default(0),
                    ])->columns(4),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('icon_url')
                    ->label('Icon')
                    ->circular(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable(),

                Tables\Columns\TextColumn::make('category_label')
                    ->label('Category')
                    ->badge(),

                Tables\Columns\TextColumn::make('rarity_name')
                    ->label('Rarity')
                    ->badge()
                    ->color(fn ($record) => match ($record->rarity_level) {
                        1 => 'gray',
                        2 => 'success',
                        3 => 'info',
                        4 => 'warning',
                        5 => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('xp_reward')
                    ->label('XP')
                    ->sortable(),

                Tables\Columns\TextColumn::make('bonus_points')
                    ->label('Points')
                    ->sortable(),

                Tables\Columns\TextColumn::make('earned_count')
                    ->label('Earned By')
                    ->suffix(' customers'),

                Tables\Columns\IconColumn::make('is_secret')
                    ->label('Secret')
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->options(Badge::CATEGORIES),
                Tables\Filters\SelectFilter::make('rarity_level')
                    ->label('Rarity')
                    ->options(Badge::RARITIES),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->defaultSort('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBadges::route('/'),
            'create' => Pages\CreateBadge::route('/create'),
            'edit' => Pages\EditBadge::route('/{record}/edit'),
        ];
    }
}
