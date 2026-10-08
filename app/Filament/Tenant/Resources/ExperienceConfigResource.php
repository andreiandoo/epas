<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\ExperienceConfigResource\Pages;
use App\Models\Gamification\ExperienceConfig;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ExperienceConfigResource extends Resource
{
    protected static ?string $model = ExperienceConfig::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-arrow-trending-up';

    protected static ?string $navigationLabel = 'Experience & Levels';

    public static function getNavigationLabel(): string
    {
        return __('Experience & Levels');
    }

    protected static \UnitEnum|string|null $navigationGroup = 'Gamification';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('Gamification');
    }

    protected static ?int $navigationSort = 49;

    protected static ?string $modelLabel = 'Experience Configuration';

    public static function getModelLabel(): string
    {
        return __('Experience Configuration');
    }

    protected static ?string $pluralModelLabel = 'Experience Configuration';

    public static function getPluralModelLabel(): string
    {
        return __('Experience Configuration');
    }

    protected static ?string $slug = 'gamification-experience';

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

                SC\Section::make(__('Naming'))
                    ->description(__('Customize how XP and levels are displayed'))
                    ->schema([
                        Forms\Components\TextInput::make('xp_name.en')
                            ->label(__('XP Name (English)'))
                            ->default('Experience'),

                        Forms\Components\TextInput::make('xp_name.ro')
                            ->label(__('XP Name (Romanian)'))
                            ->default('Experiență'),

                        Forms\Components\TextInput::make('level_name.en')
                            ->label(__('Level Name (English)'))
                            ->default('Level'),

                        Forms\Components\TextInput::make('level_name.ro')
                            ->label(__('Level Name (Romanian)'))
                            ->default('Nivel'),

                        Forms\Components\Select::make('icon')
                            ->options([
                                'star' => __('Star'),
                                'sparkles' => __('Sparkles'),
                                'bolt' => __('Lightning'),
                                'fire' => __('Fire'),
                                'trophy' => __('Trophy'),
                            ])
                            ->default('star'),
                    ])->columns(3),

                SC\Section::make(__('Level Progression'))
                    ->description(__('Configure how XP translates to levels'))
                    ->schema([
                        Forms\Components\Select::make('level_formula')
                            ->options([
                                'linear' => __('Linear (same XP per level)'),
                                'exponential' => __('Exponential (increasing XP per level)'),
                                'custom' => __('Custom (define each level)'),
                            ])
                            ->default('exponential')
                            ->required()
                            ->live(),

                        Forms\Components\TextInput::make('base_xp_per_level')
                            ->label(__('Base XP per Level'))
                            ->numeric()
                            ->default(100)
                            ->required()
                            ->helperText(__('XP needed for level 1→2')),

                        Forms\Components\TextInput::make('level_multiplier')
                            ->label(__('Level Multiplier'))
                            ->numeric()
                            ->step(0.1)
                            ->default(1.5)
                            ->helperText(__('For exponential: each level needs base × multiplier^(level-1)'))
                            ->visible(fn (callable $get) => $get('level_formula') === 'exponential'),

                        Forms\Components\TextInput::make('max_level')
                            ->label(__('Maximum Level'))
                            ->numeric()
                            ->default(100)
                            ->required(),
                    ])->columns(2),

                SC\Section::make(__('Level Groups'))
                    ->description(__('Group levels into tiers (e.g., Bronze 1-5, Silver 6-10)'))
                    ->schema([
                        Forms\Components\Repeater::make('level_groups')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label(__('Group Name'))
                                    ->required(),
                                Forms\Components\TextInput::make('min_level')
                                    ->label(__('Min Level'))
                                    ->numeric()
                                    ->required(),
                                Forms\Components\TextInput::make('max_level')
                                    ->label(__('Max Level'))
                                    ->numeric()
                                    ->required(),
                                Forms\Components\ColorPicker::make('color')
                                    ->label(__('Color'))
                                    ->default('#6366F1'),
                            ])
                            ->columns(4)
                            ->itemLabel(fn (array $state): ?string => isset($state['name'], $state['min_level'], $state['max_level'])
                                ? "{$state['name']} (Levels {$state['min_level']}-{$state['max_level']})"
                                : null)
                            ->collapsible()
                            ->defaultItems(0),
                    ]),

                SC\Section::make(__('Level Rewards'))
                    ->description(__('Award bonuses at specific levels'))
                    ->schema([
                        Forms\Components\Repeater::make('level_rewards')
                            ->schema([
                                Forms\Components\TextInput::make('level')
                                    ->label(__('At Level'))
                                    ->numeric()
                                    ->required(),
                                Forms\Components\TextInput::make('bonus_points')
                                    ->label(__('Bonus Points'))
                                    ->numeric()
                                    ->default(0),
                                Forms\Components\Select::make('badge_id')
                                    ->label(__('Award Badge'))
                                    ->relationship('badges', 'name->en')
                                    ->nullable(),
                            ])
                            ->columns(3)
                            ->itemLabel(fn (array $state): ?string => isset($state['level'])
                                ? "Level {$state['level']} Reward"
                                : null)
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

                Tables\Columns\TextColumn::make('level_formula')
                    ->label(__('Formula'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => ucfirst($state)),

                Tables\Columns\TextColumn::make('base_xp_per_level')
                    ->label(__('Base XP')),

                Tables\Columns\TextColumn::make('level_multiplier')
                    ->label(__('Multiplier')),

                Tables\Columns\TextColumn::make('max_level')
                    ->label(__('Max Level')),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('Last Updated'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExperienceConfigs::route('/'),
            'create' => Pages\CreateExperienceConfig::route('/create'),
            'edit' => Pages\EditExperienceConfig::route('/{record}/edit'),
        ];
    }
}
