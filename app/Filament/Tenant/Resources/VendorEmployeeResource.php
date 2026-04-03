<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\VendorEmployeeResource\Pages;
use App\Models\VendorEmployee;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Actions;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use App\Enums\TenantType;

class VendorEmployeeResource extends Resource
{
    protected static ?string $model = VendorEmployee::class;
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-user-group';
    protected static \UnitEnum|string|null $navigationGroup = 'Festival';
    protected static ?int $navigationSort = 3;
    protected static ?string $navigationLabel = 'Angajati vendori';
    protected static ?string $modelLabel = 'Angajat';
    protected static ?string $pluralModelLabel = 'Angajati vendori';

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
                SC\Section::make('Date angajat')
                    ->schema([
                        Forms\Components\Select::make('vendor_id')
                            ->label('Vendor')
                            ->relationship('vendor', 'name', modifyQueryUsing: function (Builder $query) {
                                $tenant = auth()->user()->tenant;
                                return $query->where('tenant_id', $tenant?->id);
                            })
                            ->required()
                            ->searchable()
                            ->preload(),
                        Forms\Components\TextInput::make('name')
                            ->label('Nume')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('phone')
                            ->label('Telefon')
                            ->tel(),
                        Forms\Components\TextInput::make('email')
                            ->label('Email')
                            ->email(),
                        Forms\Components\TextInput::make('pin')
                            ->label('PIN (4-6 cifre)')
                            ->required()
                            ->minLength(4)
                            ->maxLength(6)
                            ->helperText('PIN unic per vendor, folosit pentru autentificare rapida la POS'),
                        Forms\Components\Select::make('role')
                            ->label('Rol')
                            ->options([
                                'admin'    => 'Administrator',
                                'operator' => 'Operator POS',
                                'viewer'   => 'Vizualizare',
                            ])
                            ->default('operator')
                            ->required(),
                        Forms\Components\Select::make('status')
                            ->options([
                                'active'    => 'Activ',
                                'inactive'  => 'Inactiv',
                                'suspended' => 'Suspendat',
                            ])
                            ->default('active')
                            ->required(),
                        Forms\Components\CheckboxList::make('permissions')
                            ->label('Permisiuni')
                            ->options([
                                'sell'            => 'Vanzare produse',
                                'refund'          => 'Retur / Refund',
                                'view_reports'    => 'Vizualizare rapoarte',
                                'manage_products' => 'Gestionare produse',
                                'manage_employees'=> 'Gestionare angajati',
                            ])
                            ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('role') === 'operator')
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('vendor.name')
                    ->label('Vendor')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nume')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Telefon'),
                Tables\Columns\BadgeColumn::make('role')
                    ->label('Rol')
                    ->colors([
                        'danger'  => 'admin',
                        'primary' => 'operator',
                        'gray'    => 'viewer',
                    ]),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => 'active',
                        'gray'    => 'inactive',
                        'danger'  => 'suspended',
                    ]),
                Tables\Columns\TextColumn::make('shifts_count')
                    ->label('Ture')
                    ->counts('shifts'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('vendor_id')
                    ->label('Vendor')
                    ->relationship('vendor', 'name', modifyQueryUsing: function (Builder $query) {
                        $tenant = auth()->user()->tenant;
                        return $query->where('tenant_id', $tenant?->id);
                    }),
                Tables\Filters\SelectFilter::make('role')
                    ->options([
                        'admin'    => 'Administrator',
                        'operator' => 'Operator POS',
                        'viewer'   => 'Vizualizare',
                    ]),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active'    => 'Activ',
                        'inactive'  => 'Inactiv',
                        'suspended' => 'Suspendat',
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
            'index'  => Pages\ListVendorEmployees::route('/'),
            'create' => Pages\CreateVendorEmployee::route('/create'),
            'edit'   => Pages\EditVendorEmployee::route('/{record}/edit'),
        ];
    }
}
