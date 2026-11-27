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
            SC\Section::make(__('Identitate variantă'))
                ->schema([
                    SC\Tabs::make('Name Translations')
                        ->tabs([
                            SC\Tabs\Tab::make('Română')
                                ->schema([
                                    Forms\Components\TextInput::make('name.ro')
                                        ->label(__('Nume (RO)'))
                                        ->required()
                                        ->maxLength(120)
                                        ->placeholder(__('ex: Adult, Copil, Grup 4 persoane')),
                                    Forms\Components\Textarea::make('description.ro')
                                        ->label(__('Descriere (RO)'))
                                        ->rows(2)
                                        ->maxLength(280),
                                ]),
                            SC\Tabs\Tab::make('English')
                                ->schema([
                                    Forms\Components\TextInput::make('name.en')
                                        ->label(__('Name (EN)'))
                                        ->maxLength(120),
                                    Forms\Components\Textarea::make('description.en')
                                        ->label(__('Description (EN)'))
                                        ->rows(2)
                                        ->maxLength(280),
                                ]),
                        ])->columnSpanFull(),

                    Forms\Components\TextInput::make('sku')
                        ->label(__('SKU'))
                        ->maxLength(64)
                        ->placeholder(__('opțional'))
                        ->columnSpanFull(),
                ])
                ->columns(1),

            SC\Section::make(__('Preț'))
                ->schema([
                    // Storage column is `price_cents` (integer, bani). Admin types in
                    // the value as absolute RON ("95" or "94.50"). formatStateUsing
                    // converts on form load, dehydrateStateUsing converts back on
                    // save — DB stays in cents, no schema change needed.
                    Forms\Components\TextInput::make('price_cents')
                        ->label(__('Preț'))
                        ->numeric()
                        ->required()
                        ->default(0)
                        ->minValue(0)
                        ->step(0.01)
                        ->suffix('lei')
                        ->helperText(__('Suma în lei. Ex: 95 sau 94.50.'))
                        ->formatStateUsing(fn ($state) => $state !== null ? round($state / 100, 2) : null)
                        ->dehydrateStateUsing(fn ($state) => $state !== null && $state !== '' ? (int) round(((float) $state) * 100) : null)
                        ->columnSpanFull(),

                    Forms\Components\Select::make('currency')
                        ->label(__('Monedă'))
                        // An operator with a selling currency of his own has every variant saved in it
                        // (ActivityVariantObserver), whatever is chosen here.
                        ->options(array_combine(array_keys(\App\Services\Activities\ActivityCurrency::CHOICES), array_keys(\App\Services\Activities\ActivityCurrency::CHOICES)))
                        ->default('RON')
                        ->required()
                        ->columnSpanFull(),
                ])
                ->columns(1),

            SC\Section::make(__('Capacitate & comandă'))
                ->schema([
                    Forms\Components\TextInput::make('capacity_share')
                        ->label(__('Locuri ocupate / unitate'))
                        ->numeric()
                        ->default(1)
                        ->minValue(1)
                        ->maxValue(50)
                        ->required()
                        ->helperText(__('1 = o persoană. Pentru "Grup 4 persoane" → 4.'))
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('min_per_order')
                        ->label(__('Minim per comandă'))
                        ->numeric()
                        ->default(0)
                        ->minValue(0)
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('max_per_order')
                        ->label(__('Maxim per comandă'))
                        ->numeric()
                        ->default(10)
                        ->minValue(1)
                        ->columnSpanFull(),
                ])
                ->columns(1),

            SC\Section::make(__('Tip, durată, grup'))
                ->description(__('Adult / copil, bilete pe mai multe zile, durate diferite (30 / 60 min), grupuri, unități (barcă, mașină).'))
                ->schema([
                    Forms\Components\Select::make('price_type')
                        ->label(__('Prețul este'))
                        ->options([
                            'per_person' => __('pe persoană'),
                            'per_unit' => __('pe unitate (barcă, mașină, familie)'),
                        ])
                        ->default('per_person')
                        ->required(),
                    Forms\Components\Toggle::make('is_child')
                        ->label(__('Bilet de copil'))
                        ->helperText(__('Contează la regula „bilet de acces pentru adult”.')),
                    Forms\Components\TextInput::make('persons_min')
                        ->label(__('Persoane (minim)'))
                        ->numeric()->minValue(1)->maxValue(500),
                    Forms\Components\TextInput::make('persons_max')
                        ->label(__('Persoane (maxim)'))
                        ->numeric()->minValue(1)->maxValue(500)
                        ->helperText(__('Câte persoane acoperă o unitate (ex: 4 într-o barcă).')),
                    Forms\Components\TextInput::make('duration_minutes')
                        ->label(__('Durată (minute)'))
                        ->numeric()->minValue(5)->maxValue(1440)
                        ->helperText(__('Lasă gol pentru durata produsului. Ex: 30 sau 60 la închirieri.')),
                    Forms\Components\TextInput::make('validity_days')
                        ->label(__('Valabil (zile)'))
                        ->numeric()->default(1)->minValue(1)->maxValue(60)
                        ->helperText(__('Bilete pe toată ziua: 2 = două zile la rând (abonament, camping).')),
                    Forms\Components\TextInput::make('step_qty')
                        ->label(__('Se cumpără câte'))
                        ->numeric()->minValue(1)->maxValue(100)
                        ->helperText(__('Gol = oricâte. Pentru grupuri, după minimul pe comandă.')),
                    Forms\Components\TextInput::make('companion_label')
                        ->label(__('Bilet gratuit de însoțitor'))
                        ->maxLength(80)
                        ->placeholder(__('ex: Ghid / însoțitor'))
                        ->helperText(__('Se emite automat când se cumpără cel puțin minimul pe comandă.')),
                    Forms\Components\TextInput::make('pos_price_cents')
                        ->label(__('Preț la casă'))
                        ->numeric()->minValue(0)->step(0.01)->suffix('lei')
                        ->helperText(__('Gol = același ca online.'))
                        ->formatStateUsing(fn ($state) => $state !== null ? round($state / 100, 2) : null)
                        ->dehydrateStateUsing(fn ($state) => $state !== null && $state !== '' ? (int) round(((float) $state) * 100) : null),
                    Forms\Components\Toggle::make('pos_only')
                        ->label(__('Doar la casă (ascuns online)')),
                ])
                ->columns(2)
                ->collapsed(),

            SC\Section::make(__('Vârstă'))
                ->schema([
                    Forms\Components\TextInput::make('min_age')
                        ->label(__('Vârsta minimă'))
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(99)
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('max_age')
                        ->label(__('Vârsta maximă'))
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(99)
                        ->columnSpanFull(),
                ])
                ->columns(1)
                ->collapsed(),

            SC\Section::make(__('Comision (override)'))
                ->description(__('Lasă gol pentru a moșteni comisionul de la organizator / marketplace.'))
                ->collapsed()
                ->schema([
                    Forms\Components\Select::make('commission_type')
                        ->label(__('Tip comision'))
                        ->options([
                            'percentage' => __('Procent'),
                            'fixed' => __('Sumă fixă'),
                            'both' => __('Procent + sumă fixă'),
                        ])
                        ->placeholder(__('Moștenește'))
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('commission_rate')
                        ->label(__('Procent (%)'))
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(100)
                        ->step(0.01)
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('commission_fixed')
                        ->label(__('Sumă fixă (RON)'))
                        ->numeric()
                        ->minValue(0)
                        ->step(0.01)
                        ->columnSpanFull(),
                    Forms\Components\Select::make('commission_mode')
                        ->label(__('Mod aplicare'))
                        ->options([
                            'included' => __('Inclus în preț'),
                            'added_on_top' => __('Adăugat peste preț'),
                        ])
                        ->placeholder(__('Moștenește'))
                        ->columnSpanFull(),
                ])
                ->columns(1),

            SC\Section::make(__('Detalii suplimentare'))
                ->collapsed()
                ->schema([
                    Forms\Components\TagsInput::make('perks')
                        ->label(__('Perks / beneficii'))
                        ->placeholder(__('Acces VIP, drink, manual…'))
                        ->columnSpanFull(),

                    Forms\Components\Toggle::make('is_active')
                        ->label(__('Activă'))
                        ->default(true)
                        ->columnSpanFull(),

                    Forms\Components\Toggle::make('is_refundable')
                        ->label(__('Refundabilă'))
                        ->default(true)
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('sort_order')
                        ->label(__('Ordine afișare'))
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
                    ->label(__('Nume'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('price_cents')
                    ->label(__('Preț'))
                    ->formatStateUsing(fn ($state, $record) => $state
                        ? number_format($state / 100, 2, ',', '.') . ' ' . ($record->currency ?? 'RON')
                        : '—')
                    ->alignRight()
                    ->sortable(),

                Tables\Columns\TextColumn::make('capacity_share')
                    ->label(__('Locuri'))
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('min_age')
                    ->label(__('Vârstă'))
                    ->formatStateUsing(function ($state, $record) {
                        $min = $record->min_age;
                        $max = $record->max_age;
                        if ($min !== null && $max !== null) return "{$min}–{$max}";
                        if ($min !== null) return "≥{$min}";
                        if ($max !== null) return "≤{$max}";
                        return '—';
                    }),

                Tables\Columns\IconColumn::make('is_active')->label(__('Activă'))->boolean(),
                Tables\Columns\IconColumn::make('is_refundable')->label(__('Refundabilă'))->boolean(),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label(__('Ordine'))
                    ->sortable(),
            ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->headerActions([
                CreateAction::make()->label(__('Adaugă variantă')),
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
