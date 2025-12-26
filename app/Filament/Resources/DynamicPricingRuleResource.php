<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DynamicPricingRuleResource\Pages;
use App\Models\Seating\DynamicPricingRule;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use BackedEnum;
use UnitEnum;

class DynamicPricingRuleResource extends Resource
{
    protected static ?string $model = DynamicPricingRule::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-chart-bar';
    protected static UnitEnum|string|null $navigationGroup = 'Venues & Mapping';
    protected static ?int $navigationSort = 20;
    protected static BackedEnum|string|null $navigationLabel = 'Dynamic Pricing Rules';
    protected static ?string $modelLabel = 'Dynamic Pricing Rule';
    protected static ?string $pluralModelLabel = 'Dynamic Pricing Rules';

    // protected static ?string $navigationParentItem = 'Email Templates';

    // protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-chart-bar';

    // protected static BackedEnum|string|null $navigationLabel = 'Dynamic Pricing Rules';

    // protected static UnitEnum|string|null $navigationGroup = 'Venues';

    // protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                SC\Section::make('Rule Identification')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Descriptive name for this pricing rule')
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('description')
                            ->maxLength(1000)
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),

                SC\Section::make('Rule Scope')
                    ->schema([
                        Forms\Components\Select::make('scope')
                            ->options([
                                'global' => 'Global (all events)',
                                'event' => 'Specific Event',
                                'venue' => 'Specific Venue',
                                'section' => 'Specific Section',
                            ])
                            ->default('global')
                            ->required()
                            ->live()
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('scope_ref')
                            ->label('Scope Reference')
                            ->helperText('ID of the event, venue, or section (leave empty for global)')
                            ->nullable()
                            ->visible(fn (Get $get) => in_array($get('scope'), ['event', 'venue', 'section']))
                            ->columnSpan(1),
                    ])
                    ->columns(2),

                SC\Section::make('Pricing Strategy')
                    ->schema([
                        Forms\Components\Select::make('strategy')
                            ->options(function () {
                                $strategies = config('seating.dynamic_pricing.strategies', []);
                                return collect($strategies)->mapWithKeys(fn ($class, $key) => [$key => ucwords(str_replace('_', ' ', $key))]);
                            })
                            ->default('demand_based')
                            ->required()
                            ->helperText('Algorithm used to calculate dynamic prices')
                            ->columnSpanFull(),

                        Forms\Components\KeyValue::make('params')
                            ->label('Strategy Parameters')
                            ->keyLabel('Parameter')
                            ->valueLabel('Value')
                            ->helperText('JSON parameters for the selected strategy (e.g., {"multiplier": 1.5, "threshold": 0.8})')
                            ->columnSpanFull(),
                    ]),

                SC\Section::make('Schedule')
                    ->schema([
                        Forms\Components\DateTimePicker::make('effective_from')
                            ->label('Effective From')
                            ->native(false)
                            ->nullable()
                            ->helperText('Leave empty for immediate effect')
                            ->columnSpan(1),

                        Forms\Components\DateTimePicker::make('effective_to')
                            ->label('Effective To')
                            ->native(false)
                            ->nullable()
                            ->helperText('Leave empty for no expiration')
                            ->columnSpan(1),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Only active rules are applied during repricing')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                SC\Section::make('Execution Settings')
                    ->schema([
                        Forms\Components\TextInput::make('priority')
                            ->numeric()
                            ->default(0)
                            ->helperText('Lower numbers execute first when multiple rules apply')
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('execution_frequency_minutes')
                            ->label('Execution Frequency (minutes)')
                            ->numeric()
                            ->default(60)
                            ->minValue(1)
                            ->helperText('How often this rule should be recalculated (e.g., 60 = hourly)')
                            ->columnSpan(1),

                        Forms\Components\DateTimePicker::make('last_executed_at')
                            ->label('Last Executed')
                            ->native(false)
                            ->disabled()
                            ->helperText('Auto-updated when rule runs')
                            ->columnSpan(1),
                    ])
                    ->columns(3),
            ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->url(fn ($record) => static::getUrl('edit', ['record' => $record])),

                Tables\Columns\TextColumn::make('scope')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'global' => 'success',
                        'event' => 'info',
                        'venue' => 'warning',
                        'section' => 'gray',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('strategy')
                    ->formatStateUsing(fn (string $state): string => ucwords(str_replace('_', ' ', $state)))
                    ->badge()
                    ->color('primary'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('priority')
                    ->sortable(),

                Tables\Columns\TextColumn::make('effective_from')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('effective_to')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('last_executed_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('scope')
                    ->options([
                        'global' => 'Global',
                        'event' => 'Event',
                        'venue' => 'Venue',
                        'section' => 'Section',
                    ]),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active')
                    ->placeholder('All rules')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only'),

                Tables\Filters\SelectFilter::make('strategy')
                    ->options(function () {
                        $strategies = config('seating.dynamic_pricing.strategies', []);
                        return collect($strategies)->mapWithKeys(fn ($class, $key) => [$key => ucwords(str_replace('_', ' ', $key))]);
                    }),
            ])
            ->defaultSort('priority');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDynamicPricingRules::route('/'),
            'create' => Pages\CreateDynamicPricingRule::route('/create'),
            'edit' => Pages\EditDynamicPricingRule::route('/{record}/edit'),
        ];
    }
}
