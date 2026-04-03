<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\WristbandResource\Pages;
use App\Models\Wristband;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Actions;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use App\Enums\TenantType;

class WristbandResource extends Resource
{
    protected static ?string $model = Wristband::class;
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-identification';
    protected static \UnitEnum|string|null $navigationGroup = 'Festival';
    protected static ?int $navigationSort = 4;
    protected static ?string $navigationLabel = 'Bratari';
    protected static ?string $modelLabel = 'Bratara';
    protected static ?string $pluralModelLabel = 'Bratari';

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
                SC\Section::make('Detalii bratara')
                    ->schema([
                        Forms\Components\Select::make('festival_edition_id')
                            ->label('Editie festival')
                            ->relationship('edition', 'name', modifyQueryUsing: function (Builder $query) {
                                $tenant = auth()->user()->tenant;
                                return $query->where('tenant_id', $tenant?->id);
                            })
                            ->required()
                            ->searchable()
                            ->preload(),
                        Forms\Components\TextInput::make('uid')
                            ->label('UID (NFC/QR)')
                            ->required()
                            ->unique(ignoreRecord: true),
                        Forms\Components\Select::make('wristband_type')
                            ->label('Tip')
                            ->options([
                                'nfc'  => 'NFC',
                                'qr'   => 'QR Code',
                                'rfid' => 'RFID',
                            ])
                            ->default('nfc')
                            ->required(),
                        Forms\Components\Select::make('status')
                            ->options([
                                'unassigned' => 'Neasignata',
                                'assigned'   => 'Asignata',
                                'active'     => 'Activa',
                                'disabled'   => 'Dezactivata',
                            ])
                            ->default('unassigned')
                            ->required(),
                        Forms\Components\TextInput::make('balance_cents')
                            ->label('Sold')
                            ->numeric()
                            ->default(0)
                            ->suffix('RON')
                            ->formatStateUsing(fn ($state) => $state ? number_format($state / 100, 2, '.', '') : '0.00')
                            ->dehydrated(false),
                        Forms\Components\Select::make('currency')
                            ->label('Moneda')
                            ->options([
                                'RON' => 'RON',
                                'EUR' => 'EUR',
                            ])
                            ->default('RON'),
                        Forms\Components\Select::make('customer_id')
                            ->label('Client conectat')
                            ->relationship('customer', 'email')
                            ->searchable()
                            ->preload()
                            ->nullable(),
                        Forms\Components\Placeholder::make('customer_info')
                            ->label('Detalii client')
                            ->visible(fn ($record) => $record?->customer_id)
                            ->content(function ($record) {
                                if (!$record?->customer) return '-';
                                $c = $record->customer;
                                return "{$c->first_name} {$c->last_name} | {$c->email} | {$c->phone}";
                            }),
                        Forms\Components\Placeholder::make('pass_info')
                            ->label('Pass festival')
                            ->visible(fn ($record) => $record?->festival_pass_purchase_id)
                            ->content(function ($record) {
                                $pp = $record?->passPurchase;
                                if (!$pp) return '-';
                                return "#{$pp->code} — {$pp->festivalPass?->name} ({$pp->status})";
                            }),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('uid')
                    ->label('UID')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('edition.name')
                    ->label('Editie')
                    ->sortable(),
                Tables\Columns\TextColumn::make('wristband_type')
                    ->label('Tip')
                    ->badge(),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'gray'    => 'unassigned',
                        'info'    => 'assigned',
                        'success' => 'active',
                        'danger'  => 'disabled',
                    ]),
                Tables\Columns\TextColumn::make('balance_cents')
                    ->label('Sold')
                    ->formatStateUsing(fn ($state, $record) => number_format($state / 100, 2) . ' ' . $record->currency)
                    ->sortable(),
                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Client')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('activated_at')
                    ->label('Activata la')
                    ->dateTime('d.m.Y H:i')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'unassigned' => 'Neasignata',
                        'assigned'   => 'Asignata',
                        'active'     => 'Activa',
                        'disabled'   => 'Dezactivata',
                    ]),
                Tables\Filters\SelectFilter::make('festival_edition_id')
                    ->label('Editie')
                    ->relationship('edition', 'name', modifyQueryUsing: function (Builder $query) {
                        $tenant = auth()->user()->tenant;
                        return $query->where('tenant_id', $tenant?->id);
                    }),
                Tables\Filters\SelectFilter::make('wristband_type')
                    ->label('Tip')
                    ->options([
                        'nfc'  => 'NFC',
                        'qr'   => 'QR Code',
                        'rfid' => 'RFID',
                    ]),
            ])
            ->actions([
                Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListWristbands::route('/'),
            'create' => Pages\CreateWristband::route('/create'),
            'edit'   => Pages\EditWristband::route('/{record}/edit'),
        ];
    }
}
