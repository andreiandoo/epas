<?php

namespace App\Filament\Marketplace\Resources\ActivityResource\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components as SC;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Variants tab on the Activity edit page.
 *
 * Lets the organizer manage pricing variants attached to the parent
 * activity. Field shape mirrors ticket_types so the same checkout layer
 * can branch only on which FK is populated.
 */
class ActivityVariantsRelationManager extends RelationManager
{
    protected static string $relationship = 'variants';

    protected static ?string $title = 'Variante de preț';

    protected static ?string $modelLabel = 'Variantă';

    protected static ?string $pluralModelLabel = 'Variante';

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            SC\Section::make('Identitate variantă')
                ->schema([
                    SC\Tabs::make('Name Translations')
                        ->tabs([
                            SC\Tabs\Tab::make('Română')
                                ->schema([
                                    Forms\Components\TextInput::make('name.ro')
                                        ->label('Nume (RO)')
                                        ->required()
                                        ->maxLength(120)
                                        ->placeholder('ex: Adult, Copil, Grup 4 persoane'),
                                    Forms\Components\Textarea::make('description.ro')
                                        ->label('Descriere (RO)')
                                        ->rows(2)
                                        ->maxLength(280),
                                ]),
                            SC\Tabs\Tab::make('English')
                                ->schema([
                                    Forms\Components\TextInput::make('name.en')
                                        ->label('Name (EN)')
                                        ->maxLength(120),
                                    Forms\Components\Textarea::make('description.en')
                                        ->label('Description (EN)')
                                        ->rows(2)
                                        ->maxLength(280),
                                ]),
                        ])->columnSpanFull(),

                    Forms\Components\TextInput::make('sku')
                        ->label('SKU')
                        ->maxLength(64)
                        ->placeholder('opțional')
                        ->columnSpanFull(),
                ])
                ->columns(1),

            SC\Section::make('Preț')
                ->schema([
                    // Storage column is `price_cents` (integer, bani). Admin types in
                    // the value as absolute RON ("95" or "94.50"). formatStateUsing
                    // converts on form load, dehydrateStateUsing converts back on
                    // save — DB stays in cents, no schema change needed.
                    Forms\Components\TextInput::make('price_cents')
                        ->label('Preț')
                        ->numeric()
                        ->required()
                        ->default(0)
                        ->minValue(0)
                        ->step(0.01)
                        ->suffix('lei')
                        ->helperText('Suma în lei. Ex: 95 sau 94.50.')
                        ->formatStateUsing(fn ($state) => $state !== null ? round($state / 100, 2) : null)
                        ->dehydrateStateUsing(fn ($state) => $state !== null && $state !== '' ? (int) round(((float) $state) * 100) : null)
                        ->columnSpanFull(),

                    Forms\Components\Select::make('currency')
                        ->label('Monedă')
                        ->options(['RON' => 'RON', 'EUR' => 'EUR'])
                        ->default('RON')
                        ->required()
                        ->columnSpanFull(),
                ])
                ->columns(1),

            SC\Section::make('Capacitate & comandă')
                ->schema([
                    Forms\Components\TextInput::make('capacity_share')
                        ->label('Locuri ocupate / unitate')
                        ->numeric()
                        ->default(1)
                        ->minValue(1)
                        ->maxValue(50)
                        ->required()
                        ->helperText('1 = o persoană. Pentru "Grup 4 persoane" → 4.')
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('min_per_order')
                        ->label('Minim per comandă')
                        ->numeric()
                        ->default(0)
                        ->minValue(0)
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('max_per_order')
                        ->label('Maxim per comandă')
                        ->numeric()
                        ->default(10)
                        ->minValue(1)
                        ->columnSpanFull(),
                ])
                ->columns(1),

            SC\Section::make('Vârstă')
                ->schema([
                    Forms\Components\TextInput::make('min_age')
                        ->label('Vârsta minimă')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(99)
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('max_age')
                        ->label('Vârsta maximă')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(99)
                        ->columnSpanFull(),
                ])
                ->columns(1)
                ->collapsed(),

            SC\Section::make('Comision (override)')
                ->description('Lasă gol pentru a moșteni comisionul de la organizator / marketplace.')
                ->collapsed()
                ->schema([
                    Forms\Components\Select::make('commission_type')
                        ->label('Tip comision')
                        ->options([
                            'percentage' => 'Procent',
                            'fixed' => 'Sumă fixă',
                            'both' => 'Procent + sumă fixă',
                        ])
                        ->placeholder('Moștenește')
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('commission_rate')
                        ->label('Procent (%)')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(100)
                        ->step(0.01)
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('commission_fixed')
                        ->label('Sumă fixă (RON)')
                        ->numeric()
                        ->minValue(0)
                        ->step(0.01)
                        ->columnSpanFull(),
                    Forms\Components\Select::make('commission_mode')
                        ->label('Mod aplicare')
                        ->options([
                            'included' => 'Inclus în preț',
                            'added_on_top' => 'Adăugat peste preț',
                        ])
                        ->placeholder('Moștenește')
                        ->columnSpanFull(),
                ])
                ->columns(1),

            SC\Section::make('Detalii suplimentare')
                ->collapsed()
                ->schema([
                    Forms\Components\TagsInput::make('perks')
                        ->label('Perks / beneficii')
                        ->placeholder('Acces VIP, drink, manual…')
                        ->columnSpanFull(),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Activă')
                        ->default(true)
                        ->columnSpanFull(),

                    Forms\Components\Toggle::make('is_refundable')
                        ->label('Refundabilă')
                        ->default(true)
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('sort_order')
                        ->label('Ordine afișare')
                        ->numeric()
                        ->default(0)
                        ->columnSpanFull(),
                ])
                ->columns(1),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('sku')
            ->columns([
                Tables\Columns\TextColumn::make('name.ro')
                    ->label('Nume')
                    ->searchable(),

                Tables\Columns\TextColumn::make('price_cents')
                    ->label('Preț')
                    ->formatStateUsing(fn ($state, $record) => $state
                        ? number_format($state / 100, 2, ',', '.') . ' ' . ($record->currency ?? 'RON')
                        : '—')
                    ->alignRight()
                    ->sortable(),

                Tables\Columns\TextColumn::make('capacity_share')
                    ->label('Locuri')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('min_age')
                    ->label('Vârstă')
                    ->formatStateUsing(function ($state, $record) {
                        $min = $record->min_age;
                        $max = $record->max_age;
                        if ($min !== null && $max !== null) return "{$min}–{$max}";
                        if ($min !== null) return "≥{$min}";
                        if ($max !== null) return "≤{$max}";
                        return '—';
                    }),

                Tables\Columns\IconColumn::make('is_active')->label('Activă')->boolean(),
                Tables\Columns\IconColumn::make('is_refundable')->label('Refundabilă')->boolean(),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Ordine')
                    ->sortable(),
            ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->headerActions([
                CreateAction::make()->label('Adaugă variantă'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
