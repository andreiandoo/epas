<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\FestivalEditionResource\Pages;
use App\Models\FestivalEdition;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FestivalEditionResource extends Resource
{
    protected static ?string $model = FestivalEdition::class;
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-calendar-days';
    protected static \UnitEnum|string|null $navigationGroup = 'Festival';
    protected static ?int $navigationSort = 1;
    protected static ?string $navigationLabel = 'Editii Festival';
    protected static ?string $modelLabel = 'Editie';
    protected static ?string $pluralModelLabel = 'Editii Festival';

    public static function getEloquentQuery(): Builder
    {
        $tenant = auth()->user()->tenant;
        return parent::getEloquentQuery()->where('tenant_id', $tenant?->id);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                SC\Section::make('Detalii editie')
                    ->schema([
                        Forms\Components\Select::make('event_id')
                            ->label('Eveniment asociat')
                            ->relationship('event', modifyQueryUsing: function (Builder $query) {
                                $tenant = auth()->user()->tenant;
                                return $query->where('tenant_id', $tenant?->id);
                            })
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->getTranslation('title', app()->getLocale()))
                            ->searchable()
                            ->preload(),
                        Forms\Components\TextInput::make('name')
                            ->label('Nume')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Electric Castle 2026'),
                        Forms\Components\TextInput::make('slug')
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Forms\Components\TextInput::make('year')
                            ->label('An')
                            ->numeric()
                            ->required()
                            ->default(date('Y')),
                        Forms\Components\TextInput::make('edition_number')
                            ->label('Numar editie')
                            ->numeric(),
                        Forms\Components\DatePicker::make('start_date')
                            ->label('Data inceput')
                            ->required(),
                        Forms\Components\DatePicker::make('end_date')
                            ->label('Data sfarsit')
                            ->required(),
                        Forms\Components\Select::make('status')
                            ->options([
                                'draft'     => 'Draft',
                                'announced' => 'Anuntat',
                                'active'    => 'Activ',
                                'completed' => 'Finalizat',
                                'cancelled' => 'Anulat',
                            ])
                            ->default('draft')
                            ->required(),
                        Forms\Components\Select::make('currency')
                            ->label('Moneda')
                            ->options([
                                'RON' => 'RON',
                                'EUR' => 'EUR',
                                'USD' => 'USD',
                            ])
                            ->default('RON'),
                        Forms\Components\Select::make('cashless_mode')
                            ->label('Mod cashless')
                            ->options([
                                'nfc'    => 'NFC (Bratara cu cip)',
                                'qr'     => 'QR Code',
                                'hybrid' => 'Hybrid (NFC + QR)',
                            ])
                            ->default('nfc'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nume')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('year')
                    ->label('An')
                    ->sortable(),
                Tables\Columns\TextColumn::make('edition_number')
                    ->label('Editia')
                    ->sortable(),
                Tables\Columns\TextColumn::make('start_date')
                    ->label('Inceput')
                    ->date('d.m.Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('end_date')
                    ->label('Sfarsit')
                    ->date('d.m.Y')
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'gray'    => 'draft',
                        'info'    => 'announced',
                        'success' => 'active',
                        'primary' => 'completed',
                        'danger'  => 'cancelled',
                    ]),
                Tables\Columns\TextColumn::make('currency')
                    ->label('Moneda'),
                Tables\Columns\TextColumn::make('vendors_count')
                    ->label('Vendori')
                    ->counts('vendors'),
            ])
            ->defaultSort('year', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft'     => 'Draft',
                        'announced' => 'Anuntat',
                        'active'    => 'Activ',
                        'completed' => 'Finalizat',
                        'cancelled' => 'Anulat',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'            => Pages\ListFestivalEditions::route('/'),
            'create'           => Pages\CreateFestivalEdition::route('/create'),
            'edit'             => Pages\EditFestivalEdition::route('/{record}/edit'),
            'external-tickets' => Pages\ImportFestivalExternalTickets::route('/{record}/external-tickets'),
        ];
    }
}
