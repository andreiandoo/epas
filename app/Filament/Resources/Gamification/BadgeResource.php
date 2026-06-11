<?php

namespace App\Filament\Resources\Gamification;

use App\Filament\Resources\Gamification\BadgeResource\Pages;
use App\Models\Gamification\Badge;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Tables;
use Filament\Tables\Table;

class BadgeResource extends Resource
{
    protected static ?string $model = Badge::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-trophy';

    protected static ?string $navigationLabel = 'Badges';

    protected static \UnitEnum|string|null $navigationGroup = 'Gamification';

    protected static ?int $navigationSort = 92;

    protected static ?string $slug = 'gamification/badges';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                SC\Section::make('Ownership')
                    ->schema([
                        Forms\Components\Select::make('tenant_id')
                            ->relationship('tenant', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable(),

                        Forms\Components\Select::make('marketplace_client_id')
                            ->relationship('marketplaceClient', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable(),
                    ])->columns(2),

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
                            ->unique(ignoreRecord: true),

                        Forms\Components\Select::make('category')
                            ->options(Badge::CATEGORIES)
                            ->required(),

                        Forms\Components\Select::make('rarity_level')
                            ->label('Rarity')
                            ->options(Badge::RARITIES)
                            ->required(),

                        Forms\Components\ColorPicker::make('color')
                            ->label('Color'),
                    ])->columns(2),

                SC\Section::make('Rewards')
                    ->schema([
                        Forms\Components\TextInput::make('xp_reward')
                            ->label('XP Reward')
                            ->numeric()
                            ->default(0),

                        Forms\Components\TextInput::make('bonus_points')
                            ->label('Bonus Points')
                            ->numeric()
                            ->default(0),
                    ])->columns(2),

                SC\Section::make('Status')
                    ->schema([
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),

                        Forms\Components\Toggle::make('is_secret')
                            ->label('Secret')
                            ->default(false),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('tenant.name')
                    ->label('Tenant')
                    ->placeholder('(Marketplace)')
                    ->sortable(),

                Tables\Columns\TextColumn::make('marketplaceClient.name')
                    ->label('Marketplace')
                    ->placeholder('(Tenant)')
                    ->sortable(),

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
                    ->label('XP'),

                Tables\Columns\TextColumn::make('earned_count')
                    ->label('Earned By'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tenant_id')
                    ->label('Tenant')
                    ->relationship('tenant', 'name'),
                Tables\Filters\SelectFilter::make('marketplace_client_id')
                    ->label('Marketplace')
                    ->relationship('marketplaceClient', 'name'),
                Tables\Filters\SelectFilter::make('category')
                    ->options(Badge::CATEGORIES),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
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
