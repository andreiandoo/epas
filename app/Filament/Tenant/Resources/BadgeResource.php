<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\BadgeResource\Pages;
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
    protected static ?string $model = Badge::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-trophy';

    protected static ?string $navigationLabel = 'Badges';

    public static function getNavigationLabel(): string
    {
        return __('Badges');
    }

    protected static \UnitEnum|string|null $navigationGroup = 'Gamification';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('Gamification');
    }

    protected static ?int $navigationSort = 48;

    protected static ?string $modelLabel = 'Badge';

    public static function getModelLabel(): string
    {
        return __('Badge');
    }

    protected static ?string $pluralModelLabel = 'Badges';

    public static function getPluralModelLabel(): string
    {
        return __('Badges');
    }

    protected static ?string $slug = 'gamification-badges';

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
                            ->label(__('Name (English)'))
                            ->required(),

                        Forms\Components\TextInput::make('name.ro')
                            ->label(__('Name (Romanian)'))
                            ->required(),

                        Forms\Components\TextInput::make('slug')
                            ->label('Slug')
                            ->unique(ignoreRecord: true)
                            ->helperText(__('Auto-generated if left empty')),

                        Forms\Components\Textarea::make('description.en')
                            ->label(__('Description (English)'))
                            ->rows(2),

                        Forms\Components\Textarea::make('description.ro')
                            ->label(__('Description (Romanian)'))
                            ->rows(2),

                        Forms\Components\FileUpload::make('icon_url')
                            ->label(__('Icon Image'))
                            ->image()
                            ->directory('badges'),

                        Forms\Components\ColorPicker::make('color')
                            ->label(__('Badge Color'))
                            ->default('#6366F1'),
                    ])->columns(2),

                SC\Section::make(__('Category & Rarity'))
                    ->schema([
                        Forms\Components\Select::make('category')
                            ->options(Badge::CATEGORIES)
                            ->default('milestone')
                            ->required(),

                        Forms\Components\Select::make('rarity_level')
                            ->label(__('Rarity'))
                            ->options(Badge::RARITIES)
                            ->default(1)
                            ->required(),
                    ])->columns(2),

                SC\Section::make(__('Rewards'))
                    ->description(__('XP and bonus points awarded when badge is earned'))
                    ->schema([
                        Forms\Components\TextInput::make('xp_reward')
                            ->label(__('XP Reward'))
                            ->numeric()
                            ->default(0)
                            ->helperText(__('Experience points awarded')),

                        Forms\Components\TextInput::make('bonus_points')
                            ->label(__('Bonus Points'))
                            ->numeric()
                            ->default(0)
                            ->helperText(__('Loyalty points awarded')),
                    ])->columns(2),

                SC\Section::make(__('Conditions'))
                    ->description(__('Define conditions for automatic badge awarding'))
                    ->schema([
                        Forms\Components\Repeater::make('conditions.rules')
                            ->label(__('Rules'))
                            ->schema([
                                Forms\Components\Select::make('metric')
                                    ->options([
                                        'events_attended' => __('Events Attended'),
                                        'reviews_submitted' => __('Reviews Submitted'),
                                        'referrals_converted' => __('Referrals Converted'),
                                        'total_badges_earned' => __('Total Badges Earned'),
                                        'current_level' => __('Current Level'),
                                        'total_xp' => __('Total XP'),
                                        'orders_count' => __('Orders Count'),
                                        'total_spent' => __('Total Spent'),
                                        'first_purchase' => __('First Purchase'),
                                    ])
                                    ->required(),

                                Forms\Components\Select::make('operator')
                                    ->options([
                                        '>=' => __('Greater than or equal'),
                                        '>' => __('Greater than'),
                                        '=' => __('Equal to'),
                                        '<' => __('Less than'),
                                        '<=' => __('Less than or equal'),
                                    ])
                                    ->default('>=')
                                    ->required(),

                                Forms\Components\TextInput::make('value')
                                    ->numeric()
                                    ->required(),
                            ])
                            ->columns(3)
                            ->defaultItems(0)
                            ->helperText(__('All conditions must be met for badge to be awarded')),
                    ]),

                SC\Section::make(__('Display Settings'))
                    ->schema([
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),

                        Forms\Components\Toggle::make('is_featured')
                            ->label('Featured')
                            ->default(false),

                        Forms\Components\Toggle::make('is_secret')
                            ->label(__('Secret Badge'))
                            ->default(false)
                            ->helperText(__('Hidden until earned')),

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
                    ->label(__('Icon'))
                    ->circular(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable(),

                Tables\Columns\TextColumn::make('category_label')
                    ->label('Category')
                    ->badge(),

                Tables\Columns\TextColumn::make('rarity_name')
                    ->label(__('Rarity'))
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
                    ->label(__('Points'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('earned_count')
                    ->label(__('Earned By'))
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
                    ->label(__('Rarity'))
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
