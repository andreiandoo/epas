<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GeneralTaxResource\Pages;
use App\Models\Tax\GeneralTax;
use App\Models\EventType;
use BackedEnum;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class GeneralTaxResource extends Resource
{
    protected static ?string $model = GeneralTax::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-calculator';

    protected static ?string $navigationLabel = 'General Taxes';

    protected static ?string $navigationParentItem = 'Taxes';

    protected static \UnitEnum|string|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'General Tax';

    protected static ?string $pluralModelLabel = 'General Taxes';

    protected static ?string $slug = 'general-taxes';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function form(Schema $schema): Schema
    {
        $currencies = [
            'EUR' => 'EUR - Euro',
            'USD' => 'USD - US Dollar',
            'GBP' => 'GBP - British Pound',
            'RON' => 'RON - Romanian Leu',
            'CHF' => 'CHF - Swiss Franc',
            'PLN' => 'PLN - Polish Zloty',
            'CZK' => 'CZK - Czech Koruna',
            'HUF' => 'HUF - Hungarian Forint',
            'SEK' => 'SEK - Swedish Krona',
            'NOK' => 'NOK - Norwegian Krone',
            'DKK' => 'DKK - Danish Krone',
        ];

        return $schema
            ->schema([
                SC\Section::make('Tax Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Tax Name')
                            ->required()
                            ->maxLength(190)
                            ->placeholder('e.g., TVA 21%, Timbru Muzical, UCMR-ADA'),

                        Forms\Components\Select::make('event_type_id')
                            ->label('Event Type')
                            ->options(function () {
                                return EventType::all()
                                    ->mapWithKeys(fn ($type) => [
                                        $type->id => $type->name['en'] ?? $type->slug
                                    ]);
                            })
                            ->searchable()
                            ->placeholder('All Event Types (leave empty for global)')
                            ->helperText('Select an event type to apply this tax only to specific events, or leave empty for all events.'),

                        SC\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('value')
                                    ->label('Value')
                                    ->required()
                                    ->numeric()
                                    ->step(0.01)
                                    ->minValue(0)
                                    ->placeholder('0.00'),

                                Forms\Components\Select::make('value_type')
                                    ->label('Value Type')
                                    ->options([
                                        'percent' => 'Percentage (%)',
                                        'fixed' => 'Fixed Amount',
                                    ])
                                    ->default('percent')
                                    ->required()
                                    ->live(),

                                Forms\Components\Select::make('currency')
                                    ->label('Currency')
                                    ->options($currencies)
                                    ->default('RON')
                                    ->searchable()
                                    ->visible(fn (Get $get) => $get('value_type') === 'fixed')
                                    ->required(fn (Get $get) => $get('value_type') === 'fixed'),
                            ]),

                        Forms\Components\TextInput::make('priority')
                            ->label('Priority')
                            ->numeric()
                            ->default(0)
                            ->helperText('Higher priority taxes are applied first.'),

                        SC\Grid::make(2)
                            ->schema([
                                Forms\Components\Toggle::make('is_compound')
                                    ->label('Compound Tax')
                                    ->default(false)
                                    ->live()
                                    ->helperText('Calculated on subtotal + other taxes'),

                                Forms\Components\TextInput::make('compound_order')
                                    ->label('Compound Order')
                                    ->numeric()
                                    ->default(0)
                                    ->visible(fn (Get $get) => $get('is_compound')),
                            ]),

                        Forms\Components\RichEditor::make('explanation')
                            ->label('Explanation / Notes')
                            ->toolbarButtons(['bold', 'italic', 'link', 'bulletList', 'orderedList'])
                            ->placeholder('Describe what this tax is for...')
                            ->columnSpanFull(),
                    ])->columns(2),

                SC\Section::make('Tax Application Rules')
                    ->description('How is this tax calculated and applied?')
                    ->collapsed()
                    ->schema([
                        SC\Grid::make(2)
                            ->schema([
                                Forms\Components\Toggle::make('is_added_to_price')
                                    ->label('Added to Price')
                                    ->default(false)
                                    ->helperText('ON = Added to ticket price (like stamps). OFF = Included in price (like VAT)'),

                                Forms\Components\Select::make('applied_to_base')
                                    ->label('Applied To')
                                    ->options([
                                        'gross_with_vat' => 'Gross (with VAT)',
                                        'gross_excl_vat' => 'Gross (excluding VAT)',
                                        'ticket_price' => 'Ticket Price',
                                        'net_revenue' => 'Net Revenue',
                                    ])
                                    ->default('gross_excl_vat')
                                    ->helperText('What is the tax calculated on?'),
                            ]),

                        SC\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('min_revenue_threshold')
                                    ->label('Min Revenue Threshold')
                                    ->numeric()
                                    ->prefix('RON')
                                    ->placeholder('No minimum'),

                                Forms\Components\TextInput::make('max_revenue_threshold')
                                    ->label('Max Revenue Threshold')
                                    ->numeric()
                                    ->prefix('RON')
                                    ->placeholder('No maximum'),

                                Forms\Components\TextInput::make('min_guaranteed_amount')
                                    ->label('Min Guaranteed Amount')
                                    ->numeric()
                                    ->prefix('RON')
                                    ->placeholder('None'),
                            ]),

                        Forms\Components\Toggle::make('has_tiered_rates')
                            ->label('Has Tiered Rates')
                            ->default(false)
                            ->live()
                            ->helperText('Different rates based on revenue (e.g., UCMR-ADA)'),

                        Forms\Components\Repeater::make('tiered_rates')
                            ->label('Revenue Tiers')
                            ->visible(fn (Get $get) => $get('has_tiered_rates'))
                            ->schema([
                                Forms\Components\TextInput::make('min')
                                    ->label('Min Revenue')
                                    ->numeric()
                                    ->required()
                                    ->prefix('RON'),
                                Forms\Components\TextInput::make('max')
                                    ->label('Max Revenue')
                                    ->numeric()
                                    ->prefix('RON')
                                    ->placeholder('Unlimited'),
                                Forms\Components\TextInput::make('rate')
                                    ->label('Rate')
                                    ->numeric()
                                    ->required()
                                    ->suffix('%'),
                            ])
                            ->columns(3)
                            ->defaultItems(0)
                            ->columnSpanFull(),
                    ]),

                SC\Section::make('Payment Information')
                    ->description('Where and to whom is this tax paid?')
                    ->collapsed()
                    ->schema([
                        SC\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('beneficiary')
                                    ->label('Beneficiary')
                                    ->maxLength(255)
                                    ->placeholder('e.g., ANAF, UCMR-ADA, DITL'),

                                Forms\Components\TextInput::make('iban')
                                    ->label('IBAN')
                                    ->maxLength(34)
                                    ->placeholder('RO49AAAA1B31007593840000'),
                            ]),

                        Forms\Components\Textarea::make('beneficiary_address')
                            ->label('Beneficiary Address')
                            ->rows(2)
                            ->columnSpanFull(),

                        Forms\Components\RichEditor::make('where_to_pay')
                            ->label('Where / How to Pay')
                            ->toolbarButtons(['bold', 'italic', 'link', 'bulletList'])
                            ->placeholder('Payment instructions, online portal URLs, etc.')
                            ->columnSpanFull(),
                    ]),

                SC\Section::make('Payment Terms')
                    ->description('When must this tax be paid?')
                    ->collapsed()
                    ->schema([
                        SC\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('payment_term_type')
                                    ->label('Payment Term Type')
                                    ->options([
                                        'day_of_month' => 'Day of Following Month',
                                        'days_after_event' => 'Days After Event',
                                        'at_sale' => 'At Time of Sale',
                                        'quarterly' => 'Quarterly',
                                        'custom' => 'Custom',
                                    ])
                                    ->live(),

                                Forms\Components\TextInput::make('payment_term_day')
                                    ->label('Day of Month')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(31)
                                    ->placeholder('e.g., 10, 25')
                                    ->visible(fn (Get $get) => in_array($get('payment_term_type'), ['day_of_month', 'quarterly'])),

                                Forms\Components\TextInput::make('payment_term_days_after')
                                    ->label('Days After Event')
                                    ->numeric()
                                    ->minValue(1)
                                    ->placeholder('e.g., 45')
                                    ->visible(fn (Get $get) => $get('payment_term_type') === 'days_after_event'),
                            ]),

                        Forms\Components\TextInput::make('payment_term')
                            ->label('Payment Term Description')
                            ->maxLength(255)
                            ->placeholder('e.g., "10 a lunii următoare", "45 zile de la eveniment"')
                            ->columnSpanFull(),
                    ]),

                SC\Section::make('Legal & Documentation')
                    ->description('Legal basis and required declarations')
                    ->collapsed()
                    ->schema([
                        Forms\Components\TextInput::make('legal_basis')
                            ->label('Legal Basis')
                            ->maxLength(255)
                            ->placeholder('e.g., Art. 291 Cod Fiscal, Legea 35/1994')
                            ->columnSpanFull(),

                        Forms\Components\RichEditor::make('declaration')
                            ->label('Declaration Template / Requirements')
                            ->toolbarButtons(['bold', 'italic', 'link', 'bulletList', 'orderedList'])
                            ->placeholder('What declarations need to be filed?')
                            ->columnSpanFull(),
                    ]),

                SC\Section::make('Instructions')
                    ->description('What needs to be done before and after the event?')
                    ->collapsed()
                    ->schema([
                        Forms\Components\RichEditor::make('before_event_instructions')
                            ->label('Before Event')
                            ->toolbarButtons(['bold', 'italic', 'link', 'bulletList', 'orderedList'])
                            ->placeholder('Steps to complete before the event...')
                            ->columnSpanFull(),

                        Forms\Components\RichEditor::make('after_event_instructions')
                            ->label('After Event')
                            ->toolbarButtons(['bold', 'italic', 'link', 'bulletList', 'orderedList'])
                            ->placeholder('Steps to complete after the event...')
                            ->columnSpanFull(),
                    ]),

                SC\Section::make('Validity Period')
                    ->description('When is this tax active?')
                    ->collapsed()
                    ->schema([
                        SC\Grid::make(2)
                            ->schema([
                                Forms\Components\DatePicker::make('valid_from')
                                    ->label('Valid From')
                                    ->placeholder('Always'),

                                Forms\Components\DatePicker::make('valid_until')
                                    ->label('Valid Until')
                                    ->placeholder('Forever')
                                    ->afterOrEqual('valid_from'),
                            ]),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Inactive taxes will not be applied.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('eventType.name')
                    ->label('Event Type')
                    ->formatStateUsing(function ($state) {
                        if (is_array($state)) {
                            return $state['en'] ?? '-';
                        }
                        return $state ?? 'All Types';
                    })
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('value')
                    ->label('Value')
                    ->formatStateUsing(function ($state, $record) {
                        if ($record->value_type === 'percent') {
                            return number_format($state, 2) . '%';
                        }
                        return number_format($state, 2) . ' ' . ($record->currency ?? '');
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('beneficiary')
                    ->label('Beneficiary')
                    ->placeholder('-')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('payment_term')
                    ->label('Payment Term')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('is_added_to_price')
                    ->label('Added to Price')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('validity')
                    ->label('Status')
                    ->state(fn ($record) => $record->getValidityStatus())
                    ->badge()
                    ->colors([
                        'success' => 'active',
                        'gray' => 'inactive',
                        'info' => 'scheduled',
                        'danger' => 'expired',
                    ])
                    ->formatStateUsing(fn ($state) => ucfirst($state)),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status'),
                Tables\Filters\SelectFilter::make('value_type')
                    ->label('Value Type')
                    ->options([
                        'percent' => 'Percentage',
                        'fixed' => 'Fixed Amount',
                    ]),
                Tables\Filters\SelectFilter::make('event_type_id')
                    ->label('Event Type')
                    ->options(function () {
                        return EventType::all()
                            ->mapWithKeys(fn ($type) => [
                                $type->id => $type->name['en'] ?? $type->slug
                            ]);
                    }),
            ])
            ->defaultSort('priority', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGeneralTaxes::route('/'),
            'create' => Pages\CreateGeneralTax::route('/create'),
            'edit' => Pages\EditGeneralTax::route('/{record}/edit'),
        ];
    }
}
