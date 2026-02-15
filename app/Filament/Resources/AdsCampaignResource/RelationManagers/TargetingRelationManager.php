<?php

namespace App\Filament\Resources\AdsCampaignResource\RelationManagers;

use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions;

class TargetingRelationManager extends RelationManager
{
    protected static string $relationship = 'targeting';

    protected static ?string $title = 'Audience Targeting';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                SC\Section::make('Demographics')
                    ->schema([
                        Forms\Components\TextInput::make('age_min')
                            ->label('Minimum Age')
                            ->numeric()
                            ->default(18)
                            ->minValue(13)
                            ->maxValue(65),

                        Forms\Components\TextInput::make('age_max')
                            ->label('Maximum Age')
                            ->numeric()
                            ->default(55)
                            ->minValue(18)
                            ->maxValue(65),

                        Forms\Components\CheckboxList::make('genders')
                            ->options([
                                'all' => 'All Genders',
                                'male' => 'Male',
                                'female' => 'Female',
                            ])
                            ->default(['all'])
                            ->columns(3),

                        Forms\Components\TagsInput::make('languages')
                            ->label('Languages')
                            ->placeholder('e.g., ro, en, hu')
                            ->helperText('ISO 639-1 language codes'),
                    ])->columns(2),

                SC\Section::make('Location Targeting')
                    ->schema([
                        Forms\Components\Repeater::make('locations')
                            ->label('Target Locations')
                            ->schema([
                                Forms\Components\Select::make('type')
                                    ->options([
                                        'country' => 'Country',
                                        'region' => 'Region/State',
                                        'city' => 'City',
                                    ])
                                    ->default('country')
                                    ->required(),

                                Forms\Components\TextInput::make('id')
                                    ->label('Location ID/Code')
                                    ->required()
                                    ->helperText('Country: ISO code (RO), City: FB/Google ID'),

                                Forms\Components\TextInput::make('name')
                                    ->label('Display Name')
                                    ->required(),

                                Forms\Components\TextInput::make('radius_km')
                                    ->label('Radius (km)')
                                    ->numeric()
                                    ->visible(fn (Forms\Get $get) => $get('type') === 'city')
                                    ->default(25),
                            ])
                            ->columns(4)
                            ->defaultItems(1)
                            ->addActionLabel('Add Location'),

                        Forms\Components\Select::make('location_type')
                            ->label('People in this location')
                            ->options([
                                'everyone' => 'Everyone in this location',
                                'living_in' => 'People living in this location',
                                'recently_in' => 'People recently in this location',
                                'traveling_in' => 'People traveling in this location',
                            ])
                            ->default('everyone'),
                    ]),

                SC\Section::make('Interest & Behavior Targeting')
                    ->schema([
                        Forms\Components\Repeater::make('interests')
                            ->label('Interests')
                            ->schema([
                                Forms\Components\TextInput::make('id')
                                    ->label('Interest ID')
                                    ->helperText('Facebook/Google interest ID'),
                                Forms\Components\TextInput::make('name')
                                    ->label('Interest Name')
                                    ->required(),
                            ])
                            ->columns(2)
                            ->defaultItems(0)
                            ->addActionLabel('Add Interest')
                            ->helperText('Target people interested in: concerts, festivals, nightlife, specific music genres, etc.'),

                        Forms\Components\Repeater::make('behaviors')
                            ->label('Behaviors')
                            ->schema([
                                Forms\Components\TextInput::make('id')
                                    ->label('Behavior ID'),
                                Forms\Components\TextInput::make('name')
                                    ->label('Behavior Name')
                                    ->required(),
                            ])
                            ->columns(2)
                            ->defaultItems(0)
                            ->addActionLabel('Add Behavior'),
                    ])->collapsible(),

                SC\Section::make('Custom Audiences & Retargeting')
                    ->schema([
                        Forms\Components\TagsInput::make('custom_audience_ids')
                            ->label('Custom Audience IDs')
                            ->helperText('Facebook/Google custom audience IDs to include'),

                        Forms\Components\TagsInput::make('excluded_audience_ids')
                            ->label('Excluded Audience IDs')
                            ->helperText('Audiences to exclude (e.g., already purchased)'),

                        Forms\Components\KeyValue::make('lookalike_config')
                            ->label('Lookalike Audience')
                            ->keyLabel('Setting')
                            ->valueLabel('Value')
                            ->helperText('source_audience_id, percentage (1-10), country'),
                    ])->collapsible(),

                SC\Section::make('Placements & Devices')
                    ->schema([
                        Forms\Components\Toggle::make('automatic_placements')
                            ->label('Automatic Placements')
                            ->default(true)
                            ->reactive()
                            ->helperText('Let platforms choose optimal placements'),

                        Forms\Components\KeyValue::make('placements')
                            ->label('Manual Placements')
                            ->visible(fn (Forms\Get $get) => !$get('automatic_placements'))
                            ->helperText('facebook: feed,stories,reels | instagram: feed,stories,explore | google: search,display,youtube'),

                        Forms\Components\CheckboxList::make('devices')
                            ->options([
                                'mobile' => 'Mobile',
                                'desktop' => 'Desktop',
                                'tablet' => 'Tablet',
                            ])
                            ->default(['mobile', 'desktop'])
                            ->columns(3),

                        Forms\Components\Select::make('variant_label')
                            ->label('A/B Variant (for audience testing)')
                            ->options([
                                'A' => 'Variant A',
                                'B' => 'Variant B',
                            ])
                            ->placeholder('No variant'),
                    ])->collapsible(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('age_min')
                    ->label('Age')
                    ->formatStateUsing(fn ($state, $record) => "{$record->age_min}-{$record->age_max}"),

                Tables\Columns\TextColumn::make('genders')
                    ->formatStateUsing(fn ($state) => is_array($state) ? implode(', ', array_map('ucfirst', $state)) : $state),

                Tables\Columns\TextColumn::make('locations')
                    ->formatStateUsing(function ($state) {
                        if (!is_array($state)) return '-';
                        return implode(', ', array_map(fn ($l) => $l['name'] ?? $l['id'], $state));
                    })
                    ->limit(40),

                Tables\Columns\TextColumn::make('interests')
                    ->formatStateUsing(function ($state) {
                        if (!is_array($state)) return '-';
                        return implode(', ', array_map(fn ($i) => $i['name'], $state));
                    })
                    ->limit(40),

                Tables\Columns\IconColumn::make('automatic_placements')
                    ->label('Auto Place')
                    ->boolean(),

                Tables\Columns\TextColumn::make('variant_label')
                    ->label('Variant')
                    ->badge(),
            ])
            ->recordActions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->headerActions([
                Actions\CreateAction::make(),
            ]);
    }
}
