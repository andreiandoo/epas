<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\MerchandiseItemResource\Pages;
use App\Models\MerchandiseItem;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Actions;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use App\Enums\TenantType;

class MerchandiseItemResource extends Resource
{
    protected static ?string $model = MerchandiseItem::class;
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-cube';
    protected static \UnitEnum|string|null $navigationGroup = 'Festival';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('Festival');
    }
    protected static ?int $navigationSort = 5;
    protected static ?string $navigationLabel = 'Marfa';

    public static function getNavigationLabel(): string
    {
        return __('Marfa');
    }
    protected static ?string $modelLabel = 'Produs marfa';

    public static function getModelLabel(): string
    {
        return __('Produs marfa');
    }
    protected static ?string $pluralModelLabel = 'Marfa';

    public static function getPluralModelLabel(): string
    {
        return __('Marfa');
    }

    public static function shouldRegisterNavigation(): bool
    {
        $tenant = auth()->user()?->tenant;
        return $tenant && $tenant->tenant_type === TenantType::Festival;
    }

    public static function getEloquentQuery(): Builder
    {
        $tenant = auth()->user()->tenant;
        return parent::getEloquentQuery()->where('tenant_id', $tenant?->id);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                SC\Section::make('Detalii produs')
                    ->schema([
                        Forms\Components\Select::make('festival_edition_id')
                            ->label(__('Editie festival'))
                            ->relationship('edition', 'name', modifyQueryUsing: function (Builder $query) {
                                $tenant = auth()->user()->tenant;
                                return $query->where('tenant_id', $tenant?->id);
                            })
                            ->required()
                            ->searchable()
                            ->preload(),
                        Forms\Components\Select::make('merchandise_supplier_id')
                            ->label('Furnizor')
                            ->relationship('supplier', 'name', modifyQueryUsing: function (Builder $query) {
                                $tenant = auth()->user()->tenant;
                                return $query->where('tenant_id', $tenant?->id);
                            })
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->label('Nume furnizor')
                                    ->required(),
                                Forms\Components\TextInput::make('cui')
                                    ->label('CUI'),
                                Forms\Components\TextInput::make('contact_person')
                                    ->label(__('Persoana contact')),
                                Forms\Components\TextInput::make('phone')
                                    ->label('Telefon')
                                    ->tel(),
                                Forms\Components\TextInput::make('email')
                                    ->email(),
                            ])
                            ->createOptionUsing(function (array $data) {
                                $data['tenant_id'] = auth()->user()->tenant_id;
                                return \App\Models\MerchandiseSupplier::create($data)->id;
                            }),
                        Forms\Components\TextInput::make('name')
                            ->label('Nume produs')
                            ->required()
                            ->maxLength(255)
                            ->placeholder(__('Pahar personalizat 500ml')),
                        Forms\Components\Select::make('type')
                            ->label('Tip')
                            ->options([
                                'consumable' => 'Consumabil',
                                'equipment'  => 'Echipament',
                                'packaging'  => 'Ambalaj',
                                'ingredient' => 'Ingredient',
                                'other'      => 'Altele',
                            ])
                            ->default('consumable')
                            ->required(),
                        Forms\Components\Select::make('unit')
                            ->label(__('Unitate masura'))
                            ->options([
                                'buc' => __('Bucati'),
                                'kg'  => 'Kilograme',
                                'l'   => 'Litri',
                                'set' => 'Seturi',
                            ])
                            ->default('buc')
                            ->required(),
                        Forms\Components\TextInput::make('quantity')
                            ->label('Cantitate')
                            ->numeric()
                            ->required()
                            ->minValue(0),
                        Forms\Components\TextInput::make('acquisition_price_cents')
                            ->label(__('Pret achizitie'))
                            ->numeric()
                            ->required()
                            ->suffix('RON')
                            ->step(0.01)
                            ->formatStateUsing(fn ($state) => $state ? number_format($state / 100, 2, '.', '') : '0.00')
                            ->dehydrateStateUsing(fn ($state) => (int) round(((float) $state) * 100)),
                        Forms\Components\Select::make('currency')
                            ->label('Moneda')
                            ->options([
                                'RON' => 'RON',
                                'EUR' => 'EUR',
                            ])
                            ->default('RON'),
                        Forms\Components\TextInput::make('vat_rate')
                            ->label('Cota TVA %')
                            ->numeric()
                            ->default(19)
                            ->suffix('%'),
                    ])->columns(2),

                SC\Section::make(__('Factura'))
                    ->schema([
                        Forms\Components\TextInput::make('invoice_number')
                            ->label(__('Nr. factura')),
                        Forms\Components\DatePicker::make('invoice_date')
                            ->label('Data factura'),
                        Forms\Components\Textarea::make('notes')
                            ->label(__('Observatii'))
                            ->rows(2)
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('edition.name')
                    ->label(__('Editie'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('supplier.name')
                    ->label('Furnizor')
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Produs')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('type')
                    ->label('Tip')
                    ->colors([
                        'primary'   => 'consumable',
                        'success'   => 'equipment',
                        'warning'   => 'packaging',
                        'info'      => 'ingredient',
                        'gray'      => 'other',
                    ]),
                Tables\Columns\TextColumn::make('quantity')
                    ->label('Cantitate')
                    ->formatStateUsing(fn ($state, $record) => rtrim(rtrim(number_format((float) $state, 2, '.', ''), '0'), '.') . ' ' . $record->unit)
                    ->sortable(),
                Tables\Columns\TextColumn::make('acquisition_price_cents')
                    ->label(__('Pret achizitie'))
                    ->formatStateUsing(fn ($state, $record) => number_format($state / 100, 2) . ' ' . $record->currency)
                    ->sortable(),
                Tables\Columns\TextColumn::make('invoice_number')
                    ->label(__('Nr. factura'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('festival_edition_id')
                    ->label(__('Editie'))
                    ->relationship('edition', 'name', modifyQueryUsing: function (Builder $query) {
                        $tenant = auth()->user()->tenant;
                        return $query->where('tenant_id', $tenant?->id);
                    }),
                Tables\Filters\SelectFilter::make('type')
                    ->label('Tip')
                    ->options([
                        'consumable' => 'Consumabil',
                        'equipment'  => 'Echipament',
                        'packaging'  => 'Ambalaj',
                        'ingredient' => 'Ingredient',
                        'other'      => 'Altele',
                    ]),
            ])
            ->actions([
                Actions\EditAction::make(),
            ])
            ->bulkActions([
                Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'    => Pages\ListMerchandiseItems::route('/'),
            'create'   => Pages\CreateMerchandiseItem::route('/create'),
            'edit'     => Pages\EditMerchandiseItem::route('/{record}/edit'),
            'bulk-add' => Pages\BulkAddMerchandiseItems::route('/bulk-add'),
        ];
    }
}
