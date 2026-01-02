<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Filament\Marketplace\Resources\ExperienceActionResource\Pages;
use App\Models\Gamification\ExperienceAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ExperienceActionResource extends Resource
{
    use HasMarketplaceContext;

    protected static ?string $model = ExperienceAction::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-bolt';

    protected static ?string $navigationLabel = 'XP Actions';

    protected static \UnitEnum|string|null $navigationGroup = 'Gamification';

    protected static ?int $navigationSort = 50;

    protected static ?string $modelLabel = 'XP Action';

    protected static ?string $pluralModelLabel = 'XP Actions';

    protected static ?string $slug = 'gamification-xp-actions';

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

                SC\Section::make('Action Configuration')
                    ->schema([
                        Forms\Components\Select::make('action_type')
                            ->options(ExperienceAction::ACTION_TYPES)
                            ->required()
                            ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->where('marketplace_client_id', static::getMarketplaceClientId())),

                        Forms\Components\TextInput::make('name.en')
                            ->label('Name (English)')
                            ->required(),

                        Forms\Components\TextInput::make('name.ro')
                            ->label('Name (Romanian)')
                            ->required(),

                        Forms\Components\Textarea::make('description.en')
                            ->label('Description (English)')
                            ->rows(2),

                        Forms\Components\Textarea::make('description.ro')
                            ->label('Description (Romanian)')
                            ->rows(2),
                    ])->columns(2),

                SC\Section::make('XP Calculation')
                    ->schema([
                        Forms\Components\Select::make('xp_type')
                            ->options([
                                'fixed' => 'Fixed Amount',
                                'per_currency' => 'Per Currency Unit (e.g., per RON spent)',
                                'multiplier' => 'Multiplier',
                            ])
                            ->default('fixed')
                            ->required()
                            ->live(),

                        Forms\Components\TextInput::make('xp_amount')
                            ->label('XP Amount')
                            ->numeric()
                            ->default(0)
                            ->required()
                            ->helperText(fn (callable $get) => match ($get('xp_type')) {
                                'fixed' => 'Fixed XP awarded per action',
                                'per_currency' => 'Base XP (0 if using per_currency)',
                                'multiplier' => 'Base XP to multiply',
                                default => 'XP amount',
                            }),

                        Forms\Components\TextInput::make('xp_per_currency_unit')
                            ->label('XP per Currency Unit')
                            ->numeric()
                            ->step(0.01)
                            ->default(1)
                            ->helperText('e.g., 1 = 1 XP per RON spent')
                            ->visible(fn (callable $get) => in_array($get('xp_type'), ['per_currency', 'multiplier'])),

                        Forms\Components\TextInput::make('max_xp_per_action')
                            ->label('Max XP per Action')
                            ->numeric()
                            ->nullable()
                            ->helperText('Cap on XP from single action (leave empty for no limit)'),
                    ])->columns(2),

                SC\Section::make('Rate Limiting')
                    ->schema([
                        Forms\Components\TextInput::make('max_times_per_day')
                            ->label('Max Times per Day')
                            ->numeric()
                            ->nullable()
                            ->helperText('How many times can earn per day (leave empty for unlimited)'),

                        Forms\Components\TextInput::make('cooldown_hours')
                            ->label('Cooldown (hours)')
                            ->numeric()
                            ->nullable()
                            ->helperText('Hours between earning from same action'),
                    ])->columns(2),

                Forms\Components\Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('action_type_label')
                    ->label('Action')
                    ->searchable(query: fn ($query, $search) => $query->where('action_type', 'like', "%{$search}%")),

                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable(),

                Tables\Columns\TextColumn::make('xp_type_label')
                    ->label('XP Type')
                    ->badge(),

                Tables\Columns\TextColumn::make('xp_amount')
                    ->label('XP Amount'),

                Tables\Columns\TextColumn::make('xp_per_currency_unit')
                    ->label('Per Currency')
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('max_times_per_day')
                    ->label('Daily Limit')
                    ->placeholder('Unlimited'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExperienceActions::route('/'),
            'create' => Pages\CreateExperienceAction::route('/create'),
            'edit' => Pages\EditExperienceAction::route('/{record}/edit'),
        ];
    }
}
