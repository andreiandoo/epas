<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\VendorResource\Pages;
use App\Models\Vendor;
use App\Services\AnafService;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Actions;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use App\Enums\TenantType;

class VendorResource extends Resource
{
    protected static ?string $model = Vendor::class;
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-building-storefront';
    protected static \UnitEnum|string|null $navigationGroup = 'Festival';
    protected static ?int $navigationSort = 2;
    protected static ?string $navigationLabel = 'Vendori';
    protected static ?string $modelLabel = 'Vendor';
    protected static ?string $pluralModelLabel = 'Vendori';

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
                SC\Tabs::make('Vendor')
                    ->tabs([
                        SC\Tabs\Tab::make('Date generale')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Nume stand/brand')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('slug')
                                    ->maxLength(255)
                                    ->unique(ignoreRecord: true),
                                Forms\Components\TextInput::make('email')
                                    ->email()
                                    ->required(),
                                Forms\Components\TextInput::make('password')
                                    ->password()
                                    ->revealable()
                                    ->required(fn (string $operation) => $operation === 'create')
                                    ->dehydrated(fn ($state) => filled($state)),
                                Forms\Components\TextInput::make('phone')
                                    ->tel(),
                                Forms\Components\TextInput::make('contact_person')
                                    ->label('Persoana de contact'),
                                Forms\Components\FileUpload::make('logo_url')
                                    ->label('Logo')
                                    ->image()
                                    ->directory('vendors/logos'),
                                Forms\Components\Select::make('status')
                                    ->options([
                                        'active'    => 'Activ',
                                        'suspended' => 'Suspendat',
                                        'inactive'  => 'Inactiv',
                                    ])
                                    ->default('active')
                                    ->required(),
                            ])->columns(2),

                        SC\Tabs\Tab::make('Date societate')
                            ->schema([
                                SC\Section::make('Interogare ANAF')
                                    ->description('Introdu CUI-ul si datele se completeaza automat')
                                    ->schema([
                                        Forms\Components\TextInput::make('cui')
                                            ->label('CUI')
                                            ->maxLength(20)
                                            ->suffixAction(
                                                Forms\Components\Actions\Action::make('lookupAnaf')
                                                    ->icon('heroicon-o-magnifying-glass')
                                                    ->label('Interogare ANAF')
                                                    ->action(function (\Filament\Schemas\Components\Utilities\Get $get, \Filament\Schemas\Components\Utilities\Set $set) {
                                                        $cui = $get('cui');
                                                        if (! $cui) {
                                                            Notification::make()
                                                                ->title('Introdu un CUI valid')
                                                                ->warning()
                                                                ->send();
                                                            return;
                                                        }

                                                        $anaf = new AnafService();
                                                        if (! $anaf->isValidCui($cui)) {
                                                            Notification::make()
                                                                ->title('CUI invalid')
                                                                ->danger()
                                                                ->send();
                                                            return;
                                                        }

                                                        $data = $anaf->lookupByCui($cui);
                                                        if (! $data) {
                                                            Notification::make()
                                                                ->title('Societatea nu a fost gasita in baza ANAF')
                                                                ->danger()
                                                                ->send();
                                                            return;
                                                        }

                                                        $set('company_name', $data['company_name']);
                                                        $set('fiscal_name', $data['company_name']);
                                                        $set('reg_com', $data['reg_com']);
                                                        $set('cod_caen', $data['cod_caen']);
                                                        $set('fiscal_address', $data['address']);
                                                        $set('county', $data['county']);
                                                        $set('city', $data['city']);
                                                        $set('is_vat_payer', $data['vat_payer']);
                                                        $set('vat_since', $data['vat_since']);
                                                        $set('is_active_fiscal', $data['is_active']);
                                                        $set('is_split_vat', $data['is_split_vat'] ?? false);

                                                        Notification::make()
                                                            ->title('Date preluate din ANAF')
                                                            ->success()
                                                            ->send();
                                                    })
                                            ),
                                    ]),

                                SC\Section::make('Date fiscale')
                                    ->schema([
                                        Forms\Components\TextInput::make('company_name')
                                            ->label('Denumire comerciala'),
                                        Forms\Components\TextInput::make('fiscal_name')
                                            ->label('Denumire oficiala (ANAF)'),
                                        Forms\Components\TextInput::make('reg_com')
                                            ->label('Nr. Reg. Com.'),
                                        Forms\Components\TextInput::make('cod_caen')
                                            ->label('Cod CAEN'),
                                        Forms\Components\Textarea::make('fiscal_address')
                                            ->label('Adresa sediu social')
                                            ->rows(2)
                                            ->columnSpanFull(),
                                        Forms\Components\TextInput::make('county')
                                            ->label('Judet'),
                                        Forms\Components\TextInput::make('city')
                                            ->label('Localitate'),
                                        Forms\Components\Toggle::make('is_vat_payer')
                                            ->label('Platitor TVA')
                                            ->inline(false),
                                        Forms\Components\DatePicker::make('vat_since')
                                            ->label('TVA din data'),
                                        Forms\Components\Toggle::make('is_active_fiscal')
                                            ->label('Activ fiscal')
                                            ->inline(false),
                                        Forms\Components\Toggle::make('is_split_vat')
                                            ->label('TVA la incasare')
                                            ->inline(false),
                                    ])->columns(2),

                                SC\Section::make('Date bancare')
                                    ->schema([
                                        Forms\Components\TextInput::make('bank_name')
                                            ->label('Banca'),
                                        Forms\Components\TextInput::make('iban')
                                            ->label('IBAN')
                                            ->maxLength(34),
                                    ])->columns(2),
                            ]),
                    ])->columnSpanFull(),
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
                Tables\Columns\TextColumn::make('company_name')
                    ->label('Societate')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('cui')
                    ->label('CUI')
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_vat_payer')
                    ->label('TVA')
                    ->boolean(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Telefon'),
                Tables\Columns\TextColumn::make('contact_person')
                    ->label('Contact')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => 'active',
                        'warning' => 'suspended',
                        'danger'  => 'inactive',
                    ]),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active'    => 'Activ',
                        'suspended' => 'Suspendat',
                        'inactive'  => 'Inactiv',
                    ]),
                Tables\Filters\TernaryFilter::make('is_vat_payer')
                    ->label('Platitor TVA'),
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
            'index'  => Pages\ListVendors::route('/'),
            'create' => Pages\CreateVendor::route('/create'),
            'edit'   => Pages\EditVendor::route('/{record}/edit'),
        ];
    }
}
